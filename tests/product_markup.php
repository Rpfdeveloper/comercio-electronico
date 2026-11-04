<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
require_once __DIR__ . '/../tienda/src/lib/helpers.php';
require_once __DIR__ . '/../tienda/src/lib/seo.php';

$marker = 'marcado-' . bin2hex(random_bytes(4));
$category = null; $brand = null; $products = [];
$extract = static function (string $html): ?array {
    if (preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m) !== 1) { return null; }
    return json_decode($m[1][0], true, 32, JSON_THROW_ON_ERROR);
};
$original = getenv('TIENDA_PUBLIC_URL');
try {
    // Unidad: escape, ofertas y URL absolutas solo con una dirección pública configurada.
    putenv('TIENDA_PUBLIC_URL');
    $hostile = 'Noir </script><script>alert(1)</script> & "x"';
    $product = ['nombre'=>$hostile, 'slug'=>'noir', 'descripcion_larga'=>'Ámbar <b>y</b> vainilla', 'descripcion_corta'=>'', 'marca'=>'Maison <i>', 'categoria'=>'Women Perfumes',
        'variantes'=>2, 'precio_base'=>'10.00', 'stock'=>0, 'imagen_principal'=>'img/products/Mujer/noir.jpg'];
    $formats = [['capacidad_ml'=>50, 'precio'=>'45.5', 'stock'=>3, 'sku'=>'NOIR-50'], ['capacidad_ml'=>100, 'precio'=>'70', 'stock'=>0, 'sku'=>null]];
    $json = seo_product_jsonld($product, $formats);
    check(strpos($json, '<') === false && strpos($json, '>') === false && strpos($json, '&') === false && stripos($json, '</script') === false, 'marcado no puede cerrar la etiqueta script ni inyectar HTML');
    $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    check($data['@type'] === 'Product' && $data['name'] === $hostile && $data['brand']['name'] === 'Maison <i>' && $data['category'] === 'Mujer', 'textos originales se conservan al decodificar');
    check($data['description'] === 'Ámbar y vainilla', 'descripcion sin etiquetas y con Unicode legible');
    check(count($data['offers']) === 2 && $data['offers'][0]['price'] === '45.50' && $data['offers'][0]['priceCurrency'] === 'EUR' && $data['offers'][0]['sku'] === 'NOIR-50' && $data['offers'][0]['name'] === '50 ml', 'una oferta por formato con precio decimal y SKU');
    check($data['offers'][0]['availability'] === 'https://schema.org/InStock' && $data['offers'][1]['availability'] === 'https://schema.org/OutOfStock' && !isset($data['offers'][1]['sku']), 'disponibilidad por formato y SKU solo si existe');
    check($data['offers'][0]['priceSpecification']['valueAddedTaxIncluded'] === false, 'precio declarado sin IVA, como en la ficha');
    check(!isset($data['url']) && !isset($data['image']) && !isset($data['offers'][0]['url']), 'sin direccion publica no se inventan URL');
    putenv('TIENDA_PUBLIC_URL=https://shop.example.invalid/tienda/public');
    $data = json_decode(seo_product_jsonld($product, $formats), true);
    check($data['url'] === 'https://shop.example.invalid/tienda/public/producto.php?p=noir' && $data['image'] === 'https://shop.example.invalid/tienda/public/img/products/Mujer/noir.jpg' && $data['offers'][1]['url'] === $data['url'], 'con direccion configurada, URL e imagen absolutas');
    $single = json_decode(seo_product_jsonld(['variantes'=>0, 'precio_base'=>'19.9', 'stock'=>4, 'imagen_principal'=>null] + $product, []), true);
    check(isset($single['offers']['@type']) && $single['offers']['price'] === '19.90' && !isset($single['offers']['name']) && !isset($single['image']), 'formato unico publica una oferta sin imagen inexistente');
    $none = json_decode(seo_product_jsonld($product, []), true);
    check(!isset($none['offers']), 'perfume con formatos pero ninguno activo no publica ofertas');
    putenv($original === false ? 'TIENDA_PUBLIC_URL' : 'TIENDA_PUBLIC_URL=' . $original);

    // HTTP: la ficha real publica el marcado coherente con lo que se muestra.
    $pdo->prepare('INSERT INTO categoria(nombre,slug,activa) VALUES (?,?,1)')->execute(['Colección marcado', $marker]);
    $category = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute(['Marca <b>marcado</b>', $marker]);
    $brand = (int)$pdo->lastInsertId();
    $insert = $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,descripcion_corta,precio,precio_oferta,stock,activo) VALUES (?,?,?,?,?,?,?,?,1)');
    $insert->execute([$category, $brand, $marker . ' </script><b>formatos', $marker . '-formatos', 'Breve', 10, null, 0]);
    $products['formatos'] = (int)$pdo->lastInsertId();
    $insert->execute([$category, $brand, $marker . ' unico', $marker . '-unico', 'Breve', 30, 24.5, 6]);
    $products['unico'] = (int)$pdo->lastInsertId();
    $variant = $pdo->prepare('INSERT INTO producto_variante(id_producto,capacidad_ml,sku,precio,precio_oferta,stock,activo) VALUES (?,?,?,?,?,?,?)');
    $variant->execute([$products['formatos'], 30, $marker . '-30', 40, 35, 2, 1]);
    $variant->execute([$products['formatos'], 75, $marker . '-75', 60, null, 0, 1]);
    $variant->execute([$products['formatos'], 200, $marker . '-200', 90, null, 9, 0]);
    $b = new TestBrowser();
    $html = $b->request('public/producto.php?p=' . $marker . '-formatos');
    $data = $extract($html);
    check($b->status === 200 && $data !== null && $data['@type'] === 'Product', 'ficha incluye un unico bloque de marcado de producto');
    check(strpos($html, '</script><b>formatos') === false && $data['name'] === $marker . ' </script><b>formatos', 'nombre hostil escapado en la pagina y exacto en los datos');
    $prices = array_column($data['offers'], 'price', 'name');
    check($prices === ['30 ml'=>'35.00', '75 ml'=>'60.00'], 'ofertas coinciden con los formatos activos y precios de oferta mostrados');
    check(strpos($html, '35,00 €') !== false && $data['offers'][1]['availability'] === 'https://schema.org/OutOfStock', 'precio y agotado coherentes con la ficha visible');
    check(!in_array($marker . '-200', array_column($data['offers'], 'sku'), true), 'formato inactivo no se publica');
    $data = $extract($b->request('public/producto.php?p=' . $marker . '-unico'));
    check($data['offers']['price'] === '24.50' && $data['offers']['availability'] === 'https://schema.org/InStock' && !isset($data['url']), 'formato unico usa el precio de oferta; sin URL en el prototipo');
    $html = $b->request('public/producto.php?p=' . $marker . '-inexistente');
    check($b->status === 404 && strpos($html, 'application/ld+json') === false, 'pagina no encontrada sin marcado');
    $pdo->prepare('UPDATE categoria SET activa=0 WHERE id=?')->execute([$category]);
    $html = $b->request('public/producto.php?p=' . $marker . '-unico');
    check($b->status === 404 && strpos($html, 'application/ld+json') === false, 'perfume oculto no publica marcado');
    $html = $b->request('public/catalogo.php');
    check(strpos($html, 'application/ld+json') === false, 'el catalogo no publica marcado de producto');
    echo "PASS $checks comprobaciones de marcado de producto\n";
} finally {
    putenv($original === false ? 'TIENDA_PUBLIC_URL' : 'TIENDA_PUBLIC_URL=' . $original);
    foreach ($products as $id) {
        $pdo->prepare('DELETE FROM producto_variante WHERE id_producto=?')->execute([$id]);
        $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$id]);
    }
    if ($brand !== null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
    if ($category !== null) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$category]); }
}
