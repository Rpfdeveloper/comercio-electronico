<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/catalog.php';
$pageTitle = 'Mis favoritos';
$ids = favorites_ids();
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        if (!in_array($action, ['add', 'remove', 'clear'], true)) {
            throw new InvalidArgumentException('Elige una acción válida.');
        }
        if ($action === 'clear') {
            $ids = [];
            $message = 'Has vaciado tu colección de favoritos.';
        } else {
            $rawId = $_POST['id_producto'] ?? '';
            if (!is_string($rawId) || !preg_match('/^[1-9][0-9]{0,9}$/D', $rawId) || (int)$rawId > 2147483647) {
                throw new InvalidArgumentException('Selecciona un perfume válido.');
            }
            $id = (int)$rawId;
            if ($action === 'add') {
                if (!favorites_products(db(), [$id])) { throw new OutOfBoundsException('Este perfume ya no está en el catálogo.'); }
                if (!in_array($id, $ids, true)) {
                    if (count($ids) >= FAVORITES_LIMIT) { throw new LengthException('Puedes guardar hasta 24 perfumes. Quita uno para añadir otro.'); }
                    array_unshift($ids, $id);
                }
                $message = 'Perfume guardado. Puedes volver a él desde Mis favoritos.';
            } else {
                $ids = array_values(array_diff($ids, [$id]));
                $message = 'Perfume quitado de favoritos.';
            }
        }
        favorites_write($ids);
        $_SESSION['favorites_notice'] = $message;
        header('Location: ' . url(favorites_return($_POST['return'] ?? 'favoritos.php')), true, 303);
        exit;
    } catch (InvalidArgumentException $e) {
        http_response_code(422); $error = $e->getMessage();
    } catch (OutOfBoundsException $e) {
        http_response_code(404); $error = $e->getMessage();
    } catch (LengthException $e) {
        http_response_code(409); $error = $e->getMessage();
    }
}
$products = favorites_products(db(), $ids);
require __DIR__ . '/includes/header.php';
?>
<div class="storefront favorites-page">
  <section class="discovery-intro" aria-labelledby="favorites-title">
    <p class="store-eyebrow">TU COLECCIÓN PERSONAL</p>
    <h1 id="favorites-title">Lo que te gusta,<br><em>para cuando quieras.</em></h1>
    <p class="store-lead">Guarda los perfumes que despiertan tu curiosidad y vuelve a ellos a tu ritmo. Sin crear una cuenta.</p>
    <p class="store-caption">Hasta 24 perfumes en este navegador durante 180 días desde el último cambio. Si borras las cookies, se pierde la lista. No se sincroniza entre dispositivos ni reserva stock.</p>
  </section>
  <?php if ($error): ?><p class="account-error" role="alert" tabindex="-1" data-focus-on-load><?= h($error) ?></p><?php endif; ?>
  <?php if ($ids): ?>
    <div class="favorites-toolbar"><p><strong><?= count($products) ?></strong> <?= count($products) === 1 ? 'perfume para volver a descubrir' : 'perfumes para volver a descubrir' ?></p>
      <form method="post" action="<?= h(url('favoritos.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="favorite-button" type="submit">Vaciar favoritos</button></form>
    </div>
    <?php if (count($products) < count($ids)): ?><p class="message-box">Algunos perfumes guardados ya no están en el catálogo. Puedes vaciar la lista para eliminarlos.</p><?php endif; ?>
    <div class="grid store-product-grid"><?php foreach ($products as $product): ?><?php require __DIR__ . '/includes/product-card.php'; ?><?php endforeach; ?></div>
    <p class="store-caption">Precios y disponibilidad actualizados al abrir esta página. Comprueba la capacidad y el importe en cada ficha.</p>
  <?php else: ?>
    <section class="favorites-empty"><span class="favorites-mark" aria-hidden="true">♡</span><h2>Tu colección empieza con una curiosidad.</h2><p>Pulsa «Guardar perfume» en el catálogo o en una ficha. Lo encontrarás aquí en tu próxima visita.</p><a class="btn" href="<?= h(url('catalogo.php')) ?>">Explorar perfumes <span aria-hidden="true">→</span></a></section>
  <?php endif; ?>
  <a class="store-text-link" href="<?= h(url('descubrir.php')) ?>">Seguir buscando mi perfume <span aria-hidden="true">→</span></a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
