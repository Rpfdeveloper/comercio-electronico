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
$notice = $_SESSION['cart_notice'] ?? '';
unset($_SESSION['cart_notice']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $next = $cart;
        if (isset($_POST['continuar']) && $_POST['continuar'] !== '1') {
            throw new DomainException('La acción solicitada no es válida.');
        }
        $continue = ($_POST['continuar'] ?? '') === '1';
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
        $checkout = $continue && $action === 'update' && $next;
        if (!$checkout) {
            $_SESSION['cart_notice'] = $action === 'remove' ? 'Producto eliminado del carrito.' :
                ($action === 'add' ? 'Producto añadido al carrito.' : 'Carrito actualizado.');
        }
        header('Location: ' . ($checkout ? 'checkout.php' : 'carrito.php'), true, 303);
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
$totals = cart_totals($items);
require_once __DIR__ . '/includes/header.php';
?>
<section class="commerce" aria-labelledby="cart-title">
  <p class="account-eyebrow">TU SELECCIÓN</p>
  <div class="commerce-heading"><h2 id="cart-title">Carrito de la compra</h2><a href="catalogo.php">Seguir comprando</a></div>
  <?php if ($error): ?><div class="commerce-error" role="alert" tabindex="-1" data-focus-on-load><p><?= h($error) ?></p></div><?php endif; ?>
  <?php if ($notice && !$error): ?><p class="commerce-notice" role="status"><?= h($notice) ?></p><?php endif; ?>
  <?php if (!$items): ?>
    <div class="commerce-panel commerce-empty">
      <?php if ($cart): ?>
        <h3>Hay productos que ya no están disponibles</h3><p>Elimínalos para continuar con tu compra.</p>
        <?php foreach ($cart as $key => $entry): ?>
          <form method="post" action="carrito.php"><?= csrf_field() ?><button class="commerce-secondary" type="submit" name="remove" value="<?= h((string)$key) ?>">Eliminar producto #<?= (int)$entry['id_producto'] ?></button></form>
        <?php endforeach; ?>
      <?php else: ?><h3>Tu carrito está vacío</h3><p>Explora el catálogo y encuentra tu próxima fragancia.</p><a class="btn" href="catalogo.php">Ver perfumes</a><?php endif; ?>
    </div>
  <?php else: ?>
    <form method="post" action="carrito.php" class="commerce-layout">
      <?= csrf_field() ?><input type="hidden" name="action" value="update">
      <div class="commerce-panel">
        <p class="commerce-help" id="quantity-help">Puedes actualizar las cantidades o continuar directamente. Al continuar se guardarán tus cambios. Una cantidad de 0 elimina el producto.</p>
        <ul class="cart-lines">
          <?php foreach ($items as $it): ?>
            <li class="cart-line">
              <div class="cart-line-description"><h3><?= h($it['nombre']) ?></h3>
                <p class="commerce-help"><?= $it['capacidad_ml'] ? (int)$it['capacidad_ml'] . ' ml · ' : '' ?><?= money_eur($it['precio']) ?> por unidad</p>
                <button type="submit" name="remove" value="<?= h($it['key']) ?>" class="commerce-remove" formnovalidate aria-label="Eliminar <?= h($it['nombre']) ?><?= $it['capacidad_ml'] ? ' de ' . (int)$it['capacidad_ml'] . ' ml' : '' ?>">Eliminar</button>
              </div>
              <div class="cart-line-quantity"><label for="qty-<?= h($it['key']) ?>">Cantidad<span class="sr-only"> de <?= h($it['nombre']) ?><?= $it['capacidad_ml'] ? ' de ' . (int)$it['capacidad_ml'] . ' ml' : '' ?></span></label>
                <input type="number" id="qty-<?= h($it['key']) ?>" name="cantidad[<?= h($it['key']) ?>]" value="<?= (int)$it['cantidad'] ?>" min="0" max="999" inputmode="numeric" aria-describedby="quantity-help" required>
              </div>
              <div class="cart-line-total"><span class="commerce-help">Subtotal</span><strong><?= money_eur($it['subtotal']) ?></strong></div>
            </li>
          <?php endforeach; ?>
        </ul>
        <button type="submit" class="commerce-secondary">Actualizar carrito</button>
      </div>
      <aside class="commerce-panel commerce-summary" aria-labelledby="summary-title">
        <?php $summaryItems = []; require __DIR__ . '/includes/order-summary.php'; ?>
        <button type="submit" name="continuar" value="1" class="commerce-primary">Continuar con la compra</button>
        <p class="commerce-help">Revisarás tus datos y el pedido antes de confirmarlo.</p>
      </aside>
    </form>
  <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
