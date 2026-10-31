<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/catalog.php';
$pageTitle = 'Encuentra una fragancia que hable de ti';
$pageDescription = 'Explora el catálogo de Perfume Shop y encuentra fragancias por colección, descripción y presupuesto.';
$pageCanonical = 'index.php';
$pageIndexable = true;
$cats = db()->query('SELECT c.nombre, c.slug, COUNT(p.id) AS productos
    FROM categoria c LEFT JOIN producto p ON p.id_categoria = c.id AND p.activo = 1
    WHERE c.activa = 1 GROUP BY c.id, c.nombre, c.slug, c.orden
    ORDER BY c.orden, c.nombre')->fetchAll();
$featured = catalog_results(db(), catalog_filters(['stock' => '1', 'orden' => 'recientes']));
require __DIR__ . '/includes/header.php';
?>
<div class="storefront">
  <section class="store-hero" aria-labelledby="home-title">
    <div class="store-hero-copy">
      <p class="store-eyebrow">EL ARTE DE DESCUBRIR</p>
      <h1 id="home-title">Encuentra una fragancia que hable de ti.</h1>
      <p class="store-lead">Explora perfumes de mujer, hombre y unisex, a tu ritmo.</p>
      <div class="store-actions">
        <a class="btn" href="<?= h(url('catalogo.php')) ?>">Explorar catálogo</a>
        <a class="btn store-secondary" href="<?= h(url('descubrir.php')) ?>">Probar asesor</a>
      </div>
      <p class="store-caption">Una guía por preferencias para descubrir tu próxima fragancia.</p>
    </div>
    <figure class="store-hero-image">
      <img src="https://images.pexels.com/photos/965989/pexels-photo-965989.jpeg?auto=compress&amp;cs=tinysrgb&amp;w=1280" width="1280" height="853" fetchpriority="high" referrerpolicy="no-referrer" alt="Composición de frascos de perfume sobre una superficie clara" data-image-fallback="<?= h(asset('img/products/Mujer/aurora-blossom-edp.jpg')) ?>" data-fallback-alt="Frasco de perfume junto a flores en tonos suaves">
      <figcaption>Una fragancia. Muchas formas de ser tú.</figcaption>
    </figure>
  </section>
  <section class="store-section" aria-labelledby="collections-title">
    <div class="store-section-heading"><div><p class="store-eyebrow">EXPLORA A TU MANERA</p><h2 id="collections-title">Elige por dónde empezar.</h2></div><a class="store-text-link" href="<?= h(url('catalogo.php')) ?>">Ver todo el catálogo <span aria-hidden="true">→</span></a></div>
    <div class="store-collections">
      <?php foreach ($cats as $c): ?>
        <a class="store-collection" href="<?= h(url('categoria.php?c=' . rawurlencode($c['slug']))) ?>">
          <span class="store-collection-copy"><span><strong><?= h(category_label($c['nombre'])) ?></strong><small><?= (int)$c['productos'] ?> <?= (int)$c['productos'] === 1 ? 'perfume en catálogo' : 'perfumes en catálogo' ?></small></span><span aria-hidden="true">↗</span></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if (!$cats): ?><p class="message-box">Estamos preparando las colecciones. Vuelve a visitarnos pronto.</p><?php endif; ?>
  </section>
  <section class="store-discovery" aria-labelledby="discovery-title">
    <p class="store-eyebrow">TU PRÓXIMO DESCUBRIMIENTO</p>
    <h2 id="discovery-title">¿Por dónde empezar?</h2>
    <p>Elige una colección, una palabra que describa lo que buscas y tu presupuesto. Te mostramos las coincidencias disponibles en el catálogo.</p>
    <a class="btn" href="<?= h(url('descubrir.php')) ?>">Abrir asesor <span aria-hidden="true">→</span></a>
  </section>
  <section class="store-section favorites-invitation" aria-labelledby="save-title">
    <div><p class="store-eyebrow">DESCUBRE HOY. ELIGE A TU RITMO.</p><h2 id="save-title">Una colección que empieza contigo.</h2><p>Guarda lo que te gusta. Retómalo en este navegador o inicia sesión para llevar tu colección contigo.</p></div>
    <a class="store-text-link" href="<?= h(url('favoritos.php')) ?>">Mi colección de favoritos <span aria-hidden="true">♡</span></a>
  </section>
  <section class="store-section" aria-labelledby="latest-title">
    <div class="store-section-heading"><div><p class="store-eyebrow">SIGUE DESCUBRIENDO</p><h2 id="latest-title">Últimas incorporaciones.</h2></div><a class="store-text-link" href="<?= h(url('catalogo.php?stock=1&orden=recientes')) ?>">Ver disponibles <span aria-hidden="true">→</span></a></div>
    <?php if ($featured['products']): ?>
      <div class="grid store-product-grid">
        <?php foreach (array_slice($featured['products'], 0, 4) as $product): ?>
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
      <p class="store-caption">Precio desde la capacidad más económica disponible. Consulta las opciones en cada ficha.</p>
    <?php else: ?><p class="message-box">Ahora mismo no hay perfumes disponibles. Puedes seguir explorando las colecciones.</p><?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
