<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
admin_require();
require_once __DIR__ . '/../src/lib/admin_orders.php';
[$filters, $errors] = admin_orders_filters($_GET);
$result = null;
$unavailable = false;
if ($errors) { http_response_code(422); }
else {
    try {
        $result = admin_orders_search(db(), $filters);
        $filters['pagina'] = (string)$result['page'];
    } catch (PDOException $e) { http_response_code(503); $unavailable = true; }
}
$fieldError = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="error-' . $name . '"' : '';
};
admin_page_start('Pedidos');
?>
<p class="eyebrow">Operaciones / Pedidos</p>
<h1>Todos los pedidos, a mano.</h1>
<p class="lede">Localiza una compra, revisa su detalle y consulta los importes registrados.</p>
<?php if ($errors): ?>
<div class="notice" role="alert"><strong>Revisa los filtros para continuar.</strong><ul>
<?php foreach ($errors as $key=>$message): ?><li><a href="#<?= $key === 'pagina' ? 'filtros' : 'filter-' . h($key) ?>"><?= h($message) ?></a></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>
<?php if ($unavailable): ?><div class="notice" role="alert">No podemos consultar los pedidos en este momento. Inténtalo de nuevo más tarde.</div><?php endif; ?>
<?php if ($result !== null): $totals = $result['totals']; ?>
<section class="stats" aria-label="Resumen de los resultados filtrados">
  <div class="stat highlight"><span>Pedidos encontrados</span><strong><?= (int)$totals['cantidad'] ?></strong></div>
  <div class="stat"><span>Pendientes</span><strong><?= (int)$totals['pendientes'] ?></strong></div>
  <div class="stat"><span>Pago registrado</span><strong><?= (int)$totals['pagados'] ?></strong></div>
  <div class="stat"><span>Importe de los pedidos</span><strong><?= h(money_eur((float)$totals['importe'])) ?></strong></div>
</section>
<?php endif; ?>
<section class="panel" id="filtros" aria-labelledby="filter-title">
<h2 id="filter-title">Encuentra un pedido</h2>
<form method="get" action="pedidos.php" class="filters" role="search" aria-label="Buscar pedidos">
  <div class="field"><label for="filter-q">Número, nombre o correo</label><input id="filter-q" name="q" type="search" maxlength="160" autocomplete="off" value="<?= h($filters['q']) ?>"<?= $fieldError('q') ?>><?php if (isset($errors['q'])): ?><p class="error-text" id="error-q"><?= h($errors['q']) ?></p><?php endif; ?></div>
  <div class="field"><label for="filter-estado">Estado</label><select id="filter-estado" name="estado"<?= $fieldError('estado') ?>><?php foreach ([''=>'Todos', 'pending'=>'Pendiente', 'paid'=>'Pago registrado', 'processing'=>'En preparación', 'shipped'=>'Enviado', 'canceled'=>'Cancelado', 'otros'=>'Otros estados'] as $value=>$label): ?><option value="<?= h((string)$value) ?>"<?= $filters['estado'] === (string)$value ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select><?php if (isset($errors['estado'])): ?><p class="error-text" id="error-estado"><?= h($errors['estado']) ?></p><?php endif; ?></div>
  <?php foreach (['desde'=>'Desde', 'hasta'=>'Hasta'] as $name=>$label): ?><div class="field"><label for="filter-<?= $name ?>"><?= $label ?></label><input id="filter-<?= $name ?>" type="date" name="<?= $name ?>" value="<?= h($filters[$name]) ?>"<?= $fieldError($name) ?>><?php if (isset($errors[$name])): ?><p class="error-text" id="error-<?= $name ?>"><?= h($errors[$name]) ?></p><?php endif; ?></div><?php endforeach; ?>
  <div class="field"><label for="filter-orden">Ordenar por</label><select id="filter-orden" name="orden"<?= $fieldError('orden') ?>><?php foreach (['recientes'=>'Más recientes', 'antiguos'=>'Más antiguos', 'importe_mayor'=>'Mayor importe', 'importe_menor'=>'Menor importe'] as $value=>$label): ?><option value="<?= $value ?>"<?= $filters['orden'] === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><?php if (isset($errors['orden'])): ?><p class="error-text" id="error-orden"><?= h($errors['orden']) ?></p><?php endif; ?></div>
  <div class="filter-actions"><a href="pedidos.php">Limpiar filtros</a><button class="button" type="submit">Aplicar filtros</button></div>
</form>
</section>
<?php if ($result !== null): ?>
<div class="list-header"><h2>Resultados</h2><p class="muted"><?= (int)$totals['cantidad'] ? $result['offset'] + 1 : 0 ?>–<?= $result['offset'] + count($result['orders']) ?> de <?= (int)$totals['cantidad'] ?> pedidos</p></div>
<?php if (!$result['orders']): ?>
<div class="empty"><h2>No hay pedidos con estos filtros.</h2><p class="muted">Prueba con otro nombre, estado o intervalo de fechas.</p><a class="button secondary" href="pedidos.php">Ver todos los pedidos</a></div>
<?php else: ?>
<table class="orders-table" aria-label="Pedidos encontrados">
<thead><tr><th scope="col">Pedido</th><th scope="col">Fecha</th><th scope="col">Cliente</th><th scope="col">Importe</th><th scope="col">Estado</th><th scope="col">Detalle</th></tr></thead>
<tbody>
<?php foreach ($result['orders'] as $order): [$stateLabel, $stateClass] = admin_order_state($order['estado_texto']); ?>
<tr data-order-id="<?= (int)$order['id'] ?>">
  <td data-label="Pedido"><strong>#<?= (int)$order['id'] ?></strong></td>
  <td data-label="Fecha"><?= h($order['fecha_pedido']) ?></td>
  <td data-label="Cliente"><strong><?= h($order['nombre_envio']) ?></strong><small><?= h($order['email']) ?></small></td>
  <td data-label="Importe"><strong><?= h(money_eur((float)$order['total'])) ?></strong></td>
  <td data-label="Estado"><span class="badge <?= $stateClass ?>"><?= h($stateLabel) ?></span></td>
  <td data-label="Detalle"><a class="order-link" href="pedido.php?<?= h(http_build_query(['id'=>(int)$order['id']] + $filters, '', '&', PHP_QUERY_RFC3986)) ?>" aria-label="Ver pedido <?= (int)$order['id'] ?>">Ver <span aria-hidden="true">→</span></a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<nav class="pagination" aria-label="Páginas de pedidos"><p>Página <?= $result['page'] ?> de <?= $result['pages'] ?></p><div class="links">
<?php if ($result['page'] > 1): ?><a class="button secondary" rel="prev" href="<?= h(admin_orders_url($filters, $result['page'] - 1)) ?>">← Anterior</a><?php endif; ?>
<?php if ($result['page'] < $result['pages']): ?><a class="button secondary" rel="next" href="<?= h(admin_orders_url($filters, $result['page'] + 1)) ?>">Siguiente →</a><?php endif; ?>
</div></nav>
<p class="muted">Los importes corresponden a pedidos registrados. El estado guardado no sustituye la verificación del pago.</p>
<?php endif; ?>
<?php admin_page_end(); ?>
