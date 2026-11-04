<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
$password = getenv('TIENDA_TEST_ADMIN_PASS') ?: '';
if ($password === '') { throw new RuntimeException('Define TIENDA_TEST_ADMIN_PASS'); }
$marker = 'taxo-' . bin2hex(random_bytes(4));
$product = null;
$row = static function (string $table, string $slug) use ($pdo) {
    $st = $pdo->prepare("SELECT * FROM $table WHERE slug=?"); $st->execute([$slug]); return $st->fetch();
};
$count = static function (string $table) use ($pdo): int { return (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn(); };
$version = static function (string $html): string { preg_match('/name="version" value="([a-f0-9]*)"/', $html, $m); return $m[1] ?? ''; };
try {
    $pdo->exec('DELETE FROM acceso_intento');
    $anon = new TestBrowser();
    foreach (['admin/colecciones.php', 'admin/coleccion.php?tipo=marca'] as $path) {
        $anon->request($path);
        check($anon->status === 302 && ($anon->headers['location'] ?? '') === 'login.php', 'requiere sesion administrativa: ' . $path);
    }
    $b = new TestBrowser();
    $b->request('admin/login.php');
    $b->request('admin/login.php', ['user'=>'admin', 'pass'=>$password]);
    check($b->status === 302, 'administrador accede');
    $html = $b->request('admin/colecciones.php');
    $seedCategories = $count('categoria'); $seedBrands = $count('marca');
    check($b->status === 200 && strpos($html, 'Colecciones (' . $seedCategories . ')') !== false && strpos($html, 'Marcas (' . $seedBrands . ')') !== false, 'listado muestra todas las colecciones y marcas');
    check(strpos($html, 'aria-current="page">Colecciones y marcas') !== false && strpos($html, '>Mujer<') !== false, 'seccion marcada y nombres originales traducidos');
    check(strpos($b->headers['cache-control'] ?? '', 'no-store') !== false && strpos($b->headers['x-robots-tag'] ?? '', 'noindex') !== false, 'panel privado sin cache ni indexacion');

    // Alta de colección: nace oculta aunque se manipule el formulario.
    $b->request('admin/coleccion.php?tipo=coleccion');
    $valid = ['nombre'=>$marker . ' Noche', 'slug'=>$marker . '-noche', 'descripcion'=>'Fragancias de noche', 'activa'=>'1', 'orden'=>'7'];
    $before = $count('categoria');
    foreach ([
        ['nombre'=>''], ['nombre'=>['x']], ['nombre'=>str_repeat('a', 121)], ['nombre'=>"a\0b"], ['nombre'=>"\xFF"],
        ['slug'=>'Con Espacios'], ['slug'=>'acento-ñ'], ['slug'=>'-guion'], ['slug'=>'mujer'],
        ['orden'=>'-1'], ['orden'=>'10000'], ['orden'=>'1.5'], ['orden'=>'abc'],
        ['descripcion'=>str_repeat('d', 2001)], ['descripcion'=>"x\x07y"],
    ] as $bad) {
        $existing = $pdo->query("SELECT slug FROM categoria ORDER BY id LIMIT 1")->fetchColumn();
        if (($bad['slug'] ?? null) === 'mujer') { $bad['slug'] = $existing; }
        $html = $b->request('admin/coleccion.php?tipo=coleccion', $bad + $valid);
        check($b->status === 422 && $count('categoria') === $before && strpos($html, 'No se ha guardado') !== false, 'alta de coleccion rechaza ' . json_encode(array_keys($bad)));
    }
    check(strpos($html, 'value="' . $marker . ' Noche"') !== false, 'datos validos se conservan tras un error');
    $b->request('admin/coleccion.php?tipo=coleccion', $valid, false);
    check($b->status === 403 && $count('categoria') === $before, 'alta sin CSRF rechazada');
    $b->request('admin/coleccion.php?tipo=coleccion', $valid);
    $created = $row('categoria', $marker . '-noche');
    check($b->status === 303 && ($b->headers['location'] ?? '') === 'colecciones.php' && $created && (int)$created['activa'] === 0 && (int)$created['orden'] === 7, 'coleccion creada oculta con su orden');
    $html = $b->request('admin/colecciones.php');
    check(strpos($html, 'creada como oculta') !== false && strpos($html, 'data-taxonomy="coleccion-' . $created['id'] . '"') !== false, 'confirmacion y nueva fila en el listado');
    $public = (new TestBrowser())->request('public/catalogo.php');
    check(strpos($public, $marker . ' Noche') === false, 'coleccion oculta no aparece en la tienda');

    // Edición: activar, proteger enlaces y detectar formularios antiguos.
    $url = 'admin/coleccion.php?tipo=coleccion&id=' . $created['id'];
    $html = $b->request($url);
    $v1 = $version($html);
    check($b->status === 200 && strlen($v1) === 64 && strpos($html, 'name="slug"') === false, 'edicion muestra enlace fijo y huella de version');
    $edit = ['nombre'=>$marker . ' Noche <script>x</script>', 'descripcion'=>'', 'activa'=>'1', 'orden'=>'3', 'slug'=>'otro-enlace', 'version'=>$v1];
    $b->request($url, $edit);
    $after = $row('categoria', $marker . '-noche');
    check($b->status === 303 && (int)$after['activa'] === 1 && (int)$after['orden'] === 3 && $after['descripcion'] === null && !$row('categoria', 'otro-enlace'), 'edicion activa, ordena y no cambia el enlace');
    $html = $b->request('admin/colecciones.php');
    check(strpos($html, '&lt;script&gt;x&lt;/script&gt;') !== false && strpos($html, '<script>x') === false, 'nombre escapado en el listado');
    $public = (new TestBrowser())->request('public/catalogo.php');
    check(strpos($public, $marker . ' Noche') !== false, 'coleccion activada aparece en la tienda');
    $b->request($url, ['version'=>$v1] + $edit);
    check($b->status === 409 && strpos($b->request($url, ['version'=>$v1] + $edit), 'ha cambiado desde que abriste') !== false, 'formulario antiguo detecta el conflicto');
    $b->request($url, ['version'=>['x']] + $edit);
    check($b->status === 409, 'version no escalar tratada como conflicto');

    // Ocultar una colección con perfumes visibles exige confirmación explícita.
    $brand = (int)$pdo->query('SELECT id FROM marca ORDER BY id LIMIT 1')->fetchColumn();
    $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,precio,stock,activo) VALUES (?,?,?,?,?,?,1)')
        ->execute([$created['id'], $brand, $marker . ' perfume', $marker . '-perfume', 30, 5]);
    $product = (int)$pdo->lastInsertId();
    $browser = new TestBrowser();
    $browser->request('public/producto.php?p=' . $marker . '-perfume');
    check($browser->status === 200, 'perfume visible en coleccion activa');
    $html = $b->request($url);
    check(strpos($html, 'name="confirmar_ocultar"') !== false, 'colección visible ofrece confirmacion al ocultar');
    $hide = ['nombre'=>$marker . ' Noche', 'descripcion'=>'', 'activa'=>'0', 'orden'=>'3', 'version'=>$version($html)];
    $html = $b->request($url, $hide);
    check($b->status === 422 && strpos($html, 'su único perfume activo') !== false && (int)$row('categoria', $marker . '-noche')['activa'] === 1, 'ocultar sin confirmar no retira perfumes');
    $b->request($url, $hide + ['confirmar_ocultar'=>'1']);
    check($b->status === 303 && (int)$row('categoria', $marker . '-noche')['activa'] === 0, 'ocultar con confirmacion');
    $browser->request('public/producto.php?p=' . $marker . '-perfume');
    check($browser->status === 404, 'perfume de coleccion oculta deja de mostrarse');
    $html = $b->request('admin/colecciones.php');
    check(strpos($html, '1 activos de 1') !== false, 'listado cuenta perfumes activos y totales');

    // Marcas.
    $before = $count('marca');
    $b->request('admin/coleccion.php?tipo=marca');
    $brandData = ['nombre'=>$marker . ' Maison', 'slug'=>$marker . '-maison', 'descripcion'=>'Casa ficticia', 'pais'=>'Francia'];
    foreach ([['pais'=>str_repeat('p', 81)], ['pais'=>"x\ny"], ['slug'=>''], ['nombre'=>'   ']] as $bad) {
        $b->request('admin/coleccion.php?tipo=marca', $bad + $brandData);
        check($b->status === 422 && $count('marca') === $before, 'alta de marca rechaza ' . json_encode($bad));
    }
    $b->request('admin/coleccion.php?tipo=marca', $brandData);
    $brandRow = $row('marca', $marker . '-maison');
    check($b->status === 303 && $brandRow && $brandRow['pais'] === 'Francia', 'marca creada');
    $b->request('admin/coleccion.php?tipo=marca', ['slug'=>$marker . '-maison'] + $brandData);
    check($b->status === 422 && $count('marca') === $before + 1, 'enlace de marca duplicado rechazado');
    $murl = 'admin/coleccion.php?tipo=marca&id=' . $brandRow['id'];
    $html = $b->request($murl);
    check(strpos($html, 'name="activa"') === false && strpos($html, 'name="pais"') !== false, 'marca sin visibilidad propia y con pais');
    $b->request($murl, ['nombre'=>$marker . ' Maison París', 'descripcion'=>'', 'pais'=>'', 'version'=>$version($html)]);
    $brandRow = $row('marca', $marker . '-maison');
    check($b->status === 303 && $brandRow['nombre'] === $marker . ' Maison París' && $brandRow['pais'] === null, 'marca editada; campos opcionales vacios se guardan como sin dato');
    check(strpos($b->request('admin/colecciones.php'), $marker . ' Maison París') !== false, 'marca editada en el listado');

    foreach (['admin/coleccion.php?tipo=otra', 'admin/coleccion.php', 'admin/coleccion.php?tipo[]=marca', 'admin/coleccion.php?tipo=marca&id=0',
        'admin/coleccion.php?tipo=marca&id=abc', 'admin/coleccion.php?tipo=marca&id=2147483647'] as $path) {
        $b->request($path);
        check($b->status === 404, 'ruta no valida responde 404: ' . $path);
    }
    $curl = $b->prepare('admin/colecciones.php'); curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
    $b->receive(curl_exec($curl)); curl_setopt($curl, CURLOPT_CUSTOMREQUEST, null);
    $curl = $b->prepare($murl); curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
    $b->receive(curl_exec($curl)); curl_setopt($curl, CURLOPT_CUSTOMREQUEST, null);
    check($b->status === 405 && $row('marca', $marker . '-maison') !== false, 'no existe borrado de marcas');
    echo "PASS $checks comprobaciones de colecciones y marcas\n";
} finally {
    if ($product !== null) { $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$product]); }
    $pdo->prepare("DELETE FROM categoria WHERE slug LIKE ?")->execute([$marker . '%']);
    $pdo->prepare("DELETE FROM marca WHERE slug LIKE ?")->execute([$marker . '%']);
    $pdo->exec('DELETE FROM acceso_intento');
}
