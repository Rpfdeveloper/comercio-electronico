<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/catalog.php';
$pageTitle = 'Mis favoritos';
$context = favorites_context(db());
$favoriteCustomer = $context['customer'];
$ids = $context['ids'];
$guestIds = favorites_ids();
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $scope = $_POST['scope'] ?? 'browser';
        if (!in_array($action, ['add', 'remove', 'clear', 'merge'], true) || !in_array($scope, ['browser','account'], true)) {
            throw new InvalidArgumentException('Elige una acción válida.');
        }
        if (($scope === 'account' || $action === 'merge') && $favoriteCustomer === null) {
            throw new FavoritesSessionExpired('Accede a tu cuenta para guardar estos cambios. Tus favoritos del navegador se han conservado.');
        }
        if ($scope === 'browser' && $favoriteCustomer !== null) {
            throw new DomainException('La sesión ha cambiado. Recarga la página antes de modificar tus favoritos.');
        }
        $id = null;
        if (in_array($action, ['add','remove'], true)) {
            $rawId = $_POST['id_producto'] ?? '';
            if (!is_string($rawId) || !preg_match('/^[1-9][0-9]{0,9}$/D', $rawId) || (int)$rawId > 2147483647) {
                throw new InvalidArgumentException('Selecciona un perfume válido.');
            }
            $id = (int)$rawId;
        }
        if ($favoriteCustomer !== null) {
            $ids = favorites_account_update(db(), $favoriteCustomer, $action, $id, $guestIds);
            // Solo tras confirmar la transacción. Un fallo conserva ambas listas.
            if ($action === 'merge') { favorites_write([]); }
        } else {
            if ($action === 'clear') {
                $ids = [];
            } elseif ($action === 'add') {
                if (!favorites_products(db(), [$id])) { throw new OutOfBoundsException('Este perfume ya no está en el catálogo.'); }
                if (!in_array($id, $ids, true)) {
                    if (count($ids) >= FAVORITES_LIMIT) { throw new LengthException('Puedes guardar hasta 24 perfumes. Quita uno para añadir otro.'); }
                    array_unshift($ids, $id);
                }
            } elseif ($action === 'remove') {
                $ids = array_values(array_diff($ids, [$id]));
            }
            favorites_write($ids);
        }
        $messages = ['add'=>'Perfume guardado. Puedes volver a él desde Mis favoritos.',
            'remove'=>'Perfume quitado de favoritos.', 'clear'=>'Has vaciado tu colección de favoritos.',
            'merge'=>'Favoritos unidos a tu cuenta. Los perfumes retirados del catálogo no se han importado.'];
        $_SESSION['favorites_notice'] = $messages[$action];
        header('Location: ' . url(favorites_return($_POST['return'] ?? 'favoritos.php')), true, 303);
        exit;
    } catch (InvalidArgumentException $e) {
        http_response_code(422); $error = $e->getMessage();
    } catch (OutOfBoundsException $e) {
        http_response_code(404); $error = $e->getMessage();
    } catch (LengthException | DomainException $e) {
        http_response_code(409); $error = $e->getMessage();
    } catch (FavoritesSessionExpired $e) {
        http_response_code(403); $error = $e->getMessage();
    } catch (PDOException $e) {
        http_response_code(503); $error = 'No pudimos confirmar el cambio. Recarga tus favoritos antes de intentarlo de nuevo.';
    }
    // En un conflicto, volver a leer el estado confirmado por otros dispositivos.
    $context = favorites_context(db());
    $favoriteCustomer = $context['customer'];
    $ids = $context['ids'];
}
$products = favorites_products(db(), $ids);
require __DIR__ . '/includes/header.php';
?>
<div class="storefront favorites-page">
  <section class="discovery-intro" aria-labelledby="favorites-title">
    <p class="store-eyebrow">TU COLECCIÓN PERSONAL</p>
    <h1 id="favorites-title">Lo que te gusta,<br><em>para cuando quieras.</em></h1>
    <?php if ($favoriteCustomer !== null): ?>
      <p class="store-lead">Tu colección está guardada en tu cuenta. Inicia sesión en otro dispositivo y vuelve a encontrarla.</p>
      <p class="favorites-scope"><span aria-hidden="true">✓</span> Guardando en tu cuenta</p>
      <p class="store-caption">Hasta 24 perfumes. Los cambios se muestran al volver a abrir la página. Cerrar sesión no borra tu colección ni la copia a este navegador. Guardar no reserva stock.</p>
    <?php else: ?>
      <p class="store-lead">Guarda los perfumes que despiertan tu curiosidad y vuelve a ellos a tu ritmo. Sin crear una cuenta.</p>
      <p class="favorites-scope">Guardando en este navegador</p>
      <p class="store-caption">Hasta 24 perfumes en este navegador durante 180 días desde el último cambio. Si borras las cookies, se pierde la lista. No se sincroniza entre dispositivos ni reserva stock.</p>
      <p class="store-caption">¿Ya tienes cuenta? <a href="<?= h(url('acceso.php')) ?>">Inicia sesión</a> y elige si quieres unir estos favoritos a tu colección.</p>
    <?php endif; ?>
  </section>
  <?php if ($error): ?><p class="account-error" role="alert" tabindex="-1" data-focus-on-load><?= h($error) ?></p><?php endif; ?>
  <?php if ($favoriteCustomer !== null && $guestIds): ?>
    <section class="favorites-merge" aria-labelledby="merge-title">
      <div><p class="store-eyebrow">RECUPERA TU SELECCIÓN</p><h2 id="merge-title">También tienes favoritos en este navegador.</h2>
        <p>Puedes unirlos a tu cuenta sin duplicar los que ya guardaste. Se importan solo los perfumes que siguen en el catálogo. Después se vacía la lista de este navegador.</p>
        <p class="store-caption">Si se superan los 24 perfumes, se conservan ambas listas y puedes quitar alguno antes de intentarlo de nuevo.</p></div>
      <form method="post" action="<?= h(url('favoritos.php')) ?>"><?= csrf_field() ?><input type="hidden" name="scope" value="account"><input type="hidden" name="action" value="merge"><button type="submit">Unir favoritos a mi cuenta</button></form>
    </section>
  <?php endif; ?>
  <?php if ($ids): ?>
    <div class="favorites-toolbar"><p><strong><?= count($products) ?></strong> <?= count($products) === 1 ? 'perfume para volver a descubrir' : 'perfumes para volver a descubrir' ?></p>
      <form method="post" action="<?= h(url('favoritos.php')) ?>"><?= csrf_field() ?><input type="hidden" name="scope" value="<?= $favoriteCustomer === null ? 'browser' : 'account' ?>"><input type="hidden" name="action" value="clear"><button class="favorite-button" type="submit">Vaciar favoritos</button></form>
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
