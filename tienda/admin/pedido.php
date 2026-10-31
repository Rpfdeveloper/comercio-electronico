<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
admin_require();
require_once __DIR__ . '/../src/lib/admin_orders.php';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if ($id === false || $id === null) { http_response_code(404); exit('Pedido no encontrado.'); }
[$filters, $errors] = admin_orders_filters($_GET);
$backUrl = $errors ? 'pedidos.php' : admin_orders_url($filters);
$st = db()->prepare('SELECT p.*,e.descripcion AS estado_texto FROM pedido p JOIN estado_pedido e ON e.id=p.estado WHERE p.id = ?');
$st->execute([$id]);
$pedido = $st->fetch();
if (!$pedido) { http_response_code(404); exit('Pedido no encontrado.'); }
$st = db()->prepare('SELECT * FROM linea_pedido WHERE id_pedido = ? ORDER BY id');
$st->execute([$id]);
$lineas = $st->fetchAll();
[$stateLabel, $stateClass] = admin_order_state($pedido['estado_texto']);
admin_page_start('Pedido #' . $id);
?>
<p class="eyebrow">Operaciones / Detalle</p>
<a href="<?= h($backUrl) ?>">← Volver al listado</a>
<div class="detail-heading"><h1>Pedido #<?= (int)$id ?></h1><span class="badge <?= $stateClass ?>"><?= h($stateLabel) ?></span></div>
<p class="muted">Registrado el <?= h($pedido['fecha_pedido']) ?>. El estado no confirma un cobro.</p>
<div class="detail-grid">
<section class="panel" aria-labelledby="customer-title"><h2 id="customer-title">Cliente y envío</h2>
<address class="address"><strong><?= h($pedido['nombre_envio']) ?></strong><br><?= h($pedido['email']) ?><br><br>
<?= h($pedido['direccion_envio']) ?><br><?= h($pedido['cp_envio']) ?> <?= h($pedido['poblacion_envio']) ?><br>
<?php if (!empty($pedido['provincia_envio'])): ?><?= h($pedido['provincia_envio']) ?><br><?php endif; ?><?= h($pedido['pais_envio']) ?></address>
</section>
<section class="panel" aria-labelledby="total-title"><h2 id="total-title">Resumen del pedido</h2><dl class="totals">
<div><dt>Productos</dt><dd><?= h(money_eur((float)$pedido['importe_productos'])) ?></dd></div>
<div><dt>IVA</dt><dd><?= h(money_eur((float)$pedido['impuestos'])) ?></dd></div>
<div><dt>Envío</dt><dd><?= h(money_eur((float)$pedido['gastos_envio'])) ?></dd></div>
<div class="grand-total"><dt>Total</dt><dd><?= h(money_eur((float)$pedido['total'])) ?></dd></div>
</dl></section>
</div>
<h2>Productos del pedido</h2>
<?php if (!$lineas): ?><p class="empty">Este pedido no tiene líneas de producto registradas.</p><?php else: ?>
<table class="orders-table detail-lines" aria-label="Productos del pedido"><thead><tr><th scope="col">Producto</th><th scope="col">Precio unidad</th><th scope="col">Unidades</th><th scope="col">Subtotal</th></tr></thead><tbody>
<?php foreach ($lineas as $line): ?><tr>
<td data-label="Producto"><strong><?= h($line['nombre_producto']) ?></strong><?php if ($line['capacidad_ml']): ?><small><?= (int)$line['capacidad_ml'] ?> ml</small><?php endif; ?></td>
<td data-label="Precio unidad"><?= h(money_eur((float)$line['precio_unitario'])) ?></td><td data-label="Unidades"><?= (int)$line['unidades'] ?></td><td data-label="Subtotal"><?= h(money_eur((float)$line['precio_neto'])) ?></td>
</tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<?php if (!empty($pedido['observaciones'])): ?><section class="panel" aria-labelledby="notes-title"><h2 id="notes-title">Observaciones</h2><p class="notes"><?= h($pedido['observaciones']) ?></p></section><?php endif; ?>
<?php admin_page_end(); ?>
