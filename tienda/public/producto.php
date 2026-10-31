<?php
require_once __DIR__ . '/../config/init.php';
$slug = $_GET['p'] ?? '';
$prod = false;
if (is_string($slug) && strlen($slug) <= 200 && preg_match('//u', $slug)) {
    $st = db()->prepare('SELECT p.id, p.nombre, p.slug, p.descripcion_corta, p.descripcion_larga, p.stock,
        c.nombre AS categoria, c.slug AS categoria_slug, m.nombre AS marca,
        CASE WHEN p.precio_oferta > 0 THEN p.precio_oferta ELSE p.precio END AS precio_base,
        p.imagen_principal, (SELECT COUNT(*) FROM producto_variante v WHERE v.id_producto = p.id) AS variantes
        FROM producto p JOIN categoria c ON c.id = p.id_categoria JOIN marca m ON m.id = p.id_marca
        WHERE p.slug = ? AND p.activo = 1 AND c.activa = 1 LIMIT 1');
    $st->execute([$slug]);
    $prod = $st->fetch();
}
if (!$prod) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    echo '<p>Producto no encontrado</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$st = db()->prepare('SELECT id, capacidad_ml,
    CASE WHEN precio_oferta > 0 THEN precio_oferta ELSE precio END AS precio, stock
    FROM producto_variante WHERE id_producto = ? AND activo = 1
    ORDER BY stock > 0 DESC, precio, capacidad_ml, id');
$st->execute([$prod['id']]);
$vars = $st->fetchAll();
$available = $prod['variantes'] ? count(array_filter($vars, static function ($v) { return $v['stock'] > 0; })) > 0 : $prod['stock'] > 0;
$pageTitle = $prod['nombre'] . ' · ' . $prod['marca'];
$pageDescription = seo_description($prod['nombre'] . '. ' . ($prod['descripcion_larga'] ?: $prod['descripcion_corta'] ?: 'Consulta capacidades, precio y disponibilidad.'));
$pageCanonical = 'producto.php?p=' . rawurlencode($prod['slug']);
$pageIndexable = true;
require __DIR__ . '/includes/header.php';
?>
<p><a href="<?= h(url('catalogo.php')) ?>">← Volver al catálogo</a></p>
<nav class="product-breadcrumb" aria-label="Ruta de navegación"><a href="<?= h(url('index.php')) ?>">Inicio</a><span aria-hidden="true"> / </span><a href="<?= h(url('categoria.php?c=' . rawurlencode($prod['categoria_slug']))) ?>"><?= h(category_label($prod['categoria'])) ?></a><span aria-hidden="true"> / </span><span aria-current="page"><?= h($prod['nombre']) ?></span></nav>
<?php
$selectedFormat = $vars[0] ?? null;
$shownPrice = $selectedFormat ? $selectedFormat['precio'] : (!$prod['variantes'] ? $prod['precio_base'] : null);
$shownStock = $selectedFormat ? (int)$selectedFormat['stock'] : (!$prod['variantes'] ? (int)$prod['stock'] : 0);
?>
<article class="product-showcase" aria-labelledby="product-title">
  <div class="product-visual">
    <?php if ($prod['imagen_principal']): ?><img src="<?= h($prod['imagen_principal']) ?>" alt="<?= h($prod['nombre']) ?>" width="640" height="640" fetchpriority="high">
    <?php else: ?><div class="product-image-placeholder" role="img" aria-label="Imagen del perfume pendiente"><span>Imagen pendiente</span></div><?php endif; ?>
  </div>
  <div class="product-information">
    <p class="store-eyebrow"><?= h($prod['marca']) ?> · <?= h(category_label($prod['categoria'])) ?></p>
    <h1 id="product-title"><?= h($prod['nombre']) ?></h1>
    <?php if ($prod['descripcion_corta']): ?><p class="product-lead"><?= h($prod['descripcion_corta']) ?></p><?php endif; ?>
    <?php if ($shownPrice !== null): ?><p class="product-display-price" data-product-price aria-live="polite"><?= money_eur((float)$shownPrice) ?></p><p class="store-caption">Precio antes de IVA. Consulta el total en el carrito.</p><?php endif; ?>
    <?php if (!$available): ?><p class="account-error" role="status">Agotado. Este perfume no tiene unidades disponibles.</p><?php else: ?><p class="product-stock" data-product-stock><?= $shownStock ?> unidades disponibles en este formato.</p><?php endif; ?>
    <form method="post" action="carrito.php" class="account-form product-purchase" data-product-purchase>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="id_producto" value="<?= (int)$prod['id'] ?>">
      <?php if ($vars): ?>
        <label for="var">Capacidad</label>
        <select id="var" name="id_variante" <?= $available ? '' : 'disabled' ?> data-product-variant>
          <?php foreach ($vars as $v): ?><option value="<?= (int)$v['id'] ?>" data-price="<?= h(money_eur((float)$v['precio'])) ?>" data-stock="<?= (int)$v['stock'] ?>" <?= $v['stock'] > 0 ? '' : 'disabled' ?>><?= (int)$v['capacidad_ml'] ?> ml — <?= number_format((float)$v['precio'], 2, ',', '.') ?> €<?= $v['stock'] > 0 ? '' : ' — Agotado' ?></option><?php endforeach; ?>
        </select>
      <?php endif; ?>
      <label for="qty">Cantidad</label>
      <input type="number" id="qty" name="cantidad" min="1" max="999" value="1" <?= $available ? '' : 'disabled' ?>>
      <button type="submit" <?= $available ? '' : 'disabled' ?>>Añadir al carrito <span aria-hidden="true">→</span></button>
    </form>
    <?php $favoriteProduct = $prod; require __DIR__ . '/includes/favorite-button.php'; ?>
    <p class="store-caption">Guardar en favoritos te permite volver a esta ficha. No reserva existencias.</p>
    <div class="product-context"><span>Catálogo del proyecto</span><span>Sin pagos reales</span></div>
  </div>
  <section class="product-description" aria-labelledby="description-title"><h2 id="description-title">Conoce este perfume.</h2>
    <?php if ($prod['descripcion_larga']): ?><p><?= nl2br(h($prod['descripcion_larga'])) ?></p><?php else: ?><p>La descripción completa está pendiente. No hay más información publicada sobre esta fragancia.</p><?php endif; ?>
  </section>
  <aside class="product-help"><h2>Elige a tu ritmo.</h2><p>Explora el catálogo con una colección, una palabra o tu presupuesto.</p><a href="<?= h(url('descubrir.php')) ?>">Abrir el asesor de perfumes →</a></aside>
</article>
<script src="<?= asset('js/product.js?v=1') ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
