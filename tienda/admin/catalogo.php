<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
admin_require();
require_once __DIR__ . '/../src/lib/admin_inventory.php';
[$filters, $errors] = admin_inventory_filters($_GET);
$items = []; $total = 0; $page = 1; $pages = 1; $unavailable = false;
if ($errors) { http_response_code(422); }
else {
    try {
        $where = []; $params = [];
        if ($filters['q'] !== '') {
            $term = '%' . strtr($filters['q'], ['!'=>'!!','%'=>'!%','_'=>'!_']) . '%';
            $where[] = "(p.nombre LIKE ? ESCAPE '!' OR m.nombre LIKE ? ESCAPE '!' OR p.slug LIKE ? ESCAPE '!')";
            $params = [$term,$term,$term];
        }
        if ($filters['visibilidad'] !== '') { $where[] = $filters['visibilidad'] === 'activa' ? '(p.activo=1 AND c.activa=1)' : '(COALESCE(p.activo=1 AND c.activa=1,0)=0)'; }
        $from = ' FROM producto p JOIN categoria c ON c.id=p.id_categoria JOIN marca m ON m.id=p.id_marca' . ($where ? ' WHERE ' . implode(' AND ',$where) : '');
        $st = db()->prepare('SELECT COUNT(*)' . $from); $st->execute($params); $total = (int)$st->fetchColumn();
        $pages = max(1,(int)ceil($total/25)); $page = min((int)$filters['pagina'],$pages);
        $st = db()->prepare('SELECT p.id,p.nombre,p.slug,p.activo,p.imagen_principal,c.activa AS categoria_activa,c.nombre AS categoria,m.nombre AS marca,
            (SELECT COUNT(*) FROM producto_variante v WHERE v.id_producto=p.id) AS formatos' . $from . ' ORDER BY p.nombre,p.id LIMIT 25 OFFSET ' . (($page-1)*25));
        $st->execute($params); $items = $st->fetchAll();
    } catch (PDOException $e) { http_response_code(503); $unavailable = true; }
}
$pageUrl = static fn(int $number) => 'catalogo.php?' . http_build_query(['q'=>$filters['q'],'visibilidad'=>$filters['visibilidad'],'pagina'=>$number]);
admin_page_start('Gestión del catálogo', true, 'catalogo');
?>
<p class="eyebrow">Operaciones / Catálogo</p>
<div class="list-header"><div><h1>Tu colección, al día.</h1><p class="lede">Crea perfumes y mantén sus fichas, precios, formatos y existencias.</p></div><a class="button" href="producto.php">+ Nuevo perfume</a></div>
<?php if ($errors): ?><p class="notice" role="alert">Revisa los filtros de búsqueda.</p><?php endif; ?>
<?php if ($unavailable): ?><p class="notice" role="alert">No podemos consultar el catálogo en este momento. Inténtalo más tarde.</p><?php endif; ?>
<section class="panel"><form class="catalog-search" method="get" action="catalogo.php" role="search" aria-label="Buscar perfumes para editar">
<div class="field"><label for="catalog-q">Nombre, marca o enlace</label><input id="catalog-q" type="search" name="q" maxlength="160" value="<?= h($filters['q']) ?>"></div>
<div class="field"><label for="catalog-state">Publicación</label><select id="catalog-state" name="visibilidad"><?php foreach ([''=>'Todos','activa'=>'Activo en una categoría activa','inactiva'=>'Inactivo o categoría desactivada'] as $value=>$label): ?><option value="<?= h($value) ?>"<?= $filters['visibilidad'] === $value ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
<button class="button" type="submit">Buscar</button><a href="catalogo.php">Limpiar</a>
</form></section>
<?php if (!$errors && !$unavailable): ?>
<div class="list-header"><h2><?= $total ?> perfumes</h2><p class="muted">25 por página · Ordenados por nombre</p></div>
<?php if (!$items): ?><div class="empty"><h2>No hay perfumes con estos filtros.</h2><p>Prueba otra búsqueda o crea una ficha nueva.</p></div><?php else: ?>
<table class="orders-table catalog-admin-table" aria-label="Perfumes del catálogo"><thead><tr><th>Perfume</th><th>Colección</th><th>Formatos</th><th>Publicación</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr data-product="<?= (int)$item['id'] ?>">
<td data-label="Perfume"><strong><?= h($item['nombre']) ?></strong><small><?= h($item['marca']) ?></small></td>
<td data-label="Colección"><?= h(category_label($item['categoria'])) ?></td>
<td data-label="Formatos"><?= (int)$item['formatos'] ? (int)$item['formatos'] . ' formatos' : 'Formato único' ?></td>
<td data-label="Publicación"><span class="badge <?= $item['activo'] && $item['categoria_activa'] ? 'confirmed' : 'other' ?>"><?= $item['activo'] && $item['categoria_activa'] ? 'Activo' : 'No visible' ?></span></td>
<td data-label="Acciones"><a class="order-link" href="producto.php?id=<?= (int)$item['id'] ?>">Editar <span class="sr-only"><?= h($item['nombre']) ?></span> →</a></td>
</tr><?php endforeach; ?></tbody></table><?php endif; ?>
<nav class="pagination" aria-label="Páginas de perfumes"><p>Página <?= $page ?> de <?= $pages ?></p><div class="links"><?php if ($page>1): ?><a class="button secondary" rel="prev" href="<?= h($pageUrl($page-1)) ?>">← Anterior</a><?php endif; ?><?php if ($page<$pages): ?><a class="button secondary" rel="next" href="<?= h($pageUrl($page+1)) ?>">Siguiente →</a><?php endif; ?></div></nav>
<p class="muted inventory-note">Los nuevos perfumes se crean inactivos. Activarlos permite mostrarlos si su categoría está activa. La disponibilidad depende de las existencias de sus formatos.</p>
<?php endif; admin_page_end(); ?>
