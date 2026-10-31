<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/catalog.php';
$pageTitle = 'Encuentra tu perfume';
$pageDescription = 'Una guía por colección, descripción y presupuesto para explorar perfumes disponibles en el catálogo.';
$categories = db()->query('SELECT nombre, slug FROM categoria WHERE activa = 1 ORDER BY orden, nombre')->fetchAll();
$filters = catalog_filters([]);
$error = '';
$submitted = array_key_exists('buscar', $_GET);
$pageIndexable = !$submitted && !$_GET;
$pageCanonical = $pageIndexable ? 'descubrir.php' : null;
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
    <p class="store-eyebrow">TU ASESOR DE PERFUMES</p>
    <h1 id="guide-title">Encuentra tu perfume.</h1>
    <p class="store-lead">Cuéntanos qué buscas. Explora perfumes disponibles según tus preferencias y presupuesto.</p>
  </section>
  <?php if ($error): ?><div class="account-error" role="alert" tabindex="-1" data-focus-on-load><p><?= h($error) ?></p><a href="<?= h(url('descubrir.php')) ?>">Reiniciar la búsqueda</a></div><?php endif; ?>
  <form class="discovery-form guide-form" method="get" action="<?= h(url('descubrir.php')) ?>" aria-label="Preferencias de perfume" data-guide-form data-guide-start="<?= $_GET ? 'all' : 'steps' ?>">
    <input type="hidden" name="buscar" value="1">
    <div class="guide-toolbar" data-guide-toolbar hidden>
      <p class="store-eyebrow" data-guide-status>Paso 1 de 3</p>
      <button class="guide-mode" type="button" data-guide-mode>Ver todas las preferencias</button>
    </div>
    <ol class="guide-progress" aria-label="Pasos del asesor" data-guide-progress hidden>
      <li aria-current="step"><span>01</span> Colección</li><li><span>02</span> Inspiración</li><li><span>03</span> Presupuesto</li>
    </ol>
    <section class="guide-step" data-guide-step aria-labelledby="guide-step-1">
      <h2 id="guide-step-1" tabindex="-1"><span>01</span> Tu colección</h2>
      <p>Elige por dónde empezar. También puedes explorarlas todas.</p>
      <label for="guide-category">Colección de perfumes</label><select id="guide-category" name="c">
        <option value="">Todas las colecciones</option>
        <?php foreach ($categories as $category): ?><option value="<?= h($category['slug']) ?>" <?= $filters['c'] === $category['slug'] ? 'selected' : '' ?>><?= h(category_label($category['nombre'])) ?></option><?php endforeach; ?>
      </select>
    </section>
    <section class="guide-step" data-guide-step aria-labelledby="guide-step-2">
      <h2 id="guide-step-2" tabindex="-1"><span>02</span> Una palabra que te inspire</h2>
      <p>Empieza por una sensación, una marca o el nombre de un perfume.</p>
      <label for="guide-query">Qué te gustaría encontrar</label><input type="text" id="guide-query" name="q" maxlength="100" value="<?= h($filters['q']) ?>" placeholder="Por ejemplo: floral" aria-describedby="guide-query-help"><small id="guide-query-help">Buscamos el texto en nombres, marcas y descripciones.</small>
      <div class="guide-examples" aria-label="Ejemplos para buscar" data-guide-examples hidden><button type="button" data-guide-example="floral">Floral</button><button type="button" data-guide-example="fresco">Fresco</button><button type="button" data-guide-example="amaderado">Amaderado</button></div>
    </section>
    <section class="guide-step" data-guide-step aria-labelledby="guide-step-3">
      <h2 id="guide-step-3" tabindex="-1"><span>03</span> Tu presupuesto</h2>
      <p>Un punto de partida para descubrir a tu ritmo.</p>
      <label for="guide-budget">Tu presupuesto máximo (€)</label><input type="text" inputmode="decimal" id="guide-budget" name="max" value="<?= h($filters['max']) ?>" placeholder="Sin límite" aria-describedby="guide-budget-help"><small id="guide-budget-help">Opcional. Desde 0 €, con un máximo de dos decimales.</small>
    </section>
    <aside class="guide-summary" aria-labelledby="guide-summary-title" data-guide-summary hidden>
      <h3 id="guide-summary-title">Tu selección, de un vistazo</h3>
      <dl><div><dt>Colección</dt><dd data-guide-value="c"></dd></div><div><dt>Inspiración</dt><dd data-guide-value="q"></dd></div><div><dt>Presupuesto</dt><dd data-guide-value="max"></dd></div></dl>
      <p>Puedes dejar cualquier preferencia sin completar.</p>
    </aside>
    <div class="discovery-submit guide-actions">
      <button class="store-secondary" type="button" data-guide-back hidden>← Atrás</button>
      <button type="button" data-guide-next hidden>Continuar <span aria-hidden="true">→</span></button>
      <button type="submit" data-guide-submit>Ver coincidencias <span aria-hidden="true">→</span></button>
      <a href="<?= h(url('descubrir.php')) ?>">Limpiar preferencias</a>
    </div>
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
<script src="<?= asset('js/discovery.js?v=1') ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
