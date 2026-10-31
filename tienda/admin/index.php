<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php'; admin_require();
require_once __DIR__ . '/../src/lib/admin_inventory.php';
$summary=null; $unavailable=false;
try {
    [$filters] = admin_inventory_filters([]);
    $inventory=admin_inventory_search(db(),$filters)['totals'];
    $products=db()->query('SELECT COUNT(*) AS total,COALESCE(SUM(activo=0),0) AS borradores FROM producto')->fetch();
    $orders=db()->query("SELECT COUNT(*) FROM pedido p JOIN estado_pedido e ON e.id=p.estado WHERE e.descripcion='pending'")->fetchColumn();
    $summary=['inventory'=>$inventory,'products'=>$products,'pending'=>(int)$orders];
} catch (PDOException $e) { http_response_code(503);$unavailable=true; }
admin_page_start('Resumen de la tienda',true,'resumen');
?>
<p class="eyebrow">Operaciones / Resumen</p><h1>Tu tienda, de un vistazo.</h1><p class="lede">Revisa qué necesita atención y continúa con la gestión diaria.</p>
<?php if ($unavailable): ?><p class="notice" role="alert">No podemos cargar el resumen en este momento. Inténtalo más tarde.</p><?php endif; ?>
<?php if ($summary): ?>
<section class="stats" aria-label="Resumen de operaciones">
<div class="stat highlight"><span>Perfumes en catálogo</span><strong><?= (int)$products['total'] ?></strong><a href="catalogo.php">Gestionar catálogo →</a></div>
<div class="stat"><span>Pedidos pendientes</span><strong><?= (int)$orders ?></strong><a href="pedidos.php">Revisar pedidos →</a></div>
<div class="stat"><span>Referencias con stock bajo</span><strong><?= (int)$inventory['bajo'] ?></strong><a href="inventario.php?stock=bajo">Ver stock bajo →</a></div>
<div class="stat"><span>Referencias agotadas</span><strong><?= (int)$inventory['agotado'] ?></strong><a href="inventario.php?stock=agotado">Preparar reposición →</a></div>
</section>
<div class="detail-grid"><section class="panel"><h2>Continúa con el catálogo</h2><p><?= (int)$products['borradores'] ?> perfumes están inactivos. Revisa su ficha, imagen y formatos antes de activarlos.</p><div class="editor-actions"><a class="button" href="producto.php">+ Nuevo perfume</a><a href="catalogo.php?visibilidad=inactiva">Revisar no visibles</a></div></section>
<section class="panel"><h2>Calidad del inventario</h2><p><?= (int)$inventory['revision'] ?> referencias tienen existencias ausentes o negativas. Los resúmenes no las convierten en unidades disponibles.</p><a href="inventario.php?stock=revision">Revisar datos de stock →</a></section></div>
<p class="muted inventory-note">El inventario incluye referencias activas e inactivas. Los pedidos pendientes y sus importes no acreditan pagos. La tienda continúa siendo un prototipo sin pasarela de pago.</p>
<?php endif; admin_page_end(); ?>
