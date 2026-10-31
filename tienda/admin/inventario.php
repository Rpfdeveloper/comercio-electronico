<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
admin_require();
require_once __DIR__ . '/../src/lib/admin_inventory.php';
[$filters, $errors] = admin_inventory_filters($_GET);
$result = null;
$unavailable = false;
if ($errors) { http_response_code(422); }
else {
    try {
        $result = admin_inventory_search(db(), $filters);
        $filters['pagina'] = (string)$result['page'];
    } catch (PDOException $e) { http_response_code(503); $unavailable = true; }
}
$fieldError = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="error-' . $name . '"' : '';
};
admin_page_start('Inventario', true, 'inventario');
?>
<p class="eyebrow">Operaciones / Inventario</p>
<h1>El stock, bajo control.</h1>
<p class="lede">Consulta cada formato, detecta qué se está agotando y prepara tu próxima reposición.</p>
<?php if ($errors): ?><div class="notice" role="alert"><strong>Revisa los filtros para continuar.</strong><ul>
<?php foreach ($errors as $key=>$message): ?><li><a href="#<?= $key === 'pagina' ? 'filtros' : 'filter-' . h($key) ?>"><?= h($message) ?></a></li><?php endforeach; ?>
</ul></div><?php endif; ?>
<?php if ($unavailable): ?><div class="notice" role="alert">No podemos consultar el inventario en este momento. Inténtalo de nuevo más tarde.</div><?php endif; ?>
<?php if ($result !== null): $totals = $result['totals']; ?>
<section class="stats" aria-label="Resumen del inventario filtrado">
  <div class="stat highlight"><span>Referencias encontradas</span><strong><?= (int)$totals['cantidad'] ?></strong></div>
  <div class="stat"><span>Unidades registradas</span><strong><?= (int)$totals['unidades'] ?></strong></div>
  <div class="stat"><span>Stock bajo · 1 a 5</span><strong><?= (int)$totals['bajo'] ?></strong></div>
  <div class="stat"><span>Referencias agotadas</span><strong><?= (int)$totals['agotado'] ?></strong></div>
</section>
<?php if ((int)$totals['revision'] > 0): ?><p class="notice" role="status"><?= (int)$totals['revision'] ?> referencias tienen stock sin registrar o negativo. Revisa sus datos; no se suman a las unidades registradas.</p><?php endif; ?>
<?php endif; ?>
<section class="panel" id="filtros" aria-labelledby="filter-title">
<h2 id="filter-title">Encuentra una referencia</h2>
<form method="get" action="inventario.php" class="filters" role="search" aria-label="Buscar inventario">
  <div class="field"><label for="filter-q">Perfume, marca o SKU</label><input id="filter-q" name="q" type="search" maxlength="160" autocomplete="off" value="<?= h($filters['q']) ?>"<?= $fieldError('q') ?>><?php if (isset($errors['q'])): ?><p class="error-text" id="error-q"><?= h($errors['q']) ?></p><?php endif; ?></div>
  <?php foreach (['stock'=>['Existencias', [''=>'Todas', 'agotado'=>'Agotado · 0', 'bajo'=>'Stock bajo · 1 a 5', 'disponible'=>'Disponible · más de 5', 'revision'=>'Datos por revisar']], 'visibilidad'=>['Estado en tienda', [''=>'Todos', 'activa'=>'Activa', 'inactiva'=>'Inactiva']], 'orden'=>['Ordenar por', ['stock'=>'Menor stock primero', 'nombre'=>'Nombre: A–Z', 'stock_mayor'=>'Mayor stock primero']]] as $key=>[$label, $options]): ?>
  <div class="field"><label for="filter-<?= $key ?>"><?= $label ?></label><select id="filter-<?= $key ?>" name="<?= $key ?>"<?= $fieldError($key) ?>><?php foreach ($options as $value=>$option): ?><option value="<?= h((string)$value) ?>"<?= $filters[$key] === (string)$value ? ' selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select><?php if (isset($errors[$key])): ?><p class="error-text" id="error-<?= $key ?>"><?= h($errors[$key]) ?></p><?php endif; ?></div>
  <?php endforeach; ?>
  <div class="filter-actions"><a href="inventario.php">Limpiar filtros</a><button class="button" type="submit">Aplicar filtros</button></div>
</form>
</section>
<?php if ($result !== null): ?>
<div class="list-header"><h2>Referencias</h2><p class="muted"><?= (int)$totals['cantidad'] ? $result['offset'] + 1 : 0 ?>–<?= $result['offset'] + count($result['items']) ?> de <?= (int)$totals['cantidad'] ?> referencias</p></div>
<?php if (!$result['items']): ?><div class="empty"><h2>No hay referencias con estos filtros.</h2><p class="muted">Prueba con otro perfume, marca o nivel de existencias.</p><a class="button secondary" href="inventario.php">Ver todo el inventario</a></div>
<?php else: ?>
<table class="orders-table inventory-table" aria-label="Inventario encontrado">
<thead><tr><th scope="col">Perfume</th><th scope="col">Formato / SKU</th><th scope="col">Precio</th><th scope="col">Unidades</th><th scope="col">Existencias</th><th scope="col">En tienda</th></tr></thead><tbody>
<?php foreach ($result['items'] as $item): [$stockLabel, $stockClass] = admin_inventory_stock($item['existencias']); ?>
<tr data-reference="<?= (int)$item['producto_id'] ?>-<?= (int)$item['variante_id'] ?>">
  <td data-label="Perfume"><strong><a href="producto.php?id=<?= (int)$item['producto_id'] ?>"><?= h($item['nombre']) ?></a></strong><small><?= h($item['marca']) ?> · <?= h($item['categoria']) ?></small></td>
  <td data-label="Formato / SKU"><strong><?= $item['variante_id'] === null ? 'Formato base' : (int)$item['capacidad_ml'] . ' ml' ?></strong><small><?= $item['sku'] === null || $item['sku'] === '' ? 'Sin SKU' : h($item['sku']) ?></small></td>
  <td data-label="Precio"><?= h(money_eur((float)$item['precio'])) ?></td>
  <td data-label="Unidades"><strong><?= $item['existencias'] === null ? 'Sin dato' : (int)$item['existencias'] ?></strong></td>
  <td data-label="Existencias"><span class="badge <?= $stockClass ?>"><?= $stockLabel ?></span></td>
  <td data-label="En tienda"><span class="badge <?= (int)$item['activa'] ? 'confirmed' : 'other' ?>"><?= (int)$item['activa'] ? 'Activa' : 'Inactiva' ?></span></td>
</tr>
<?php endforeach; ?></tbody></table>
<?php endif; ?>
<nav class="pagination" aria-label="Páginas de inventario"><p>Página <?= $result['page'] ?> de <?= $result['pages'] ?></p><div class="links">
<?php if ($result['page'] > 1): ?><a class="button secondary" rel="prev" href="<?= h(admin_inventory_url($filters, $result['page'] - 1)) ?>">← Anterior</a><?php endif; ?>
<?php if ($result['page'] < $result['pages']): ?><a class="button secondary" rel="next" href="<?= h(admin_inventory_url($filters, $result['page'] + 1)) ?>">Siguiente →</a><?php endif; ?>
</div></nav>
<p class="muted inventory-note">Cada referencia es un formato del perfume. El resumen abarca todos los resultados filtrados, incluidas las referencias inactivas cuando se muestran. «Activa» requiere producto, categoría y variante activos; no garantiza existencias. Abre el nombre de un perfume para editar su ficha y sus formatos.</p>
<?php endif; ?>
<?php admin_page_end(); ?>
