<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
admin_require();
require_once __DIR__ . '/../src/lib/admin_taxonomy.php';
require_once __DIR__ . '/../src/lib/admin_catalog_view.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
try { $type = admin_taxonomy_type($_GET['tipo'] ?? null); }
catch (OutOfBoundsException $e) { http_response_code(404); exit('Página no encontrada.'); }
$cfg = admin_taxonomy_types()[$type];
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>2147483647]]) : 0;
if ($id === false) { http_response_code(404); exit('No encontrada.'); }
$selfUrl = 'coleccion.php?' . http_build_query(['tipo'=>$type] + ($id ? ['id'=>$id] : []), '', '&', PHP_QUERY_RFC3986);
$errors = []; $conflict = ''; $unavailable = false; $row = null; $version = '';
$values = ['nombre'=>'', 'slug'=>'', 'descripcion'=>'', 'activa'=>'0', 'orden'=>'0', 'pais'=>''];
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $savedId = admin_taxonomy_write(db(), $type, (int)$id, $_POST);
            $_SESSION['taxonomy_notice'] = $id ? 'Cambios guardados.' : ucfirst($cfg['singular']) . ' creada' . ($type === 'coleccion' ? ' como oculta. Actívala cuando tenga perfumes listos.' : '.');
            header('Location: colecciones.php', true, 303); exit;
        } catch (CatalogValidation $e) { $errors = $e->errors; http_response_code(422); }
        catch (CatalogConflict $e) { $conflict = $e->getMessage(); http_response_code(409); }
    }
    if ($id) {
        $row = admin_taxonomy_read(db(), $type, $id);
        foreach ($values as $key=>$default) { $values[$key] = (string)($row[$key] ?? $default); }
        $version = admin_taxonomy_version($type, $row);
    }
    if ($errors) {
        $values = admin_catalog_overlay($values, $_POST);
        $version = is_string($_POST['version'] ?? null) ? $_POST['version'] : '';
    }
} catch (OutOfBoundsException $e) { http_response_code(404); exit('No encontrada.'); }
catch (PDOException $e) { http_response_code(503); $unavailable = true; }
$title = ($id ? 'Editar ' : 'Nueva ') . $cfg['singular'];
admin_page_start(ucfirst($title), true, 'colecciones');
?>
<p class="eyebrow">Catálogo / Colecciones y marcas</p><a href="colecciones.php">← Volver a colecciones y marcas</a>
<div class="detail-heading"><h1><?= h(ucfirst($title)) ?></h1><?php if ($row): ?><span class="badge"><?= h($type === 'coleccion' ? category_label($row['nombre']) : $row['nombre']) ?></span><?php endif; ?></div>
<?php if ($errors): ?><div class="notice" role="alert" tabindex="-1" autofocus><strong>No se ha guardado. Revisa los datos:</strong><ul><?php foreach ($errors as $key=>$message): ?><li><a href="#edit-<?= h($key) ?>"><?= h($message) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($conflict): ?><div class="notice" role="alert"><p><?= h($conflict) ?></p><a class="button secondary" href="<?= h($selfUrl) ?>">Recargar datos actuales</a></div><?php endif; ?>
<?php if ($unavailable): ?><p class="notice" role="alert">No se puede editar en este momento. No se ha guardado ningún cambio.</p><?php endif; ?>
<?php if (!$unavailable && !$conflict): ?>
<form method="post" action="<?= h($selfUrl) ?>" class="catalog-editor">
<?= csrf_field() ?><input type="hidden" name="version" value="<?= h($version) ?>">
<section class="panel"><div class="editor-grid">
<?php admin_catalog_field('nombre', 'Nombre', $values['nombre'], 'text', 120, true); ?>
<?php if (!$id): admin_catalog_field('slug', 'Enlace · minúsculas y guiones', $values['slug'], 'text', 140, true); else: ?><div class="field"><span class="field-label">Enlace permanente</span><p class="word-break">/<?= h($values['slug']) ?></p></div><?php endif; ?>
<?php if ($type === 'coleccion'): ?>
<?php admin_catalog_field('orden', 'Orden en la tienda · menor primero', $values['orden'], 'integer', 4, true); ?>
<?php if ($id): ?><div class="field"><label for="edit-activa">Visibilidad</label><select id="edit-activa" name="activa"><option value="0"<?= $values['activa'] !== '1' ? ' selected' : '' ?>>Oculta</option><option value="1"<?= $values['activa'] === '1' ? ' selected' : '' ?>>Visible</option></select></div>
<?php if ($row && (int)$row['activa'] === 1): ?><label class="status-confirm editor-wide"><input type="checkbox" name="confirmar_ocultar" value="1"> Si la oculto, confirmo que sus perfumes dejarán de mostrarse y venderse.</label><?php endif; ?>
<?php else: ?><input type="hidden" name="activa" value="0"><?php endif; ?>
<?php else: ?>
<?php admin_catalog_field('pais', 'País · opcional', $values['pais'], 'text', 80); ?>
<?php endif; ?>
<div class="editor-wide"><?php admin_catalog_field('descripcion', 'Descripción · opcional', $values['descripcion'], 'textarea', 2000); ?></div>
</div></section>
<div class="editor-actions"><button class="button" type="submit"><?= $id ? 'Guardar cambios' : 'Crear ' . h($cfg['singular']) ?></button><a href="colecciones.php">Cancelar</a></div>
</form>
<?php endif; admin_page_end(); ?>
