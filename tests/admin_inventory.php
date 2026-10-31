<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
$password = getenv('TIENDA_TEST_ADMIN_PASS') ?: '';
if ($password === '') { throw new RuntimeException('Define TIENDA_TEST_ADMIN_PASS'); }
$marker = 'inventario-' . bin2hex(random_bytes(5));
$products = []; $categories = []; $brand = null; $variants = [];
$references = static function (string $html): array {
    preg_match_all('/data-reference="([0-9]+-[0-9]+)"/', $html, $matches);
    return $matches[1];
};
$summary = static function (string $html, string $label): string {
    preg_match('~<span>' . preg_quote($label, '~') . '</span><strong>(.*?)</strong>~s', $html, $match);
    return html_entity_decode($match[1] ?? '', ENT_QUOTES, 'UTF-8');
};
try {
    $pdo->exec('DELETE FROM acceso_intento');
    foreach ([1,0] as $active) {
        $pdo->prepare('INSERT INTO categoria(nombre,slug,activa) VALUES (?,?,?)')->execute(['Categoría de prueba', $marker . '-cat-' . $active, $active]);
        $categories[$active] = (int)$pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute([$marker . ' marca', $marker]);
    $brand = (int)$pdo->lastInsertId();
    $insert = $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,precio,precio_oferta,stock,activo) VALUES (?,?,?,?,?,?,?,?)');
    for ($i=0; $i<30; ++$i) {
        $insert->execute([$categories[$i === 3 ? 0 : 1], $brand, $marker . ' ' . sprintf('%02d',$i), $marker . '-' . $i, 50, $i === 1 ? 40 : null, $i === 0 ? 999 : $i, $i === 2 ? 0 : 1]);
        $products[] = (int)$pdo->lastInsertId();
    }
    $insertVariant = $pdo->prepare('INSERT INTO producto_variante(id_producto,capacidad_ml,sku,precio,precio_oferta,stock,activo) VALUES (?,?,?,?,?,?,?)');
    foreach ([[0,1,25],[3,1,null],[null,1,null],[-2,1,null],[12,0,null],[6,null,null]] as $i=>[$stock,$active,$offer]) {
        $insertVariant->execute([$products[0], 30+$i*10, $marker . '-SKU-' . $i, 60, $offer, $stock, $active]);
        $variants[] = (int)$pdo->lastInsertId();
    }
    $pdo->prepare('UPDATE producto SET nombre=? WHERE id=?')->execute([$marker . ' 29 100%_! <script>perfume</script>', $products[29]]);
    $ref = static fn(int $p, int $v=0): string => $products[$p] . '-' . ($v ? $variants[$v-1] : 0);
    $before = $pdo->query('SELECT id,stock,activo FROM producto ORDER BY id')->fetchAll();
    $beforeVariants = $pdo->query('SELECT id,stock,activo FROM producto_variante ORDER BY id')->fetchAll();
    $b = new TestBrowser();
    $b->request('admin/inventario.php?q=' . $marker);
    check($b->status === 302 && ($b->headers['location'] ?? '') === 'login.php', 'inventario exige sesión administrativa');
    $b->request('admin/login.php');
    $b->request('admin/login.php', ['user'=>'admin','pass'=>$password]);
    check($b->status === 302, 'acceso administrativo válido');
    $request = static fn(array $filters=[]): string => $b->request('admin/inventario.php?' . http_build_query($filters + ['q'=>$marker]));
    $html = $request();
    $first = $references($html);
    check($b->status === 200 && count($first) === 25, 'inventario pagina 25 referencias');
    check($summary($html,'Referencias encontradas') === '35', 'cuenta variantes y productos sin duplicar base');
    check($summary($html,'Unidades registradas') === '456', 'suma stock positivo excluyendo base, nulos y negativos');
    check($summary($html,'Stock bajo · 1 a 5') === '6' && $summary($html,'Referencias agotadas') === '1', 'resumen distingue bajo y agotado');
    check(array_slice($first,0,3) === [$ref(0,3),$ref(0,4),$ref(0,1)], 'menor stock prioriza datos sin registrar y negativos');
    check(strpos($html,'Sin dato') !== false && strpos($html,'2 referencias tienen stock') !== false, 'datos anómalos visibles sin convertirlos en cero');
    check(strpos($b->headers['cache-control'] ?? '', 'no-store') !== false && strpos($b->headers['x-robots-tag'] ?? '', 'noindex') !== false, 'inventario privado sin caché ni indexación');
    check(($b->headers['referrer-policy'] ?? '') === 'no-referrer', 'búsquedas no se transmiten por Referer');
    check(strpos($html,'href="inventario.php" aria-current="page"') !== false, 'navegación identifica sección activa');
    preg_match('/rel="next" href="([^"]+)"/', $html, $nextMatch);
    $next = html_entity_decode($nextMatch[1] ?? '', ENT_QUOTES, 'UTF-8');
    check(strpos($next,'q='.$marker) !== false && strpos($next,'pagina=2') !== false, 'paginación conserva búsqueda');
    $second = $references($b->request('admin/'.$next));
    check(count($second) === 10 && !array_intersect($first,$second), 'segunda página sin repetir referencias');
    check(!in_array($ref(0),array_merge($first,$second),true), 'producto con variantes nunca aparece como base');
    check($references($request(['pagina'=>'1000000'])) === $second, 'página excesiva se ajusta a última');
    check($references($request(['stock'=>'agotado'])) === [$ref(0,1)], 'agotado solo incluye cero');
    check(count($references($request(['stock'=>'bajo']))) === 6, 'stock bajo de uno a cinco inclusive');
    check(count($references($request(['stock'=>'disponible']))) === 25, 'más de cinco unidades disponibles');
    check($references($request(['stock'=>'revision'])) === [$ref(0,3),$ref(0,4)], 'revisión incluye nulos y negativos');
    check(count($references($request(['visibilidad'=>'inactiva']))) === 4, 'inactivos por producto categoría o variante incluso activo nulo');
    $html = $request(['visibilidad'=>'activa']);
    check($summary($html,'Referencias encontradas') === '31', 'activa exige producto categoría y variante activos');
    check($references($request(['stock'=>'bajo','visibilidad'=>'inactiva'])) === [$ref(2),$ref(3)], 'filtros de visibilidad y stock combinados');
    $html = $request(['q'=>$marker . '-SKU-0']);
    check($references($html) === [$ref(0,1)] && strpos($html,'25,00 €') !== false, 'SKU exacto y oferta positiva de variante');
    $html = $request(['q'=>$marker . ' 01']);
    check($references($html) === [$ref(1)] && strpos($html,'40,00 €') !== false && strpos($html,'Formato base') !== false, 'precio y oferta del producto base');
    $html = $request(['q'=>$marker . '-SKU-1']);
    check(strpos($html,'60,00 €') !== false && strpos($html,'40 ml') !== false, 'precio propio y capacidad de variante');
    $html = $request(['q'=>'100%_!']);
    check($references($html) === [$ref(29)], 'porcentaje guion bajo y escape son literales');
    check(strpos($html,'&lt;script&gt;perfume&lt;/script&gt;') !== false && strpos($html,'<script>perfume') === false, 'escapa nombres del catálogo');
    check(count($references($request(['q'=>$marker . ' marca']))) === 25, 'búsqueda por marca');
    $html = $request(['q'=>"' OR 1=1 --"]);
    check($b->status === 200 && !$references($html), 'inyección SQL no amplía resultados');
    $html = $request(['q'=>'<img src=x onerror=alert(1)>']);
    check(strpos($html,'<img src=x') === false && strpos($html,'&lt;img src=x') !== false, 'búsqueda reflejada escapada');
    $html = $request(['q'=>$marker.'-inexistente']);
    check(!$references($html) && strpos($html,'No hay referencias con estos filtros.') !== false && $summary($html,'Unidades registradas') === '0', 'estado vacío con resumen cero');
    $expectedName = array_merge(array_map(static fn($v)=>$products[0].'-'.$v,$variants),array_map(static fn($p)=>$p.'-0',array_slice($products,1,19)));
    check($references($request(['orden'=>'nombre'])) === $expectedName, 'orden por nombre estable entre variantes');
    check(array_slice($references($request(['orden'=>'stock_mayor'])),0,3) === [$ref(29),$ref(28),$ref(27)], 'mayor stock primero');
    foreach (array_keys(['q'=>0,'stock'=>0,'visibilidad'=>0,'orden'=>0,'pagina'=>0]) as $key) {
        $html = $request([$key=>['x']]);
        check($b->status === 422 && !$references($html), 'rechaza filtro no escalar '.$key);
    }
    foreach ([['stock'=>'invalid'],['visibilidad'=>'invalid'],['orden'=>'stock;DROP TABLE producto'],['pagina'=>'0'],['pagina'=>'-1'],['pagina'=>'1.5'],['pagina'=>'1000001'],['q'=>str_repeat('x',161)],['q'=>"x\0y"],['q'=>"\xFF"]] as $invalid) {
        $html = $request($invalid);
        check($b->status === 422 && !$references($html), 'rechaza filtro inválido '.array_key_first($invalid));
    }
    $pdo->prepare('UPDATE producto_variante SET activo=0 WHERE id_producto=?')->execute([$products[0]]);
    $html = $request(['q'=>$marker.'-SKU','visibilidad'=>'inactiva']);
    check(count($references($html)) === 6 && !in_array($ref(0),$references($html),true), 'todas las variantes inactivas no reactivan base');
    foreach ($beforeVariants as $row) { $pdo->prepare('UPDATE producto_variante SET activo=? WHERE id=?')->execute([$row['activo'],$row['id']]); }
    check($before === $pdo->query('SELECT id,stock,activo FROM producto ORDER BY id')->fetchAll() &&
        $beforeVariants === $pdo->query('SELECT id,stock,activo FROM producto_variante ORDER BY id')->fetchAll(), 'consulta conserva stock y activación');
    echo "PASS $checks comprobaciones de inventario\n";
} finally {
    foreach ($products as $id) {
        $pdo->prepare('DELETE FROM producto_variante WHERE id_producto=?')->execute([$id]);
        $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$id]);
    }
    if ($brand !== null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
    foreach ($categories as $id) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$id]); }
    $pdo->exec('DELETE FROM acceso_intento');
}
