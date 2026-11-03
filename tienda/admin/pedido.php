<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
admin_require();
require_once __DIR__ . '/../src/lib/admin_orders.php';
require_once __DIR__ . '/../src/lib/order_status.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>2147483647]]);
if ($id === false || $id === null) { http_response_code(404); exit('Pedido no encontrado.'); }
[$filters, $errors] = admin_orders_filters($_GET);
$backUrl = $errors ? 'pedidos.php' : admin_orders_url($filters);
$selfUrl = 'pedido.php?' . http_build_query(['id'=>$id] + ($errors ? [] : $filters), '', '&', PHP_QUERY_RFC3986);
$statusError = ''; $conflict = ''; $postedNote = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = order_status_change(db(), $id, $_POST);
        $message = 'Estado actualizado: ' . order_status_label($result['to']) . '.';
        if ($result['restored'] > 0) { $message .= ' Se han repuesto ' . $result['restored'] . ' unidades al inventario.'; }
        $_SESSION['order_notice'] = $message;
        header('Location: ' . $selfUrl, true, 303); exit;
    } catch (OrderStatusValidation $e) { $statusError = $e->getMessage(); http_response_code(422); }
    catch (OrderStatusConflict $e) { $conflict = $e->getMessage(); http_response_code(409); }
    catch (OutOfBoundsException $e) { http_response_code(404); exit('Pedido no encontrado.'); }
    catch (PDOException $e) { $statusError = 'No se ha podido guardar el cambio. No se ha modificado el pedido; inténtalo de nuevo.'; http_response_code(503); }
    if (is_string($_POST['nota'] ?? null) && strlen($_POST['nota']) <= 4 * ORDER_STATUS_NOTE_LIMIT) { $postedNote = $_POST['nota']; }
}
$st = db()->prepare('SELECT p.*,e.descripcion AS estado_texto FROM pedido p JOIN estado_pedido e ON e.id=p.estado WHERE p.id = ?');
$st->execute([$id]);
$pedido = $st->fetch();
if (!$pedido) { http_response_code(404); exit('Pedido no encontrado.'); }
$st = db()->prepare('SELECT * FROM linea_pedido WHERE id_pedido = ? ORDER BY id');
$st->execute([$id]);
$lineas = $st->fetchAll();
$history = []; $historyUnavailable = false;
try { $history = order_status_history(db(), $id); } catch (PDOException $e) { $historyUnavailable = true; }
$transitions = order_status_transitions($pedido['estado_texto']);
$units = array_sum(array_map('intval', array_column($lineas, 'unidades')));
$notice = $_SESSION['order_notice'] ?? ''; unset($_SESSION['order_notice']);
[$stateLabel, $stateClass] = admin_order_state($pedido['estado_texto']);
admin_page_start('Pedido #' . $id);
?>
<p class="eyebrow">Operaciones / Detalle</p>
<a href="<?= h($backUrl) ?>">← Volver al listado</a>
<div class="detail-heading"><h1>Pedido #<?= (int)$id ?></h1><span class="badge <?= $stateClass ?>"><?= h($stateLabel) ?></span></div>
<p class="muted">Registrado el <?= h($pedido['fecha_pedido']) ?>. El estado no confirma un cobro.</p>
<?php if ($notice): ?><p class="notice success" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($conflict): ?><div class="notice" role="alert"><p><?= h($conflict) ?></p><a class="button secondary" href="<?= h($selfUrl) ?>">Ver estado actual</a></div><?php endif; ?>
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
<section class="panel status-panel" id="estado" aria-labelledby="status-title">
<h2 id="status-title">Estado del pedido</h2>
<ol class="status-flow" aria-label="Recorrido del pedido">
<?php foreach (['pending', 'paid', 'processing', 'shipped'] as $step): ?><li<?= $pedido['estado_texto'] === $step ? ' aria-current="step"' : '' ?>><?= h(order_status_label($step)) ?></li><?php endforeach; ?>
</ol>
<?php if ($statusError): ?><div class="notice" role="alert" id="status-error"><strong>No se ha cambiado el estado.</strong> <?= h($statusError) ?></div><?php endif; ?>
<?php if (!$transitions): ?>
<p class="muted"><?= in_array($pedido['estado_texto'], ['shipped', 'canceled'], true) ? 'Este pedido está cerrado: no admite más cambios de estado desde el panel.' : 'Este estado no forma parte del flujo conocido. Revísalo en la base de datos antes de modificarlo.' ?></p>
<?php elseif (!$conflict): ?>
<form method="post" action="<?= h($selfUrl) ?>" class="status-form"<?= $statusError ? ' aria-describedby="status-error"' : '' ?>>
<?= csrf_field() ?><input type="hidden" name="estado_actual" value="<?= h($pedido['estado_texto']) ?>">
<fieldset><legend>Siguiente paso</legend>
<?php foreach ($transitions as $i=>$next): $checked = ($_POST['estado_nuevo'] ?? null) === $next || (!isset($_POST['estado_nuevo']) && $i === 0); ?>
<label class="status-option<?= $next === 'canceled' ? ' danger' : '' ?>"><input type="radio" name="estado_nuevo" value="<?= h($next) ?>"<?= $checked ? ' checked' : '' ?> required> <span><?= h(order_status_action_label($next)) ?></span></label>
<?php endforeach; ?>
</fieldset>
<?php if (in_array('paid', $transitions, true)): ?><p class="muted hint">Registra el pago solo cuando lo hayas comprobado fuera de la tienda: este prototipo no cobra ni verifica pagos.</p><?php endif; ?>
<?php if (in_array('canceled', $transitions, true)): ?>
<label class="status-confirm"><input type="checkbox" name="confirmar" value="1"> Confirmo la cancelación. Las <?= (int)$units ?> unidades del pedido volverán al inventario.</label>
<p class="muted hint">La cancelación no reembolsa pagos. Solo es posible antes del envío.</p>
<?php endif; ?>
<div class="field"><label for="status-note">Nota interna (opcional)</label><textarea id="status-note" name="nota" maxlength="<?= ORDER_STATUS_NOTE_LIMIT ?>" rows="3"><?= h($postedNote) ?></textarea><p class="muted hint">Hasta <?= ORDER_STATUS_NOTE_LIMIT ?> caracteres. No incluyas datos de pago ni contraseñas.</p></div>
<div class="editor-actions"><button class="button" type="submit">Guardar cambio de estado</button></div>
</form>
<?php endif; ?>
<details class="catalog-history"<?= $history ? '' : ' open' ?>><summary>Historial de estados (<?= count($history) ?>)</summary>
<?php if ($historyUnavailable): ?><p class="muted">El historial no está disponible. Aplica la migración 005 antes de gestionar estados.</p>
<?php elseif (!$history): ?><p class="muted">Sin cambios registrados desde el panel.</p>
<?php else: ?><dl><?php foreach ($history as $change): ?><div data-history-id="<?= (int)$change['id'] ?>"><dt><?= h(order_status_label($change['anterior'])) ?> → <?= h(order_status_label($change['nuevo'])) ?> <span class="muted">· <?= h($change['fecha']) ?></span></dt>
<?php if ((int)$change['unidades_repuestas'] > 0): ?><dd><?= (int)$change['unidades_repuestas'] ?> unidades repuestas al inventario.</dd><?php endif; ?>
<?php if ($change['nota'] !== null): ?><dd class="notes"><?= h($change['nota']) ?></dd><?php endif; ?></div><?php endforeach; ?></dl><?php endif; ?>
</details>
</section>
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
