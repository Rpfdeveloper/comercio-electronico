<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/customer.php';
$pdo = db();
$customer = customer_require($pdo);
$page = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
if ($page === false) { http_response_code(404); exit('Página no encontrada.'); }
$count = $pdo->prepare('SELECT COUNT(*) FROM pedido WHERE id_cliente=?');
$count->execute([$customer['id']]);
$totalOrders = (int)$count->fetchColumn();
$offset = ($page - 1) * 20;
$orders = $pdo->prepare('SELECT p.id,p.fecha_pedido,p.total,e.descripcion AS estado
    FROM pedido p JOIN estado_pedido e ON e.id=p.estado
    WHERE p.id_cliente=? ORDER BY p.fecha_pedido DESC,p.id DESC LIMIT 20 OFFSET ' . $offset);
$orders->execute([$customer['id']]);
$orders = $orders->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>
<section class="account-card" aria-labelledby="account-title">
  <div class="account-heading">
    <div><p class="account-eyebrow">MI CUENTA</p><h2 id="account-title">Hola, <?= h($customer['nombre'] ?: 'cliente') ?></h2></div>
    <form action="salir.php" method="post"><?= csrf_field() ?><button type="submit" class="account-secondary">Cerrar sesión</button></form>
  </div>
  <p><?= h($customer['email']) ?></p>
  <h3>Mis pedidos</h3>
  <p class="account-help">Aquí aparecen las compras realizadas con tu cuenta. Las compras como invitado se mantienen separadas.</p>
  <?php if (!$orders): ?>
    <div class="account-empty"><p>No hay pedidos en esta página.</p><a href="index.php">Explorar perfumes</a></div>
  <?php else: ?>
    <div class="account-table-wrap"><table class="account-table">
      <caption class="sr-only">Historial de pedidos de tu cuenta</caption>
      <thead><tr><th scope="col">Pedido</th><th scope="col">Fecha</th><th scope="col">Estado</th><th scope="col">Total</th><th scope="col">Detalle</th></tr></thead>
      <tbody><?php foreach ($orders as $order): ?><tr>
        <td>#<?= (int)$order['id'] ?></td><td><?= h($order['fecha_pedido']) ?></td>
        <td><span class="account-status"><?= h($order['estado']) ?></span></td>
        <td><?= number_format((float)$order['total'], 2, ',', '.') ?> €</td>
        <td><a href="mi-pedido.php?id=<?= (int)$order['id'] ?>">Ver pedido #<?= (int)$order['id'] ?></a></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
  <nav class="account-pages" aria-label="Páginas de pedidos">
    <?php if ($page > 1): ?><a href="cuenta.php?pagina=<?= $page - 1 ?>">Anterior</a><?php endif; ?>
    <?php if ($offset + 20 < $totalOrders): ?><a href="cuenta.php?pagina=<?= $page + 1 ?>">Siguiente</a><?php endif; ?>
  </nav>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
