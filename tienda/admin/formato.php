<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php'; admin_require();
require_once __DIR__ . '/../src/lib/admin_catalog.php';
require_once __DIR__ . '/../src/lib/admin_catalog_view.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]);
if (!$id) { http_response_code(404); exit('Perfume no encontrado.'); }
$errors=[]; $conflict=''; $unavailable=false; $snapshot=null; $version='';
$values=['capacidad_ml'=>'','sku'=>'','precio'=>'','precio_oferta'=>'','stock'=>'0','activo'=>'0'];
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (($_POST['action'] ?? '') !== 'add_variant') { throw new CatalogValidation(['action'=>'La acción no es válida.']); }
            admin_catalog_write(db(),$id,$_POST);
            $_SESSION['catalog_notice']='Formato añadido. Revisa sus datos y su visibilidad.';
            header('Location: producto.php?id='.$id,true,303); exit;
        } catch (CatalogValidation $e) { $errors=$e->errors; http_response_code(422); }
        catch (CatalogConflict $e) { $conflict=$e->getMessage(); http_response_code(409); }
    }
    $snapshot=admin_catalog_read(db(),$id); $version=admin_catalog_version($snapshot);
    if ($errors) { $values=admin_catalog_overlay($values,$_POST); $version=is_string($_POST['version'] ?? null) ? $_POST['version'] : ''; }
} catch (OutOfBoundsException $e) { http_response_code(404); exit('Perfume no encontrado.'); }
catch (Throwable $e) { http_response_code(503); $unavailable=true; }
admin_page_start('Añadir formato',true,'catalogo');
?>
<p class="eyebrow">Catálogo / Formatos</p><a href="producto.php?id=<?= (int)$id ?>">← Volver al perfume</a><h1 class="section-title">Añadir formato</h1>
<?php if ($snapshot): ?><p class="lede"><?= h($snapshot['product']['nombre']) ?></p><?php endif; ?>
<?php if ($errors): ?><div class="notice" role="alert"><strong>No se ha añadido el formato.</strong><ul><?php foreach ($errors as $key=>$message): ?><li><?= h($key) ?>: <?= h($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($conflict): ?><p class="notice" role="alert"><?= h($conflict) ?> <a href="formato.php?id=<?= (int)$id ?>">Recargar datos</a></p><?php endif; ?>
<?php if ($unavailable): ?><p class="notice" role="alert">No podemos añadir formatos en este momento. No se ha guardado ningún cambio.</p><?php endif; ?>
<?php if (!$conflict && !$unavailable): ?>
<p class="notice info">Cada capacidad tiene su propio precio y stock. Al añadir el primer formato, la tienda deja de utilizar el precio y stock base; sus unidades deben ser cero. Los carritos anteriores tendrán que elegir una capacidad. La capacidad y el SKU no se podrán cambiar después.</p>
<form method="post" action="formato.php?id=<?= (int)$id ?>" class="panel catalog-editor"><?= csrf_field() ?><input type="hidden" name="action" value="add_variant"><input type="hidden" name="version" value="<?= h($version) ?>">
<div class="editor-grid"><?php admin_catalog_field('capacidad_ml','Capacidad (ml)',$values['capacidad_ml'],'integer',5,true); admin_catalog_field('sku','SKU único · opcional',$values['sku'],'text',64); admin_catalog_price_fields($values); admin_catalog_active_field($values); ?></div>
<div class="editor-actions"><button class="button" type="submit">Añadir formato</button><a href="producto.php?id=<?= (int)$id ?>">Volver sin guardar</a></div></form>
<?php endif; admin_page_end(); ?>
