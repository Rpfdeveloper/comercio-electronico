<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/customer.php';
$pdo = db();
$customer = customer_require($pdo);
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$order = false;
if ($id !== false && $id !== null) {
    // La autorización forma parte de la consulta: nunca buscar solo por número o correo.
    $q = $pdo->prepare('SELECT p.*,e.descripcion AS estado_texto FROM pedido p
        JOIN estado_pedido e ON e.id=p.estado WHERE p.id=? AND p.id_cliente=?');
    $q->execute([$id, $customer['id']]);
    $order = $q->fetch();
}
if (!$order) {
    http_response_code(404);
    require_once __DIR__ . '/includes/header.php';
    echo '<section class="account-card"><h2>Pedido no encontrado</h2><p>No se puede mostrar este pedido.</p><a href="cuenta.php">Volver a mi cuenta</a></section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
$q = $pdo->prepare('SELECT nombre_producto,capacidad_ml,unidades,precio_unitario,precio_neto
    FROM linea_pedido WHERE id_pedido=? ORDER BY id');
$q->execute([$order['id']]);
$lines = $q->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>
<section class="account-card" aria-labelledby="order-title">
  <a href="cuenta.php">← Mis pedidos</a>
  <div class="account-heading"><h2 id="order-title">Pedido #<?= (int)$order['id'] ?></h2><span class="account-status"><?= h($order['estado_texto']) ?></span></div>
  <p><?= h($order['fecha_pedido']) ?></p>
  <div class="account-table-wrap"><table class="account-table">
    <caption class="sr-only">Productos del pedido</caption>
    <thead><tr><th scope="col">Producto</th><th scope="col">Unidades</th><th scope="col">Precio</th><th scope="col">Subtotal</th></tr></thead>
    <tbody><?php foreach ($lines as $line): ?><tr>
      <td><?= h($line['nombre_producto']) ?><?= $line['capacidad_ml'] ? ' · ' . (int)$line['capacidad_ml'] . ' ml' : '' ?></td>
      <td><?= (int)$line['unidades'] ?></td><td><?= number_format((float)$line['precio_unitario'], 2, ',', '.') ?> €</td><td><?= number_format((float)$line['precio_neto'], 2, ',', '.') ?> €</td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <div class="account-details">
    <div><h3>Dirección de envío</h3><address><?= h($order['nombre_envio']) ?><br><?= h($order['direccion_envio']) ?><br><?= h($order['cp_envio'] . ' ' . $order['poblacion_envio']) ?><br><?= h(($order['provincia_envio'] ?: '') . ' · ' . $order['pais_envio']) ?></address></div>
    <dl class="account-totals">
      <dt>Productos</dt><dd><?= number_format((float)$order['importe_productos'], 2, ',', '.') ?> €</dd>
      <dt>Impuestos</dt><dd><?= number_format((float)$order['impuestos'], 2, ',', '.') ?> €</dd>
      <dt>Envío</dt><dd><?= number_format((float)$order['gastos_envio'], 2, ',', '.') ?> €</dd>
      <dt><strong>Total</strong></dt><dd><strong><?= number_format((float)$order['total'], 2, ',', '.') ?> €</strong></dd>
    </dl>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
