<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/includes/header.php';

// Aseguramos estructura del carrito en sesión
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart =& $_SESSION['cart'];

// Gestión de acciones (add, update, remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $idProd = (int)($_POST['id_producto'] ?? 0);
        $idVar  = isset($_POST['id_variante']) && $_POST['id_variante'] !== ''
            ? (int)$_POST['id_variante']
            : null;
        $qty    = max(1, (int)($_POST['cantidad'] ?? 1));

        if ($idProd > 0) {
            $key = $idVar ? ('v' . $idVar) : ('p' . $idProd);

            if (isset($cart[$key])) {
                $cart[$key]['cantidad'] += $qty;
            } else {
                $cart[$key] = [
                    'id_producto' => $idProd,
                    'id_variante' => $idVar,
                    'cantidad'    => $qty,
                ];
            }
        }

        header('Location: carrito.php');
        exit;
    }

    if ($action === 'update') {
        $cantidades = $_POST['cantidad'] ?? [];
        foreach ($cantidades as $key => $q) {
            $q = (int)$q;
            //si la cantidad se pone a cero, se elimina
            if ($q <= 0) {
                unset($cart[$key]);
            } elseif (isset($cart[$key])) {
                $cart[$key]['cantidad'] = $q;
            }
        }

        header('Location: carrito.php');
        exit;
    }
}

if (isset($_GET['remove'])) {
    $key = $_GET['remove'];
    if (isset($cart[$key])) {
        unset($cart[$key]);
    }
    header('Location: carrito.php');
    exit;
}

// Construimos los datos de líneas desde la BD
$items = [];
$importeProductos = 0.0;

if ($cart) {
    $pdo = db();

    foreach ($cart as $key => $item) {
        $idProd = (int)$item['id_producto'];
        $idVar  = $item['id_variante'] !== null ? (int)$item['id_variante'] : null;
        $qty    = (int)$item['cantidad'];

        if ($idVar) {
            $st = $pdo->prepare(
                "SELECT p.id AS id_producto,
                        p.nombre,
                        v.id AS id_variante,
                        v.capacidad_ml,
                        COALESCE(NULLIF(v.precio_oferta,0.00), v.precio) AS precio
                 FROM producto_variante v
                 JOIN producto p ON p.id = v.id_producto
                 WHERE v.id = :id"
            );
            $st->execute([':id' => $idVar]);
        } else {
            $st = $pdo->prepare(
                "SELECT p.id AS id_producto,
                        p.nombre,
                        NULL AS id_variante,
                        NULL AS capacidad_ml,
                        COALESCE(NULLIF(p.precio_oferta,0.00), p.precio) AS precio
                 FROM producto p
                 WHERE p.id = :id"
            );
            $st->execute([':id' => $idProd]);
        }

        $row = $st->fetch();
        if (!$row) {
            continue;
        }

        $precio   = (float)$row['precio'];
        $subtotal = $precio * $qty;
        $importeProductos += $subtotal;

        $items[$key] = [
            'key'          => $key,
            'id_producto'  => (int)$row['id_producto'],
            'id_variante'  => $row['id_variante'] !== null ? (int)$row['id_variante'] : null,
            'nombre'       => $row['nombre'],
            'capacidad_ml' => $row['capacidad_ml'] !== null ? (int)$row['capacidad_ml'] : null,
            'precio'       => $precio,
            'cantidad'     => $qty,
            'subtotal'     => $subtotal,
        ];
    }
}
?>
<section class="container" style="margin-top:2rem;">
  <h2>Carrito de la compra</h2>

  <?php if (!$items): ?>
    <p>Tu carrito está vacío.</p>
  <?php else: ?>
    <form method="post" action="carrito.php">
      <input type="hidden" name="action" value="update">

      <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
        <thead>
        <tr>
          <th style="text-align:left; border-bottom:1px solid #e5e7eb;">Producto</th>
          <th style="text-align:right; border-bottom:1px solid #e5e7eb;">Precio</th>
          <th style="text-align:center; border-bottom:1px solid #e5e7eb;">Cantidad</th>
          <th style="text-align:right; border-bottom:1px solid #e5e7eb;">Subtotal</th>
          <th style="border-bottom:1px solid #e5e7eb;"></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td>
              <?= h($it['nombre']) ?>
              <?php if ($it['capacidad_ml']): ?>
                (<?= (int)$it['capacidad_ml'] ?> ml)
              <?php endif; ?>
            </td>
            <td style="text-align:right;">
              <?= number_format($it['precio'], 2) ?> €
            </td>
            <td style="text-align:center;">
              <input
                type="number"
                name="cantidad[<?= h($it['key']) ?>]"
                value="<?= (int)$it['cantidad'] ?>"
                min="0"
                style="width:70px;"
              >
            </td>
            <td style="text-align:right;">
              <?= number_format($it['subtotal'], 2) ?> €
            </td>
            <td style="text-align:center;">
              <a href="carrito.php?remove=<?= urlencode($it['key']) ?>">Eliminar</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <p style="text-align:right; margin-top:1rem; font-weight:bold;">
        Importe productos: <?= number_format($importeProductos, 2) ?> €
      </p>

      <div style="display:flex; justify-content:space-between; margin-top:1rem;">
        <button type="submit">Actualizar cantidades</button>
        <a href="checkout.php" style="align-self:center;">Tramitar compra</a>
      </div>
    </form>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

