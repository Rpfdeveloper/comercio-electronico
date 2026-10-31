<?php // $product procede de catalog_results; compartido por portada y guía. ?>
<article class="card catalog-product">
  <a href="<?= h(url('producto.php?p=' . rawurlencode($product['slug']))) ?>">
    <?php if ($product['imagen_principal']): ?><img loading="lazy" decoding="async" width="640" height="427" src="<?= h($product['imagen_principal']) ?>" alt="<?= h($product['nombre']) ?>"><?php else: ?><div class="product-image-placeholder" role="img" aria-label="Imagen del perfume pendiente"><span>Imagen pendiente</span></div><?php endif; ?>
    <h3><?= h($product['nombre']) ?></h3>
  </a>
  <p class="catalog-brand"><?= h($product['marca']) ?></p>
  <p><?= h($product['descripcion_corta'] ?? '') ?></p>
  <?php if ($product['precio_desde'] !== null): ?><div class="price">desde <?= money_eur((float)$product['precio_desde']) ?></div><?php endif; ?>
  <span class="catalog-availability <?= $product['disponible'] ? 'available' : '' ?>"><?= $product['disponible'] ? 'Disponible' : 'Agotado' ?></span>
  <?php $favoriteProduct = $product; require __DIR__ . '/favorite-button.php'; ?>
</article>
