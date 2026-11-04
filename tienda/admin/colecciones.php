<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
admin_require();
require_once __DIR__ . '/../src/lib/admin_taxonomy.php';
$lists = []; $unavailable = false;
try { foreach (array_keys(admin_taxonomy_types()) as $type) { $lists[$type] = admin_taxonomy_list(db(), $type); } }
catch (PDOException $e) { http_response_code(503); $unavailable = true; }
$notice = $_SESSION['taxonomy_notice'] ?? ''; unset($_SESSION['taxonomy_notice']);
admin_page_start('Colecciones y marcas', true, 'colecciones');
?>
<p class="eyebrow">Catálogo / Colecciones y marcas</p>
<h1>Ordena tu escaparate.</h1>
<p class="lede">Crea y edita las colecciones que agrupan la tienda y las marcas de cada perfume. No se borran: los perfumes y los pedidos las conservan.</p>
<?php if ($notice): ?><p class="notice success" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($unavailable): ?><p class="notice" role="alert">No podemos cargar las colecciones y marcas en este momento. Inténtalo más tarde.</p><?php endif; ?>
<?php if (!$unavailable): ?>
<section aria-labelledby="collections-title">
<div class="list-header"><h2 id="collections-title">Colecciones (<?= count($lists['coleccion']) ?>)</h2><a class="button" href="coleccion.php?tipo=coleccion">+ Nueva colección</a></div>
<?php if (!$lists['coleccion']): ?><div class="empty"><h2>Aún no hay colecciones.</h2></div><?php else: ?>
<table class="orders-table taxonomy-table" aria-label="Colecciones"><thead><tr><th scope="col">Colección</th><th scope="col">Perfumes</th><th scope="col">Orden</th><th scope="col">Visibilidad</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php foreach ($lists['coleccion'] as $row): ?><tr data-taxonomy="coleccion-<?= (int)$row['id'] ?>">
<td data-label="Colección"><strong><?= h(category_label($row['nombre'])) ?></strong><small>/<?= h($row['slug']) ?></small></td>
<td data-label="Perfumes"><?= (int)$row['activos'] ?> activos de <?= (int)$row['perfumes'] ?></td>
<td data-label="Orden"><?= (int)$row['orden'] ?></td>
<td data-label="Visibilidad"><span class="badge <?= (int)$row['activa'] ? 'confirmed' : 'other' ?>"><?= (int)$row['activa'] ? 'Visible' : 'Oculta' ?></span></td>
<td data-label="Acciones"><a class="order-link" href="coleccion.php?tipo=coleccion&amp;id=<?= (int)$row['id'] ?>">Editar <span class="sr-only"><?= h(category_label($row['nombre'])) ?></span> →</a></td>
</tr><?php endforeach; ?></tbody></table><?php endif; ?>
</section>
<section aria-labelledby="brands-title">
<div class="list-header"><h2 id="brands-title">Marcas (<?= count($lists['marca']) ?>)</h2><a class="button" href="coleccion.php?tipo=marca">+ Nueva marca</a></div>
<?php if (!$lists['marca']): ?><div class="empty"><h2>Aún no hay marcas.</h2></div><?php else: ?>
<table class="orders-table taxonomy-table" aria-label="Marcas"><thead><tr><th scope="col">Marca</th><th scope="col">Perfumes</th><th scope="col">País</th><th scope="col">Enlace</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php foreach ($lists['marca'] as $row): ?><tr data-taxonomy="marca-<?= (int)$row['id'] ?>">
<td data-label="Marca"><strong><?= h($row['nombre']) ?></strong></td>
<td data-label="Perfumes"><?= (int)$row['activos'] ?> activos de <?= (int)$row['perfumes'] ?></td>
<td data-label="País"><?= $row['pais'] !== null && $row['pais'] !== '' ? h($row['pais']) : '<span class="muted">Sin indicar</span>' ?></td>
<td data-label="Enlace"><small>/<?= h($row['slug']) ?></small></td>
<td data-label="Acciones"><a class="order-link" href="coleccion.php?tipo=marca&amp;id=<?= (int)$row['id'] ?>">Editar <span class="sr-only"><?= h($row['nombre']) ?></span> →</a></td>
</tr><?php endforeach; ?></tbody></table><?php endif; ?>
</section>
<p class="muted inventory-note">Las colecciones nuevas se crean ocultas. Una colección oculta retira de la tienda todos sus perfumes, aunque estén activos. Las marcas no tienen visibilidad propia: el filtro de marcas solo muestra las que tienen perfumes visibles.</p>
<?php endif; admin_page_end(); ?>
