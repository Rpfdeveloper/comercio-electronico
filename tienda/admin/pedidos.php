<?php
declare(strict_types=1);

session_start();                                   // Sesión para el panel admin
require_once __DIR__ . '/../config/init.php';      // Config + conexión BD

// Si no está logueado, redirigir al login
if (empty($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

// Obtener todos los pedidos ordenados por fecha
$st = db()->query(
    "SELECT id, fecha_pedido, email, nombre_envio, total, estado
     FROM pedido
     ORDER BY fecha_pedido DESC"
);

$pedidos = $st->fetchAll();                         // Array con todos los pedidos
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Admin - Pedidos</title>
</head>
<body>

<h2>Listado de pedidos</h2>

<!-- Enlace para cerrar sesión -->
<p><a href="logout.php">Cerrar sesión</a></p>

<!-- Tabla de pedidos -->
<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>ID</th>
        <th>Fecha</th>
        <th>Cliente</th>
        <th>Total (€)</th>
        <th>Estado</th>
        <th>Ver</th>
    </tr>

    <?php foreach ($pedidos as $p): ?>
        <tr>
            <!-- ID del pedido -->
            <td><?= (int)$p['id'] ?></td>

            <!-- Fecha/hora -->
            <td><?= htmlspecialchars($p['fecha_pedido']) ?></td>

            <!-- Cliente: nombre + email -->
            <td>
                <?= htmlspecialchars($p['nombre_envio']) ?>
                (<?= htmlspecialchars($p['email']) ?>)
            </td>

            <!-- Total del pedido -->
            <td><?= htmlspecialchars(number_format((float)$p['total'], 2, '.', '')) ?></td>

            <!-- Estado numérico → texto -->
            <td><?= $p['estado'] == 1 ? 'Confirmado' : 'Pendiente' ?></td>

            <!-- Enlace a la vista detallada -->
            <td><a href="pedido.php?id=<?= (int)$p['id'] ?>">Ver</a></td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>