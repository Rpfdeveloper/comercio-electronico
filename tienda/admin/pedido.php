<?php
declare(strict_types=1);

session_start();                                   // Sesión para el panel admin
require_once __DIR__ . '/../config/init.php';      // Configuración + BD

// Restringir acceso al panel admin
if (empty($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

// Validar ID del pedido
$id = $_GET['id'] ?? null;
if (!$id || !ctype_digit($id)) {                   // Solo números enteros válidos
    echo "ID de pedido inválido.";
    exit;
}

// ----------------------------------------------------
// Obtener datos del pedido
// ----------------------------------------------------
$st = db()->prepare("SELECT * FROM pedido WHERE id = :id");
$st->execute([':id' => $id]);
$pedido = $st->fetch();

if (!$pedido) {                                    // Pedido inexistente
    echo "Pedido no encontrado.";
    exit;
}

// ----------------------------------------------------
// Obtener líneas del pedido
// ----------------------------------------------------
$st = db()->prepare(
    "SELECT *
     FROM linea_pedido
     WHERE id_pedido = :id"
);
$st->execute([':id' => $id]);
$lineas = $st->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Admin - Pedido #<?= (int)$id ?></title>
</head>
<body>

<h2>Pedido #<?= (int)$id ?></h2>
<p><a href="pedidos.php">← Volver al listado</a></p>

<!-- Datos del cliente -->
<h3>Datos del cliente</h3>
<p>
<strong>Nombre:</strong> <?= htmlspecialchars($pedido['nombre_envio']) ?><br>
<strong>Email:</strong> <?= htmlspecialchars($pedido['email']) ?><br>

<strong>Dirección:</strong><br>
<?= htmlspecialchars($pedido['direccion_envio']) ?><br>
<?= htmlspecialchars($pedido['cp_envio']) ?> <?= htmlspecialchars($pedido['poblacion_envio']) ?><br>
<?= htmlspecialchars($pedido['provincia_envio'] ?? '') ?><br>
<?= htmlspecialchars($pedido['pais_envio']) ?><br>
</p>

<!-- Productos del pedido -->
<h3>Productos del pedido</h3>

<table border="1" cellpadding="6" cellspacing="0">
<thead>
<tr>
    <th>Producto</th>
    <th>Precio unidad</th>
    <th>Unidades</th>
    <th>Subtotal</th>
</tr>
</thead>
<tbody>

<?php foreach ($lineas as $l): ?>
<tr>
    <td>
        <?= htmlspecialchars($l['nombre_producto']) ?>
        <?php if ($l['capacidad_ml']): ?>
            (<?= (int)$l['capacidad_ml'] ?> ml)
        <?php endif; ?>
    </td>

    <td><?= number_format((float)$l['precio_unitario'], 2) ?> €</td>
    <td><?= (int)$l['unidades'] ?></td>
    <td><?= number_format((float)$l['precio_neto'], 2) ?> €</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

<!-- Totales -->
<h3>Total</h3>
<p>
Productos: <?= number_format((float)$pedido['importe_productos'], 2) ?> €<br>
IVA: <?= number_format((float)$pedido['impuestos'], 2) ?> €<br>
Envío: <?= number_format((float)$pedido['gastos_envio'], 2) ?> €<br>
<strong>Total: <?= number_format((float)$pedido['total'], 2) ?> €</strong>
</p>

</body>
</html>
