<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
$slugs = ['bruma-de-higo-edp', 'te-blanco-lumen-edt', 'ambar-nomada-edp', 'citrico-cobalto-edt', 'iris-de-arena-edp', 'musgo-polar-edt', 'lavanda-ahumada-edp', 'lluvia-de-jengibre-edt'];
$count = static fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();
$category = $pdo->query("SELECT id, activa FROM categoria WHERE slug='unisex'")->fetch();
check($category !== false && (int)$category['activa'] === 1, 'la coleccion Unisex existe y esta visible');
$st = $pdo->prepare('SELECT p.slug, p.activo, p.imagen_principal, COUNT(v.id) AS formatos, SUM(v.stock) AS unidades FROM producto p LEFT JOIN producto_variante v ON v.id_producto=p.id AND v.activo=1 WHERE p.id_categoria=? GROUP BY p.id ORDER BY p.slug');
$st->execute([$category['id']]);
$rows = array_column($st->fetchAll(), null, 'slug');
$sorted = $slugs; sort($sorted);
check(array_keys($rows) === $sorted, 'la coleccion contiene los ocho perfumes previstos');
foreach ($rows as $slug => $row) {
    $file = __DIR__ . '/../tienda/public/' . $row['imagen_principal'];
    if ((int)$row['activo'] !== 1 || (int)$row['formatos'] !== 2 || (int)$row['unidades'] !== 24 || !is_file($file) || getimagesize($file)[0] !== 1200) {
        throw new RuntimeException('Perfume Unisex incompleto: ' . $slug);
    }
}
check(true, 'cada perfume esta activo, con dos formatos, stock e imagen existente');
$before = [$count('SELECT COUNT(*) FROM producto'), $count('SELECT COUNT(*) FROM producto_variante'), $count('SELECT COUNT(*) FROM categoria')];
$pdo->exec(file_get_contents(__DIR__ . '/../tienda/sql/migrations/009_unisex_catalog.sql'));
check($before === [$count('SELECT COUNT(*) FROM producto'), $count('SELECT COUNT(*) FROM producto_variante'), $count('SELECT COUNT(*) FROM categoria')], 'repetir la migracion no duplica perfumes, formatos ni colecciones');
$b = new TestBrowser();
$html = $b->request('public/categoria.php?c=unisex');
$found = 0; foreach ($slugs as $slug) { if (strpos($html, 'producto.php?p=' . $slug) !== false) { ++$found; } }
check($b->status === 200 && $found === 8, 'la pagina de la coleccion muestra los ocho perfumes');
$html = $b->request('public/producto.php?p=ambar-nomada-edp');
check($b->status === 200 && strpos($html, 'Ámbar Nómada EDP') !== false && strpos($html, '82,90 €') !== false && strpos($html, 'img/products/Unisex/ambar-nomada-edp.jpg') !== false, 'ficha con tildes, precio de oferta del formato grande e imagen');
$b->request('public/img/products/Unisex/ambar-nomada-edp.jpg');
check($b->status === 200 && strpos($b->headers['content-type'] ?? '', 'image/jpeg') === 0, 'la imagen se sirve correctamente');
echo "PASS $checks comprobaciones de la coleccion Unisex\n";
