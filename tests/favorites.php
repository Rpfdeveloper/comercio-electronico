<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
$prefix = 'saved-' . bin2hex(random_bytes(5));
$ids = []; $category = null; $brand = null;
$setCookie = static function (TestBrowser $browser, string $value): void {
    $handle = $browser->prepare('public/favoritos.php');
    curl_setopt($handle, CURLOPT_COOKIELIST, '127.0.0.1' . "\tFALSE\t/public/\tFALSE\t" . (time()+3600) . "\tperfume_favorites\t" . $value);
};
$cards = static function (string $body): int { return substr_count($body, '<article class="card catalog-product">'); };
try {
    $pdo->prepare('INSERT INTO categoria(nombre,slug,activa) VALUES (?,?,1)')->execute(['Pruebas de favoritos', $prefix]);
    $category = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute(['Marca de favoritos', $prefix]);
    $brand = (int)$pdo->lastInsertId();
    for ($i=0; $i<25; ++$i) {
        $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,descripcion_corta,precio,stock,activo) VALUES (?,?,?,?,?,20,2,1)')
            ->execute([$category, $brand, 'Perfume <script>guardado</script> ' . $i, $prefix . '-' . $i, 'Fragancia de prueba']);
        $ids[] = (int)$pdo->lastInsertId();
    }
    $b = new TestBrowser();
    $body = $b->request('public/favoritos.php');
    check($b->status === 200 && $cards($body) === 0 && str_contains($body, 'Tu colección empieza'), 'coleccion vacia util y sin registro');
    check(!isset($b->headers['set-cookie']) || !str_contains($b->headers['set-cookie'], 'perfume_favorites'), 'visitar no crea cookie de favoritos');
    $body = $b->request('public/producto.php?p=' . $prefix . '-0');
    check(str_contains($body, 'Guardar perfume') && $b->token !== '', 'ficha permite guardar sin JavaScript');
    $b->request('public/favoritos.php', ['action'=>'add', 'id_producto'=>(string)$ids[0]], false);
    check($b->status === 403, 'guardar exige CSRF');
    $b->request('public/favoritos.php', ['action'=>'add', 'id_producto'=>(string)$ids[0], 'return'=>'catalogo.php?c=' . $prefix . '&page=1']);
    check($b->status === 303 && $b->headers['location'] === '/public/catalogo.php?c=' . $prefix . '&page=1', 'guardar vuelve a la pagina y conserva filtros');
    $cookie = $b->headers['set-cookie'] ?? '';
    check(str_contains($cookie, 'perfume_favorites=') && str_contains(strtolower($cookie), 'httponly') && str_contains($cookie, 'SameSite=Lax') && str_contains($cookie, 'Max-Age=15552000'), 'cookie limitada a 180 dias con protecciones');
    $body = $b->request('public/favoritos.php');
    check($cards($body) === 1 && str_contains($body, 'Guardado · quitar'), 'perfume guardado recuperable');
    check(!str_contains($body, '<script>guardado') && str_contains($body, '&lt;script&gt;guardado'), 'escapa nombres en tarjetas y botones');
    check(str_contains($b->headers['cache-control'] ?? '', 'no-store') && str_contains($b->headers['x-robots-tag'] ?? '', 'noindex'), 'coleccion privada sin cache ni indexacion');
    $b->request('public/favoritos.php', ['action'=>'add', 'id_producto'=>(string)$ids[0]]);
    $body = $b->request('public/favoritos.php');
    check($cards($body) === 1, 'guardar repetido no duplica');
    $returning = new TestBrowser();
    $handle = $b->prepare('public/favoritos.php');
    $newHandle = $returning->prepare('public/favoritos.php');
    foreach (curl_getinfo($handle, CURLINFO_COOKIELIST) as $line) {
        $fields = explode("\t", $line);
        if ((int)$fields[4] > time()) { curl_setopt($newHandle, CURLOPT_COOKIELIST, $line); }
    }
    $body = $returning->request('public/favoritos.php');
    check($cards($body) === 1 && $returning->token !== $b->token, 'favoritos sobreviven a una nueva sesion sin cuenta');
    $pdo->prepare('UPDATE producto SET precio=33.50,stock=0 WHERE id=?')->execute([$ids[0]]);
    $body = $returning->request('public/favoritos.php');
    check(str_contains($body, '33,50 €') && str_contains($body, '>Agotado<'), 'recarga precio y stock actuales');
    $pdo->prepare('INSERT INTO producto_variante(id_producto,capacidad_ml,precio,stock,activo) VALUES (?,30,5,0,1),(?,100,45,2,1)')->execute([$ids[1],$ids[1]]);
    $returning->request('public/favoritos.php', ['action'=>'add','id_producto'=>(string)$ids[1]]);
    $body = $returning->request('public/favoritos.php');
    check(str_contains($body, 'desde 45,00 €') && !str_contains($body, 'desde 5,00 €'), 'favoritos aplican precio de variante comprable');
    $pdo->prepare('UPDATE producto SET activo=0 WHERE id=?')->execute([$ids[0]]);
    $body = $returning->request('public/favoritos.php');
    check($cards($body) === 1 && str_contains($body, 'ya no están en el catálogo'), 'oculta producto retirado e informa');
    $returning->request('public/favoritos.php', ['action'=>'add','id_producto'=>(string)$ids[0]]);
    check($returning->status === 404, 'no guarda productos retirados');
    $pdo->prepare('UPDATE categoria SET activa=0 WHERE id=?')->execute([$category]);
    $body = $returning->request('public/favoritos.php');
    check($cards($body) === 0, 'oculta coleccion retirada');
    $pdo->prepare('UPDATE categoria SET activa=1 WHERE id=?')->execute([$category]);
    $pdo->prepare('UPDATE producto SET activo=1 WHERE id=?')->execute([$ids[0]]);
    $returning->request('public/favoritos.php', ['action'=>'remove','id_producto'=>(string)$ids[0]]);
    $body = $returning->request('public/favoritos.php');
    check($cards($body) === 1, 'quitar conserva el resto de favoritos');
    foreach (['https://example.invalid', '//example.invalid', 'catalogo.php' . "\r\n" . 'X-Evil: x', 'admin/login.php', 'catalogo.php#fragment', 'catalogo.php\\evil'] as $target) {
        $returning->request('public/favoritos.php', ['action'=>'add','id_producto'=>(string)$ids[1],'return'=>$target]);
        check($returning->status === 303 && $returning->headers['location'] === '/public/favoritos.php', 'retorno rechaza direccion ajena o malformada');
    }
    foreach ([['action'=>['add']], ['action'=>'add','id_producto'=>['1']], ['action'=>'add','id_producto'=>'1 OR 1=1'], ['action'=>'add','id_producto'=>'2147483648']] as $post) {
        $returning->request('public/favoritos.php', $post);
        check($returning->status === 422, 'rechaza accion o identificador invalido');
    }
    $setCookie($returning, implode(',', array_slice($ids,0,24)));
    $returning->request('public/favoritos.php', ['action'=>'add','id_producto'=>(string)$ids[24]]);
    check($returning->status === 409, 'limite de 24 explica como continuar');
    $body = $returning->request('public/favoritos.php?action=clear');
    check($cards($body) === 24, 'GET no vacia favoritos');
    foreach (['bad', '0', '-1', '2147483648', implode(',', $ids), str_repeat('1',300)] as $value) {
        $setCookie($returning, $value);
        $body = $returning->request('public/favoritos.php');
        check($returning->status === 200 && $cards($body) === 0, 'cookie malformada o excesiva no llega a la consulta');
    }
    $setCookie($returning, $ids[1] . ',' . $ids[1]);
    $body = $returning->request('public/favoritos.php');
    check($cards($body) === 1, 'normaliza duplicados en cookie');
    $returning->request('public/favoritos.php', ['action'=>'clear']);
    check($returning->status === 303 && str_contains($returning->headers['set-cookie'] ?? '', 'Max-Age=0'), 'vaciar elimina la cookie');
    $body = $returning->request('public/favoritos.php');
    check($cards($body) === 0, 'vaciar muestra estado inicial');
    echo "PASS $checks comprobaciones de favoritos\n";
} finally {
    foreach ($ids as $id) {
        $pdo->prepare('DELETE FROM producto_variante WHERE id_producto=?')->execute([$id]);
        $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$id]);
    }
    if ($category !== null) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$category]); }
    if ($brand !== null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
}
