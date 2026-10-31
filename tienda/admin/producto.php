<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
admin_require();
require_once __DIR__ . '/../src/lib/admin_catalog.php';
require_once __DIR__ . '/../src/lib/admin_catalog_view.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]) : 0;
if ($id === false) { http_response_code(404); exit('Perfume no encontrado.'); }
$errors = []; $conflict = ''; $unavailable = false; $snapshot = null; $history = []; $categories = []; $brands = [];
$values = ['nombre'=>'','slug'=>'','id_categoria'=>'','id_marca'=>'','descripcion_corta'=>'','descripcion_larga'=>'','imagen_principal'=>'','precio'=>'','precio_oferta'=>'','stock'=>'0','activo'=>'0'];
$version = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (($_POST['action'] ?? '') !== ($id ? 'save' : 'create')) { throw new CatalogValidation(['action'=>'La acción no es válida.']); }
            $savedId = admin_catalog_write(db(), (int)$id, $_POST);
            $_SESSION['catalog_notice'] = $id ? 'Cambios guardados. El catálogo ya utiliza los datos actualizados.' : 'Perfume creado como inactivo. Revísalo antes de activarlo.';
            header('Location: producto.php?id=' . $savedId, true, 303); exit;
        } catch (CatalogValidation $e) { $errors = $e->errors; http_response_code(422); }
        catch (InvalidArgumentException $e) { $errors = ['datos'=>$e->getMessage()]; http_response_code(422); }
        catch (CatalogConflict $e) { $conflict = $e->getMessage(); http_response_code(409); }
    }
    if ($id) {
        $snapshot = admin_catalog_read(db(), $id);
        $values = $snapshot['product']; $version = admin_catalog_version($snapshot);
        $st = db()->prepare('SELECT accion,cambios,fecha FROM catalogo_cambio WHERE id_producto=? ORDER BY id DESC LIMIT 20'); $st->execute([$id]); $history = $st->fetchAll();
    }
    if ($errors) { $values = admin_catalog_overlay($values, $_POST); $version = is_string($_POST['version'] ?? null) ? $_POST['version'] : ''; }
    $categories = db()->query('SELECT id,nombre,activa FROM categoria ORDER BY orden,nombre')->fetchAll();
    $brands = db()->query('SELECT id,nombre FROM marca ORDER BY nombre')->fetchAll();
} catch (OutOfBoundsException $e) { http_response_code(404); exit('Perfume no encontrado.'); }
catch (Throwable $e) { http_response_code(503); $unavailable = true; }
$notice = $_SESSION['catalog_notice'] ?? ''; unset($_SESSION['catalog_notice']);
admin_page_start($id ? 'Editar perfume' : 'Nuevo perfume', true, 'catalogo');
?>
<p class="eyebrow">Catálogo / <?= $id ? 'Edición' : 'Alta' ?></p><a href="catalogo.php">← Volver al catálogo</a>
<div class="detail-heading"><h1><?= $id ? 'Editar perfume' : 'Nuevo perfume' ?></h1><?php if ($id && $snapshot): ?><span class="badge"><?= h($snapshot['product']['nombre']) ?></span><?php endif; ?></div>
<?php if ($notice): ?><p class="notice success" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($errors): ?><div class="notice" role="alert" tabindex="-1" autofocus><strong>No se ha guardado. Revisa los datos:</strong><ul><?php foreach ($errors as $key=>$message): ?><li><?= h($key) ?>: <?= h($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($conflict): ?><div class="notice" role="alert"><p><?= h($conflict) ?></p><a class="button secondary" href="producto.php?id=<?= (int)$id ?>">Recargar datos actuales</a></div><?php endif; ?>
<?php if ($unavailable): ?><p class="notice" role="alert">La edición del catálogo no está disponible. No se ha guardado ningún cambio. Inténtalo más tarde.</p><?php endif; ?>
<?php if (!$unavailable && !$conflict): ?>
<p class="lede"><?= $id ? 'Los cambios se aplican a la tienda al guardar. El enlace del perfume y los pedidos anteriores se conservan.' : 'La ficha se creará inactiva para que puedas revisarla antes de mostrarla en la tienda.' ?></p>
<form method="post" action="producto.php<?= $id ? '?id=' . (int)$id : '' ?>" class="catalog-editor">
<?= csrf_field() ?><input type="hidden" name="action" value="<?= $id ? 'save' : 'create' ?>"><input type="hidden" name="version" value="<?= h($version) ?>">
<section class="panel"><h2>Ficha del perfume</h2><div class="editor-grid">
<?php admin_catalog_field('nombre','Nombre del perfume',$values['nombre'],'text',180,true); ?>
<?php if (!$id): admin_catalog_field('slug','Enlace · minúsculas y guiones',$values['slug'],'text',200,true); else: ?><div class="field"><span class="field-label">Enlace permanente</span><p class="word-break"><?= h($values['slug']) ?></p></div><?php endif; ?>
<?php foreach (['id_categoria'=>['Colección',$categories],'id_marca'=>['Marca',$brands]] as $key=>[$label,$options]): ?><div class="field"><label for="edit-<?= $key ?>"><?= $label ?></label><select id="edit-<?= $key ?>" name="<?= $key ?>" required><option value="">Selecciona una opción</option><?php foreach ($options as $option): ?><option value="<?= (int)$option['id'] ?>"<?= (string)$values[$key] === (string)$option['id'] ? ' selected' : '' ?>><?= h($key === 'id_categoria' ? category_label($option['nombre']) : $option['nombre']) ?><?= array_key_exists('activa',$option) && !$option['activa'] ? ' · inactiva' : '' ?></option><?php endforeach; ?></select></div><?php endforeach; ?>
<?php admin_catalog_field('descripcion_corta','Descripción breve',$values['descripcion_corta'],'text',255); ?>
<div class="field"><label for="edit-image">Imagen del proyecto</label><select id="edit-image" name="imagen_principal"><option value="">Sin imagen · pendiente</option><?php foreach (admin_catalog_images() as $image): ?><option value="<?= h($image) ?>"<?= $values['imagen_principal'] === $image ? ' selected' : '' ?>><?= h(basename($image)) ?></option><?php endforeach; ?></select><p class="muted hint">Selecciona solo una imagen que corresponda a este perfume.</p></div>
<div class="editor-wide"><?php admin_catalog_field('descripcion_larga','Descripción completa',$values['descripcion_larga'],'textarea',10000); ?></div>
<?php if ($id): admin_catalog_active_field($values); else: ?><input type="hidden" name="activo" value="0"><?php endif; ?>
</div></section>
<?php if (!$snapshot || !$snapshot['variants']): ?><section class="panel"><h2>Formato único</h2><p class="muted">El precio y las existencias corresponden al perfume sin variantes. Deja la oferta vacía para retirarla.</p><div class="editor-grid"><?php admin_catalog_price_fields($values); ?></div></section>
<?php else: ?><section class="panel" id="variants"><h2>Formatos y existencias</h2><p class="muted">Se usa el precio y el stock de cada formato. Los valores base del producto no se suman. Deja una oferta vacía para retirarla.</p>
<?php foreach ($snapshot['variants'] as $variant): $v = $variant; if ($errors && isset($_POST['variants'][$variant['id']]) && is_array($_POST['variants'][$variant['id']])) { $v = admin_catalog_overlay($v,$_POST['variants'][$variant['id']]); } $prefix = 'variants[' . (int)$variant['id'] . ']'; ?>
<fieldset class="variant-editor"><legend><?= (int)$variant['capacidad_ml'] ?> ml · <?= $variant['sku'] ? h($variant['sku']) : 'Sin SKU' ?></legend>
<input type="hidden" name="<?= h($prefix) ?>[capacidad_ml]" value="<?= (int)$variant['capacidad_ml'] ?>"><input type="hidden" name="<?= h($prefix) ?>[sku]" value="<?= h((string)$variant['sku']) ?>">
<div class="editor-grid"><?php admin_catalog_price_fields($v,$prefix); admin_catalog_active_field($v,$prefix); ?></div></fieldset>
<?php endforeach; ?></section><?php endif; ?>
<div class="editor-actions"><button class="button" type="submit"><?= $id ? 'Guardar cambios' : 'Crear perfume inactivo' ?></button><a class="button secondary" href="catalogo.php">Volver sin guardar</a></div>
</form>
<?php if ($id): ?><section class="panel"><h2>Más opciones</h2><div class="editor-actions"><a class="button secondary" href="formato.php?id=<?= (int)$id ?>">Añadir formato</a><?php if ($snapshot['product']['activo']): ?><a href="../public/producto.php?p=<?= rawurlencode($snapshot['product']['slug']) ?>">Ver ficha en la tienda ↗</a><?php endif; ?></div><p class="muted hint">Guarda los cambios de la ficha antes de añadir un formato. Para retirar un perfume, cambia su visibilidad a Inactivo; conserva así su historial.</p></section>
<section class="panel"><h2>Historial de cambios</h2><p class="muted">Últimos 20 cambios guardados desde el panel. Los movimientos de compra se consultan en los pedidos.</p>
<?php if (!$history): ?><p>Todavía no hay cambios registrados desde el panel.</p><?php endif; ?>
<?php foreach ($history as $entry): ?><details class="catalog-history"><summary><?= h($entry['fecha']) ?> · <?= h(['create'=>'Perfume creado','save'=>'Ficha actualizada','add_variant'=>'Formato añadido'][$entry['accion']] ?? 'Cambio de catálogo') ?> · admin</summary>
<dl><?php foreach ((json_decode($entry['cambios'],true) ?: []) as $change): ?><div><dt><?= h($change['campo']) ?></dt><dd><span>Antes:</span> <?= h($change['antes'] === null || $change['antes'] === '' ? 'Sin dato' : (string)$change['antes']) ?><br><span>Después:</span> <?= h($change['despues'] === null || $change['despues'] === '' ? 'Sin dato' : (string)$change['despues']) ?></dd></div><?php endforeach; ?></dl></details><?php endforeach; ?></section>
<?php endif; endif; admin_page_end(); ?>
