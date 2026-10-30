<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../src/lib/catalog.php';
$categoryPage = $categoryPage ?? false;
$path = $categoryPage ? 'categoria.php' : 'catalogo.php';
$title = 'Encuentra tu perfume';
$error = '';
$category = null;
$categories = db()->query('SELECT nombre, slug FROM categoria WHERE activa = 1 ORDER BY orden, nombre')->fetchAll();
$brands = db()->query('SELECT m.nombre, m.slug FROM marca m WHERE EXISTS (
    SELECT 1 FROM producto p JOIN categoria c ON c.id = p.id_categoria
    WHERE p.id_marca = m.id AND p.activo = 1 AND c.activa = 1) ORDER BY m.nombre')->fetchAll();
try {
    $filters = catalog_filters($_GET);
    if ($categoryPage && $filters['c'] === '') { throw new OutOfBoundsException('Categoría no encontrada.'); }
    if ($filters['c'] !== '') {
        foreach ($categories as $item) { if ($item['slug'] === $filters['c']) { $category = $item; break; } }
        if ($category === null) { throw new OutOfBoundsException('Categoría no encontrada.'); }
    }
    if ($filters['marca'] !== '' && !in_array($filters['marca'], array_column($brands, 'slug'), true)) {
        throw new OutOfBoundsException('Marca no encontrada.');
    }
    if ($categoryPage) { $title = $category['nombre']; }
    $result = catalog_results(db(), $filters);
} catch (InvalidArgumentException $e) {
    http_response_code(422); $error = $e->getMessage();
} catch (OutOfBoundsException $e) {
    http_response_code(404); $error = $e->getMessage();
}
require __DIR__ . '/header.php';
?>
<section class="catalog" aria-labelledby="catalog-title">
  <p class="account-eyebrow">CATÁLOGO DE PERFUMES</p>
  <h2 id="catalog-title"><?= h($title) ?></h2>
  <?php if ($error !== ''): ?>
    <p class="account-error" role="alert"><?= h($error) ?></p>
    <a href="<?= h(url('catalogo.php')) ?>">Volver al catálogo</a>
  <?php else: ?>
    <p class="catalog-intro">Busca por nombre, marca o descripción y elige tu próxima fragancia.</p>
    <form class="catalog-filters" method="get" action="<?= h(url($path)) ?>" role="search" aria-label="Buscar perfumes">
      <div class="catalog-search"><label for="catalog-q">Buscar perfume</label>
        <input type="text" id="catalog-q" name="q" maxlength="100" value="<?= h($filters['q']) ?>" placeholder="Nombre, marca o descripción">
      </div>
      <?php if ($categoryPage): ?>
        <input type="hidden" name="c" value="<?= h($filters['c']) ?>">
      <?php else: ?>
        <div><label for="catalog-category">Categoría</label><select id="catalog-category" name="c">
          <option value="">Todas las categorías</option>
          <?php foreach ($categories as $item): ?><option value="<?= h($item['slug']) ?>" <?= $filters['c'] === $item['slug'] ? 'selected' : '' ?>><?= h($item['nombre']) ?></option><?php endforeach; ?>
        </select></div>
      <?php endif; ?>
      <div><label for="catalog-brand">Marca</label><select id="catalog-brand" name="marca">
        <option value="">Todas las marcas</option>
        <?php foreach ($brands as $item): ?><option value="<?= h($item['slug']) ?>" <?= $filters['marca'] === $item['slug'] ? 'selected' : '' ?>><?= h($item['nombre']) ?></option><?php endforeach; ?>
      </select></div>
      <div><label for="catalog-min">Precio mínimo (€)</label><input id="catalog-min" name="min" type="text" inputmode="decimal" value="<?= h($filters['min']) ?>" placeholder="0,00"></div>
      <div><label for="catalog-max">Precio máximo (€)</label><input id="catalog-max" name="max" type="text" inputmode="decimal" value="<?= h($filters['max']) ?>" placeholder="Sin límite"></div>
      <div><label for="catalog-sort">Ordenar por</label><select id="catalog-sort" name="orden">
        <?php foreach (catalog_orders() as $key => $label): ?><option value="<?= h($key) ?>" <?= $filters['orden'] === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
      </select></div>
      <label class="catalog-stock"><input type="checkbox" name="stock" value="1" <?= $filters['stock'] === '1' ? 'checked' : '' ?>> Solo disponibles</label>
      <div class="catalog-controls"><button type="submit">Aplicar filtros</button>
        <a href="<?= h(url($path . ($categoryPage ? '?c=' . rawurlencode($filters['c']) : ''))) ?>">Limpiar filtros</a>
      </div>
    </form>
    <p class="catalog-price-help">El precio «desde» corresponde a la capacidad más económica con stock. Si todas están agotadas, se muestra el menor precio de sus capacidades activas. Los filtros de precio se aplican a ese importe.</p>
    <div class="catalog-summary"><p role="status"><strong><?= $result['total'] ?></strong> <?= $result['total'] === 1 ? 'perfume encontrado' : 'perfumes encontrados' ?></p>
      <?php if ($result['total'] > 0): ?><p>Página <?= $result['page'] ?> de <?= $result['pages'] ?></p><?php endif; ?>
    </div>
    <?php if (!$result['products']): ?>
      <div class="message-box"><h3>No hay perfumes con estos filtros</h3><p>Prueba con otra búsqueda o amplía el intervalo de precios.</p></div>
    <?php else: ?>
      <div class="grid catalog-grid">
        <?php foreach ($result['products'] as $product): ?>
          <article class="card catalog-product">
            <a href="<?= h(url('producto.php?p=' . rawurlencode($product['slug']))) ?>">
              <?php if ($product['imagen_principal']): ?><img loading="lazy" src="<?= h($product['imagen_principal']) ?>" alt="<?= h($product['nombre']) ?>"><?php endif; ?>
              <h3><?= h($product['nombre']) ?></h3>
            </a>
            <p class="catalog-brand"><?= h($product['marca']) ?></p>
            <p><?= h($product['descripcion_corta'] ?? '') ?></p>
            <?php if ($product['precio_desde'] !== null): ?><div class="price">desde <?= number_format((float)$product['precio_desde'], 2, ',', '.') ?> €</div><?php endif; ?>
            <span class="catalog-availability <?= $product['disponible'] ? 'available' : '' ?>"><?= $product['disponible'] ? 'Disponible' : 'Agotado' ?></span>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ($result['pages'] > 1): ?><nav class="catalog-pages" aria-label="Páginas del catálogo">
        <?php if ($result['page'] > 1): ?><a rel="prev" href="<?= h(url(catalog_link($path, $filters, $result['page'] - 1))) ?>">← Anterior</a><?php endif; ?>
        <span aria-current="page">Página <?= $result['page'] ?> de <?= $result['pages'] ?></span>
        <?php if ($result['page'] < $result['pages']): ?><a rel="next" href="<?= h(url(catalog_link($path, $filters, $result['page'] + 1))) ?>">Siguiente →</a><?php endif; ?>
      </nav><?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
