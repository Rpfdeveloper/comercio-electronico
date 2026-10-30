<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';

$prefix = 'catalog-' . bin2hex(random_bytes(5));
$products = []; $categories = []; $brands = [];
$makeProduct = function (string $name, float $price, int $stock = 5, int $active = 1, ?int $category = null, ?int $brand = null) use ($pdo, &$products, &$categories, &$brands, $prefix): array {
    $slug = $prefix . '-' . count($products);
    $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,descripcion_corta,precio,stock,activo) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$category ?? $categories[0], $brand ?? $brands[0], $name, $slug, 'Descripción cítrica de prueba', $price, $stock, $active]);
    $id = (int)$pdo->lastInsertId(); $products[] = $id;
    return ['id' => $id, 'slug' => $slug];
};
$variant = function (int $id, int $ml, float $price, int $stock, int $active = 1, ?float $offer = null) use ($pdo): int {
    $pdo->prepare('INSERT INTO producto_variante(id_producto,capacidad_ml,precio,precio_oferta,stock,activo) VALUES (?,?,?,?,?,?)')->execute([$id,$ml,$price,$offer,$stock,$active]);
    return (int)$pdo->lastInsertId();
};
$cards = function (string $body): array {
    preg_match_all('/<article class="card catalog-product">(.*?)<\/article>/s', $body, $matches);
    return $matches[1];
};
$slugs = function (string $body) use ($cards): array {
    $list = [];
    foreach ($cards($body) as $card) { preg_match('/producto.php\?p=([^"&]+)/', $card, $match); $list[] = $match[1]; }
    return $list;
};
try {
    foreach ([1, 0] as $active) {
        $pdo->prepare('INSERT INTO categoria(nombre,slug,activa) VALUES (?,?,?)')->execute(['Categoría de prueba', $prefix . '-cat-' . $active, $active]);
        $categories[] = (int)$pdo->lastInsertId();
    }
    foreach (['Ámbar & prueba', 'Segunda marca'] as $i => $name) {
        $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute([$name, $prefix . '-brand-' . $i]);
        $brands[] = (int)$pdo->lastInsertId();
    }
    $basic = [];
    for ($i = 1; $i <= 15; ++$i) { $basic[] = $makeProduct(sprintf('Serie %02d', $i), (float)$i); }
    $mixed = $makeProduct('Variantes mixtas', 1);
    $variant($mixed['id'], 30, 5, 0); // No anunciar este precio si no se puede comprar.
    $buyable = $variant($mixed['id'], 100, 90, 2, 1, 60);
    $variant($mixed['id'], 200, 1, 99, 0);
    $empty = $makeProduct('Agotado con variantes', 1, 99);
    $variant($empty['id'], 50, 40, 0);
    $disabled = $makeProduct('Solo variantes inactivas', 1, 99);
    $variant($disabled['id'], 50, 2, 99, 0);
    $singleEmpty = $makeProduct('Agotado sin variantes', 20, 0);
    $offer = $makeProduct('Oferta base', 30);
    $pdo->prepare('UPDATE producto SET precio_oferta=12.50 WHERE id=?')->execute([$offer['id']]);
    $hidden = $makeProduct('Oculto inactivo', 1, 5, 0);
    $hiddenCategory = $makeProduct('Oculto por categoría', 1, 5, 1, $categories[1]);
    $literal = $makeProduct('Literal 100%_! <script>fixture</script>', 9, 1, 1, null, $brands[1]);
    $b = new TestBrowser();
    $cat = $prefix . '-cat-1';
    $path = 'public/catalogo.php?c=' . $cat;
    $body = $b->request($path);
    check($b->status === 200 && count($cards($body)) === 12 && strpos($body, '<strong>21</strong>') !== false, 'catalogo cuenta productos visibles y muestra doce por pagina');
    $first = $slugs($body);
    $body2 = $b->request($path . '&page=2');
    check(count($cards($body2)) === 9 && count(array_intersect($first, $slugs($body2))) === 0, 'segunda pagina sin productos repetidos');
    check(!in_array($hidden['slug'], array_merge($first, $slugs($body2)), true) && !in_array($hiddenCategory['slug'], array_merge($first, $slugs($body2)), true), 'catalogo excluye productos y categorias inactivos');
    $body = $b->request($path . '&q=Serie&orden=nombre');
    check($slugs($body) === array_column(array_slice($basic, 0, 12), 'slug'), 'busqueda y orden alfabetico estables');
    preg_match('/rel="next" href="([^"]+)"/', $body, $next);
    $nextPath = ltrim(html_entity_decode($next[1], ENT_QUOTES, 'UTF-8'), '/');
    $body = $b->request($nextPath);
    check($slugs($body) === array_column(array_slice($basic, 12), 'slug') && strpos($nextPath, 'q=Serie') !== false && strpos($nextPath, 'c='.$cat) !== false, 'enlace siguiente conserva busqueda y categoria');
    $body = $b->request($path . '&q=Serie&orden=precio-desc&min=3&max=14&stock=1');
    check($slugs($body) === array_column(array_reverse(array_slice($basic, 2, 12)), 'slug'), 'precio descendente y limites inclusivos');
    $body = $b->request($path . '&q=Serie&orden=precio-asc&min=3&max=5');
    check($slugs($body) === array_column(array_slice($basic, 2, 3), 'slug'), 'precio ascendente y rango combinado');
    $body = $b->request($path . '&q=Serie&orden=recientes');
    check($slugs($body) === array_column(array_slice(array_reverse($basic), 0, 12), 'slug'), 'orden reciente desempata por identificador');
    $body = $b->request($path . '&marca=' . $prefix . '-brand-1');
    check($slugs($body) === [$literal['slug']], 'filtro por marca');
    check(strpos($body, '&lt;script&gt;fixture&lt;/script&gt;') !== false && strpos($body, '<script>fixture') === false, 'nombres del catalogo escapados');
    $body = $b->request($path . '&q=' . rawurlencode('100%_!'));
    check($slugs($body) === [$literal['slug']], 'porcentaje guion bajo y escape se buscan literalmente');
    $body = $b->request($path . '&q=AMBAR');
    check(strpos($body, '<strong>20</strong>') !== false, 'busqueda de marca sin distinguir mayusculas ni acentos');
    $body = $b->request($path . '&q=citrica');
    check(strpos($body, '<strong>21</strong>') !== false, 'busqueda por descripcion');
    $body = $b->request($path . '&q=' . rawurlencode("' OR 1=1 --"));
    check($b->status === 200 && !$cards($body), 'texto SQL se trata como busqueda sin resultados');
    $body = $b->request($path . '&q=' . rawurlencode('"><script>alert(1)</script>'));
    check($b->status === 200 && strpos($body, '<script>alert') === false && strpos($body, '&lt;script&gt;') !== false, 'busqueda reflejada escapada');
    $body = $b->request($path . '&q=inexistente');
    check($b->status === 200 && strpos($body, 'No hay perfumes con estos filtros') !== false && strpos($body, 'rel="next"') === false, 'estado vacio sin paginacion fantasma');
    $body = $b->request($path . '&q=Variantes%20mixtas');
    check(count($cards($body)) === 1 && strpos($cards($body)[0], '60,00 €') !== false, 'precio desde usa oferta de variante con stock');
    $body = $b->request($path . '&q=Variantes%20mixtas&max=59.99');
    check(!$cards($body), 'precio de variante agotada no hace pasar el filtro');
    $body = $b->request($path . '&q=Variantes%20mixtas&min=60&max=60&stock=1');
    check(count($cards($body)) === 1, 'filtros usan el mismo precio comprable que la tarjeta');
    $body = $b->request($path . '&q=Oferta%20base&min=12,50&max=12,50');
    check(count($cards($body)) === 1 && strpos($cards($body)[0], '12,50 €') !== false, 'oferta base y coma decimal');
    $body = $b->request($path . '&q=Agotado&stock=1');
    check(!$cards($body), 'disponibilidad excluye agotados con y sin variantes');
    $body = $b->request($path . '&q=Solo%20variantes');
    check(count($cards($body)) === 1 && strpos($cards($body)[0], 'Agotado') !== false && strpos($cards($body)[0], 'desde') === false, 'variantes inactivas no reutilizan precio ni stock base');
    $body = $b->request($path . '&q=Agotado%20con');
    check(strpos($cards($body)[0], '40,00 €') !== false && strpos($cards($body)[0], 'Agotado') !== false, 'agotado muestra precio activo y etiqueta');
    $body = $b->request('public/categoria.php?c=' . $cat . '&q=Serie');
    check($b->status === 200 && count($cards($body)) === 12 && strpos($body, 'categoria.php?') !== false, 'URL de categoria conserva filtros y paginacion');
    foreach (['c='.$prefix.'-cat-0', 'c=inexistente', 'marca=inexistente', 'c='.$cat.'&page=3'] as $query) {
        $b->request('public/catalogo.php?' . $query);
        check($b->status === 404, 'filtro o pagina inexistente devuelve 404: '.$query);
    }
    foreach (['q[]=x', 'c[]=x', 'marca[]=x', 'min[]=0', 'max[]=0', 'stock[]=1', 'orden[]=x', 'page[]=1', 'page=-1', 'page=0', 'page=1000001', 'min=-1', 'max=1e2', 'min=0.001', 'min=10&max=1', 'stock=2', 'orden=DROP', 'q=%FF', 'q=%00', 'q='.str_repeat('a',101)] as $query) {
        $b->request('public/catalogo.php?' . $query);
        check($b->status === 422, 'entrada de filtro invalida rechazada: '.$query);
    }
    $b->request('public/categoria.php');
    check($b->status === 404, 'categoria sin identificador devuelve 404 antes del HTML');
    foreach ([$hidden['slug'], $hiddenCategory['slug'], 'inexistente'] as $slug) {
        $b->request('public/producto.php?p='.$slug);
        check($b->status === 404, 'ficha no revela productos ocultos o inexistentes');
    }
    $b->request('public/producto.php?p[]=x');
    check($b->status === 404, 'ficha rechaza identificador no escalar');
    $body = $b->request('public/producto.php?p='.$mixed['slug']);
    check($b->status === 200 && preg_match('/<select[^>]*>\s*<option value="'.$buyable.'"/', $body) === 1, 'ficha selecciona primero la capacidad comprable mas barata');
    check(strpos($body, 'disabled>30 ml') !== false && strpos($body, '200 ml') === false, 'ficha deshabilita agotadas y oculta capacidades inactivas');
    $b->request('public/carrito.php', ['action'=>'add','id_producto'=>$mixed['id'],'id_variante'=>$buyable,'cantidad'=>1]);
    $body = $b->request('public/carrito.php');
    check($b->status === 200 && (strpos($body, '60.00') !== false || strpos($body, '60,00') !== false), 'carrito cobra el precio anunciado de la variante');
    foreach ([$empty, $disabled, $singleEmpty] as $product) {
        $body = $b->request('public/producto.php?p='.$product['slug']);
        check(strpos($body, '<button type="submit" disabled>') !== false && strpos($body, 'Agotado.') !== false, 'ficha agotada bloquea boton de compra');
    }
    echo "PASS $checks comprobaciones de catalogo\n";
} finally {
    foreach ($products as $id) { $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$id]); }
    foreach ($categories as $id) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$id]); }
    foreach ($brands as $id) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$id]); }
}
