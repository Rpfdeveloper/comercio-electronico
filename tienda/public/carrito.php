<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/cart.php';

if (isset($_GET['remove'])) {
    header('Allow: POST');
    http_response_code(405);
    exit('Eliminar un producto requiere enviar el formulario.');
}
$cart = $_SESSION['cart'] ?? [];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $next = $cart;
        $action = isset($_POST['remove']) ? 'remove' : ($_POST['action'] ?? '');
        if ($action === 'add') {
            $id = cart_integer($_POST['id_producto'] ?? null);
            $variant = isset($_POST['id_variante']) && $_POST['id_variante'] !== ''
                ? cart_integer($_POST['id_variante']) : null;
            $qty = cart_integer($_POST['cantidad'] ?? null);
            $key = $variant !== null ? 'v' . $variant : 'p' . $id;
            $next[$key] = [
                'id_producto' => $id,
                'id_variante' => $variant,
                'cantidad' => ($next[$key]['cantidad'] ?? 0) + $qty,
            ];
        } elseif ($action === 'update') {
            $quantities = $_POST['cantidad'] ?? null;
            if (!is_array($quantities)) {
                throw new DomainException('Las cantidades no son válidas.');
            }
            foreach ($quantities as $key => $value) {
                if (!isset($next[$key])) {
                    throw new DomainException('El producto no está en el carrito.');
                }
                $qty = cart_integer($value, 0);
                if ($qty === 0) {
                    unset($next[$key]);
                } else {
                    $next[$key]['cantidad'] = $qty;
                }
            }
        } elseif ($action === 'remove') {
            $key = $_POST['remove'];
            if (!is_string($key) || !isset($next[$key])) {
                throw new DomainException('El producto no está en el carrito.');
            }
            unset($next[$key]);
        } else {
            throw new DomainException('La acción solicitada no es válida.');
        }
        if ($action !== 'remove' && !($action === 'update' && !$next)) {
            cart_items(db(), $next); // Validar antes de modificar la sesión.
        }
        $_SESSION['cart'] = $next;
        unset($_SESSION['checkout']);
        header('Location: carrito.php', true, 303);
        exit;
    } catch (DomainException $e) {
        http_response_code(422);
        $error = $e->getMessage();
    }
}
try {
    $items = cart_items(db(), $cart);
} catch (DomainException $e) {
    $items = [];
    $error = $e->getMessage();
}
$importeProductos = array_sum(array_column($items, 'subtotal'));
require_once __DIR__ . '/includes/header.php';
?>
<section class="container" style="margin-top:2rem;">
  <h2>Carrito de la compra</h2>
  <?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>

  <?php if (!$items): ?>
    <?php if ($cart): ?>
      <p>Revisa o elimina los productos que ya no están disponibles.</p>
      <?php foreach ($cart as $key => $entry): ?>
        <form method="post" action="carrito.php">
          <?= csrf_field() ?>
          <button type="submit" name="remove" value="<?= h((string)$key) ?>">Eliminar producto #<?= (int)$entry['id_producto'] ?></button>
        </form>
      <?php endforeach; ?>
    <?php else: ?><p>Tu carrito está vacío.</p><?php endif; ?>
  <?php else: ?>
    <form method="post" action="carrito.php">
      <?= csrf_field() ?>
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
              <button type="submit" name="remove" value="<?= h($it['key']) ?>">Eliminar</button>
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

