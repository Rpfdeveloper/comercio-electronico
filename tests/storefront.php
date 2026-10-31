<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
$prefix = 'guide-' . bin2hex(random_bytes(5));
$products = []; $categories = []; $brand = null; $activeCategories = [];
$cards = static function (string $body): array {
    preg_match_all('/<article class="card catalog-product">(.*?)<\/article>/s', $body, $matches);
    return $matches[1];
};
$slugs = static function (string $body) use ($cards): array {
    $result = [];
    foreach ($cards($body) as $card) {
        preg_match('/producto.php\?p=([^"&]+)/', $card, $match);
        $result[] = $match[1];
    }
    return $result;
};
try {
    foreach ([1, 0] as $active) {
        $pdo->prepare('INSERT INTO categoria(nombre,slug,activa) VALUES (?,?,?)')
            ->execute(['Colección <b>prueba</b>', $prefix . '-cat-' . $active, $active]);
        $categories[] = (int)$pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute(['Marca de prueba', $prefix]);
    $brand = (int)$pdo->lastInsertId();
    $make = function (string $name, int $stock, int $active = 1, ?int $category = null, float $price = 20) use ($pdo, &$products, $categories, $prefix, $brand): array {
        $slug = $prefix . '-p-' . count($products);
        $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,descripcion_corta,descripcion_larga,precio,stock,activo,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$category ?? $categories[0], $brand, $name, $slug, 'Descripción breve',
                $prefix . ' floral con literal 100%_!', $price, $stock, $active, '2037-01-01 00:00:00']);
        $id = (int)$pdo->lastInsertId(); $products[] = $id;
        return ['id' => $id, 'slug' => $slug];
    };
    $hidden = $make('Oculto', 10, 0);
    $retired = $make('Categoría retirada', 10, 1, $categories[1]);
    $empty = $make('Agotado', 0);
    $mixed = $make('Variantes', 99, 1, null, 1);
    $pdo->prepare('INSERT INTO producto_variante(id_producto,capacidad_ml,precio,precio_oferta,stock,activo) VALUES (?,30,5,NULL,0,1),(?,100,65,45,3,1)')
        ->execute([$mixed['id'], $mixed['id']]);
    $basic = $make('Floral <script>fixture</script>', 5);
    $b = new TestBrowser();
    $home = $b->request('public/index.php');
    check($b->status === 200 && strpos($home, 'id="home-title"') !== false, 'portada responde con su titulo principal');
    check(strpos($home, 'Contacto próximamente') !== false && strpos($home, 'name="email"') === false, 'contacto pendiente sin formulario ficticio');
    check(strpos($home, 'href="#main-content"') !== false && strpos($home, 'id="main-content"') !== false, 'salto de teclado tiene destino');
    check(strpos($home, '&lt;b&gt;prueba&lt;/b&gt;') !== false, 'colecciones escapan nombres');
    check(strpos($home, $prefix . '-cat-0') === false, 'portada oculta colecciones retiradas');
    check(in_array($basic['slug'], $slugs($home), true) && in_array($mixed['slug'], $slugs($home), true), 'incorporaciones contienen productos disponibles');
    check(!array_intersect([$hidden['slug'], $retired['slug'], $empty['slug']], $slugs($home)), 'incorporaciones excluyen inactivos retirados y agotados');
    check(strpos($home, 'desde 45,00 €') !== false && strpos($home, '<script>fixture') === false, 'portada usa precio comprable y escapa productos');
    $body = $b->request('public/descubrir.php');
    check($b->status === 200 && !$cards($body), 'guia inicial no inventa recomendaciones');
    check(strpos($body, 'method="get"') !== false && strpos($body, 'no envía tus preferencias a un proveedor de IA') !== false, 'guia funciona sin JavaScript y explica su alcance');
    $path = 'public/descubrir.php?buscar=1&c=' . $prefix . '-cat-1';
    $body = $b->request($path . '&q=' . $prefix . '&max=40');
    check($b->status === 200 && $slugs($body) === [$basic['slug']], 'combina coleccion presupuesto y descripcion larga');
    $body = $b->request($path . '&max=45,00&stock=0&orden=precio-desc');
    check($slugs($body) === [$basic['slug'], $mixed['slug']], 'respeta stock orden y precio de variante pese a parametros ajenos');
    $body = $b->request($path . '&max=44.99');
    check($slugs($body) === [$basic['slug']], 'variante agotada barata no hace pasar el presupuesto');
    $body = $b->request($path . '&q=' . rawurlencode('100%_!'));
    check(count($cards($body)) === 2, 'busca signos literalmente en descripcion larga');
    $body = $b->request('public/catalogo.php?c=' . $prefix . '-cat-1&q=' . $prefix);
    check(count($cards($body)) === 3, 'catalogo general encuentra la descripcion larga incluido agotado');
    $body = $b->request($path . '&q=' . rawurlencode('"><script>alert(1)</script>'));
    check(strpos($body, '<script>alert') === false && strpos($body, '&lt;script&gt;') !== false, 'consulta reflejada escapada');
    $body = $b->request($path . '&q=' . rawurlencode("' OR 1=1 --"));
    check($b->status === 200 && !$cards($body), 'consulta SQL no altera filtros');
    $body = $b->request($path . '&max=0');
    check(!$cards($body) && strpos($body, 'No encontramos coincidencias disponibles') !== false, 'estado vacio permite cambiar preferencias');
    foreach (['q[]=x', 'max[]=x', 'max=-1', 'max=1e2', 'q=%FF', 'page=0'] as $query) {
        $body = $b->request($path . '&' . $query);
        check($b->status === 422 && strpos($body, 'role="alert"') !== false, 'rechaza filtro invalido ' . $query);
    }
    for ($i = 0; $i < 13; ++$i) { $make('Serie ' . $i, 1, 1, null, 50 + $i); }
    $body = $b->request($path . '&max=99&q=' . $prefix);
    check(count($cards($body)) === 12, 'guia pagina doce coincidencias');
    preg_match('/rel="next" href="([^"]+)"/', $body, $next);
    $nextPath = ltrim(html_entity_decode($next[1], ENT_QUOTES, 'UTF-8'), '/');
    $page2 = $b->request($nextPath);
    check(count($cards($page2)) === 3 && !array_intersect($slugs($body), $slugs($page2)), 'paginacion estable sin duplicados');
    check(strpos($nextPath, 'buscar=1') !== false && strpos($nextPath, 'max=99') !== false && strpos($nextPath, 'q=' . $prefix) !== false, 'paginacion conserva preferencias y accion');
    $b->request($path . '&page=99');
    check($b->status === 404, 'pagina inexistente devuelve 404');
    $pdo->prepare('UPDATE categoria SET activa=0 WHERE id=?')->execute([$categories[0]]);
    $body = $b->request($path);
    check($b->status === 404 && !$cards($body), 'coleccion retirada no produce coincidencias');
    $activeCategories = $pdo->query('SELECT id FROM categoria WHERE activa=1')->fetchAll(PDO::FETCH_COLUMN);
    $pdo->exec('UPDATE categoria SET activa=0 WHERE activa=1');
    $body = $b->request('public/index.php');
    check($b->status === 200 && strpos($body, 'Estamos preparando las colecciones') !== false && !$cards($body), 'portada vacia sigue siendo util');
    echo "RESULTADO: $checks comprobaciones de portada y descubrimiento.\n";
} finally {
    foreach ($activeCategories as $id) { $pdo->prepare('UPDATE categoria SET activa=1 WHERE id=?')->execute([$id]); }
    foreach ($products as $id) {
        $pdo->prepare('DELETE FROM producto_variante WHERE id_producto=?')->execute([$id]);
        $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$id]);
    }
    foreach ($categories as $id) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$id]); }
    if ($brand !== null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
}
