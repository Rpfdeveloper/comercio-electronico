<?php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/xml; charset=UTF-8');
if (!seo_ready()) {
    http_response_code(503);
    echo '<?xml version="1.0" encoding="UTF-8"?><error>Mapa desactivado durante la preparación de la tienda.</error>';
    exit;
}
$paths = ['index.php', 'descubrir.php', 'ayuda.php'];
$categories = db()->query('SELECT c.slug, COUNT(p.id) AS total FROM categoria c
    JOIN producto p ON p.id_categoria=c.id AND p.activo=1 WHERE c.activa=1 GROUP BY c.id,c.slug ORDER BY c.id')->fetchAll();
$total = array_sum(array_column($categories, 'total'));
if ($total > 0) { $paths[] = 'catalogo.php'; }
// Una instalación que supere el límite necesita un índice de sitemaps dividido.
if ($total + count($categories) + 4 > 45000) { http_response_code(503); echo '<error>Dividir el sitemap antes de activarlo.</error>'; exit; }
$pages = max(1, (int)ceil($total / 12));
for ($page = 2; $page <= $pages; ++$page) { $paths[] = 'catalogo.php?page=' . $page; }
foreach ($categories as $category) {
    for ($page = 1; $page <= (int)ceil($category['total'] / 12); ++$page) {
        $paths[] = 'categoria.php?c=' . rawurlencode($category['slug']) . ($page > 1 ? '&page=' . $page : '');
    }
}
$products = db()->query('SELECT p.slug FROM producto p JOIN categoria c ON c.id=p.id_categoria
    WHERE p.activo=1 AND c.activa=1 ORDER BY p.id');
foreach ($products as $product) { $paths[] = 'producto.php?p=' . rawurlencode($product['slug']); }
if (count($paths) > 50000) { http_response_code(503); echo '<error>Dividir el sitemap antes de activarlo.</error>'; exit; }
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($paths as $path) { echo '<url><loc>' . htmlspecialchars(seo_absolute($path), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>'; }
echo '</urlset>';
