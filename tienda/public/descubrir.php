<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/catalog.php';
$pageTitle = 'Encuentra tu perfume';
$pageDescription = 'Una guía por colección, descripción y presupuesto para explorar perfumes disponibles en el catálogo.';
$categories = db()->query('SELECT nombre, slug FROM categoria WHERE activa = 1 ORDER BY orden, nombre')->fetchAll();
$filters = catalog_filters([]);
$error = '';
$submitted = array_key_exists('buscar', $_GET);
try {
    // Solo preferencias de la guía. Stock y ordenación no son manipulables.
    $filters = catalog_filters(array_intersect_key($_GET, array_flip(['q', 'c', 'max', 'page'])) +
        ['stock' => '1', 'orden' => 'precio-asc']);
    if ($filters['c'] !== '' && !in_array($filters['c'], array_column($categories, 'slug'), true)) {
        throw new OutOfBoundsException('Esta colección ya no está disponible. Elige otra colección.');
    }
    if ($submitted) { $result = catalog_results(db(), $filters); }
} catch (InvalidArgumentException $e) {
    http_response_code(422); $error = $e->getMessage();
} catch (OutOfBoundsException $e) {
    http_response_code(404); $error = $e->getMessage();
}
require __DIR__ . '/includes/header.php';
?>
<div class="storefront discovery-page">
  <section class="discovery-intro" aria-labelledby="guide-title">
    <p class="store-eyebrow">UNA GUÍA PARA EMPEZAR</p>
    <h1 id="guide-title">Encuentra tu perfume.</h1>
    <p class="store-lead">Tú eliges las preferencias. Nosotros buscamos coincidencias en el catálogo.</p>
  </section>
  <?php if ($error): ?><div class="account-error" role="alert" tabindex="-1" data-focus-on-load><p><?= h($error) ?></p><a href="<?= h(url('descubrir.php')) ?>">Reiniciar la búsqueda</a></div><?php endif; ?>
  <form class="discovery-form" method="get" action="<?= h(url('descubrir.php')) ?>" aria-label="Preferencias de perfume">
    <input type="hidden" name="buscar" value="1">
    <div><label for="guide-category"><span aria-hidden="true">01</span> Tu colección</label><select id="guide-category" name="c">
      <option value="">Todas las colecciones</option>
      <?php foreach ($categories as $category): ?><option value="<?= h($category['slug']) ?>" <?= $filters['c'] === $category['slug'] ? 'selected' : '' ?>><?= h(category_label($category['nombre'])) ?></option><?php endforeach; ?>
    </select></div>
    <div><label for="guide-query"><span aria-hidden="true">02</span> Una palabra que te inspire</label><input type="text" id="guide-query" name="q" maxlength="100" value="<?= h($filters['q']) ?>" placeholder="Por ejemplo: floral" aria-describedby="guide-query-help"><small id="guide-query-help">Buscamos el texto en nombres, marcas y descripciones.</small></div>
    <div><label for="guide-budget"><span aria-hidden="true">03</span> Tu presupuesto máximo (€)</label><input type="text" inputmode="decimal" id="guide-budget" name="max" value="<?= h($filters['max']) ?>" placeholder="Sin límite" aria-describedby="guide-budget-help"><small id="guide-budget-help">Puedes dejar cualquier preferencia sin completar.</small></div>
    <div class="discovery-submit"><button type="submit">Ver coincidencias <span aria-hidden="true">→</span></button><a href="<?= h(url('descubrir.php')) ?>">Limpiar preferencias</a></div>
  </form>
  <p class="store-caption">Solo perfumes disponibles, ordenados por precio. El importe corresponde a la capacidad más económica con stock.</p>
  <?php if ($submitted && !$error): ?>
    <section class="store-section discovery-results" aria-labelledby="guide-results">
      <div class="store-section-heading"><h2 id="guide-results" tabindex="-1" data-focus-on-load><?= $result['total'] ?> <?= $result['total'] === 1 ? 'coincidencia para explorar' : 'coincidencias para explorar' ?></h2><a class="store-text-link" href="<?= h(url(catalog_link('catalogo.php', $filters, 1))) ?>">Ver en el catálogo <span aria-hidden="true">→</span></a></div>
      <?php if ($result['products']): ?>
        <p>Coinciden con los filtros que has elegido. Abre cada ficha para conocer la descripción y las capacidades disponibles.</p>
        <div class="grid store-product-grid"><?php foreach ($result['products'] as $product): ?><?php require __DIR__ . '/includes/product-card.php'; ?><?php endforeach; ?></div>
        <?php if ($result['pages'] > 1): ?><nav class="catalog-pages" aria-label="Páginas de coincidencias">
          <?php if ($result['page'] > 1): ?><a rel="prev" href="<?= h(url(catalog_link('descubrir.php', $filters + ['buscar' => '1'], $result['page'] - 1))) ?>">← Anterior</a><?php endif; ?>
          <span aria-current="page">Página <?= $result['page'] ?> de <?= $result['pages'] ?></span>
          <?php if ($result['page'] < $result['pages']): ?><a rel="next" href="<?= h(url(catalog_link('descubrir.php', $filters + ['buscar' => '1'], $result['page'] + 1))) ?>">Siguiente →</a><?php endif; ?>
        </nav><?php endif; ?>
      <?php else: ?><div class="discovery-empty"><h3>No encontramos coincidencias disponibles.</h3><p>Prueba una palabra más sencilla, otra colección o un presupuesto más amplio.</p><a class="btn" href="<?= h(url('descubrir.php')) ?>">Cambiar preferencias</a></div><?php endif; ?>
    </section>
  <?php endif; ?>
  <details class="discovery-explanation"><summary>¿Cómo funciona esta guía?</summary><p>Filtra el catálogo actual por tus elecciones y busca el texto que escribas. No interpreta una conversación ni predice tus gustos. La búsqueda funciona sin registro y sin JavaScript; no envía tus preferencias a un proveedor de IA.</p></details>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
