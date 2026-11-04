<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
admin_require();
require_once __DIR__ . '/../src/lib/admin_images.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
$error = ''; $label = ''; $selectedProduct = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $stored = null;
    try {
        if ($action === 'upload') {
            $label = admin_catalog_text($_POST, 'nombre', 80);
            $product = $_POST['id_producto'] ?? '';
            if (!is_string($product)) { throw new ImageUploadError('Elige un perfume existente.'); }
            $selectedProduct = $product;
            $productId = $product === '' ? 0 : filter_var($product, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>2147483647]]);
            if ($productId === false) { throw new ImageUploadError('Elige un perfume existente.'); }
            if (isset($_FILES['imagen']['name']) && is_array($_FILES['imagen']['name'])) { throw new ImageUploadError('Selecciona un único archivo de imagen.'); }
            $stored = admin_images_store($_FILES['imagen'] ?? null, $label);
            if ($productId) { admin_images_assign(db(), $productId, $stored); }
            $_SESSION['images_notice'] = 'Imagen guardada' . ($productId ? ' y asignada al perfume.' : '. Ya puedes elegirla en la ficha de cualquier perfume.');
            $_SESSION['images_new'] = $stored;
        } elseif ($action === 'delete') {
            admin_images_delete(db(), $_POST['ruta'] ?? null);
            $_SESSION['images_notice'] = 'Imagen eliminada.';
        } else {
            throw new ImageUploadError('La acción no es válida.');
        }
        header('Location: imagenes.php', true, 303); exit;
    } catch (ImageUploadError | InvalidArgumentException $e) {
        // Si la asignación falla, no se deja un archivo huérfano.
        if ($stored !== null) { @unlink(__DIR__ . '/../public/' . $stored); }
        $error = $e instanceof InvalidArgumentException ? 'El nombre descriptivo admite hasta 80 caracteres de texto.' : $e->getMessage();
        http_response_code($action === 'delete' && strpos($error, 'asignada') !== false ? 409 : 422);
    } catch (PDOException $e) {
        if ($stored !== null) { @unlink(__DIR__ . '/../public/' . $stored); }
        $error = 'No se ha podido completar la operación. No se ha modificado ningún perfume.';
        http_response_code(503);
    }
}
$library = []; $products = []; $unavailable = false;
try {
    $library = admin_images_library(db());
    $products = db()->query('SELECT id,nombre FROM producto ORDER BY nombre,id')->fetchAll();
} catch (PDOException $e) { http_response_code(503); $unavailable = true; }
$formats = admin_images_formats();
$ready = isset($formats[IMAGETYPE_JPEG], $formats[IMAGETYPE_PNG]);
$notice = $_SESSION['images_notice'] ?? ''; $new = $_SESSION['images_new'] ?? ''; unset($_SESSION['images_notice'], $_SESSION['images_new']);
$accept = implode(',', array_map(static fn($ext) => ['jpg'=>'image/jpeg', 'png'=>'image/png', 'webp'=>'image/webp'][$ext], $formats));
admin_page_start('Imágenes', true, 'imagenes');
?>
<p class="eyebrow">Catálogo / Imágenes</p>
<h1>Imágenes del catálogo.</h1>
<p class="lede">Sube fotografías de los perfumes y asígnalas a su ficha. Se guardan optimizadas y sin metadatos, como la ubicación o la cámara.</p>
<?php if ($notice): ?><p class="notice success" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error): ?><div class="notice" role="alert" tabindex="-1" autofocus><strong>No se ha completado.</strong> <?= h($error) ?></div><?php endif; ?>
<?php if ($unavailable): ?><p class="notice" role="alert">No podemos cargar la biblioteca en este momento. Inténtalo más tarde.</p><?php endif; ?>
<section class="panel" aria-labelledby="upload-title">
<h2 id="upload-title">Subir una imagen</h2>
<?php if (!$ready): ?><p class="notice info">La subida necesita la extensión <strong>GD</strong> de PHP. En XAMPP, quita el <code>;</code> de la línea <code>extension=gd</code> en <code>php.ini</code> y reinicia el servidor.</p>
<?php else: ?>
<form method="post" action="imagenes.php" enctype="multipart/form-data" class="catalog-editor">
<?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="MAX_FILE_SIZE" value="<?= ADMIN_IMAGE_MAX_BYTES ?>">
<div class="editor-grid">
<div class="field"><label for="upload-file">Archivo</label><input id="upload-file" name="imagen" type="file" accept="<?= h($accept) ?>" required aria-describedby="upload-help"></div>
<div class="field"><label for="upload-name">Nombre descriptivo · opcional</label><input id="upload-name" name="nombre" type="text" maxlength="80" value="<?= h($label) ?>" placeholder="Por ejemplo, Noir Absolu frasco"></div>
<div class="field editor-wide"><label for="upload-product">Asignar a un perfume · opcional</label><select id="upload-product" name="id_producto"><option value="">Solo añadir a la biblioteca</option><?php foreach ($products as $product): ?><option value="<?= (int)$product['id'] ?>"<?= $selectedProduct === (string)$product['id'] ? ' selected' : '' ?>><?= h($product['nombre']) ?></option><?php endforeach; ?></select></div>
</div>
<p class="muted hint" id="upload-help"><?= h(strtoupper(implode(', ', $formats))) ?> · hasta 5 MB · mínimo <?= ADMIN_IMAGE_MIN_SIDE ?> píxeles por lado. Las imágenes grandes se reducen a <?= ADMIN_IMAGE_OUTPUT_SIDE ?> píxeles. Sube solo fotografías propias o con permiso de uso.</p>
<div class="editor-actions"><button class="button" type="submit">Subir imagen</button></div>
</form>
<?php endif; ?>
</section>
<?php if (!$unavailable): ?>
<div class="list-header"><h2>Biblioteca (<?= count($library) ?>)</h2><p class="muted">Primero las subidas desde el panel</p></div>
<?php if (!$library): ?><div class="empty"><h2>Todavía no hay imágenes.</h2></div><?php else: ?>
<ul class="image-library">
<?php foreach ($library as $image): ?><li class="image-tile<?= $image['path'] === $new ? ' is-new' : '' ?>" data-image="<?= h($image['path']) ?>">
<img src="../public/<?= h($image['path']) ?>" alt="" loading="lazy" width="240" height="240">
<div><strong class="word-break"><?= h($image['name']) ?></strong><small><?= $image['uploaded'] ? 'Subida desde el panel' : 'Imagen del proyecto · ' . h($image['folder']) ?> · <?= $image['uses'] ? 'en ' . $image['uses'] . ($image['uses'] === 1 ? ' perfume' : ' perfumes') : 'sin usar' ?></small>
<?php if ($image['uploaded'] && !$image['uses']): ?><form method="post" action="imagenes.php"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="ruta" value="<?= h($image['path']) ?>"><button class="button secondary" type="submit" aria-label="Eliminar <?= h($image['name']) ?>">Eliminar</button></form><?php endif; ?></div>
</li><?php endforeach; ?>
</ul><?php endif; ?>
<p class="muted inventory-note">Para cambiar la imagen de un perfume también puedes elegirla en su ficha. Solo se pueden eliminar imágenes subidas que ningún perfume utilice; las del proyecto se conservan.</p>
<?php endif; admin_page_end(); ?>
