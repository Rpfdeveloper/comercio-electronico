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
<article class="card product-detail">
  <p class="store-eyebrow"><?= h($prod['marca']) ?></p>
  <h1><?= h($prod['nombre']) ?></h1>
  <?php if ($prod['imagen_principal']): ?><img src="<?= h($prod['imagen_principal']) ?>" alt="<?= h($prod['nombre']) ?>" style="max-width:280px;height:auto"><?php endif; ?>
  <p><?= nl2br(h($prod['descripcion_larga'] ?? '')) ?></p>
  <?php if (!$available): ?><p class="account-error" role="status">Agotado. Este perfume no tiene unidades disponibles.</p><?php endif; ?>
  <?php $favoriteProduct = $prod; require __DIR__ . '/includes/favorite-button.php'; ?>
  <form method="post" action="carrito.php" class="account-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="id_producto" value="<?= (int)$prod['id'] ?>">
    <?php if ($vars): ?>
      <label for="var">Capacidad</label>
      <select id="var" name="id_variante" <?= $available ? '' : 'disabled' ?>>
        <?php foreach ($vars as $v): ?><option value="<?= (int)$v['id'] ?>" <?= $v['stock'] > 0 ? '' : 'disabled' ?>><?= (int)$v['capacidad_ml'] ?> ml — <?= number_format((float)$v['precio'], 2, ',', '.') ?> €<?= $v['stock'] > 0 ? '' : ' — Agotado' ?></option><?php endforeach; ?>
      </select>
    <?php elseif (!$prod['variantes']): ?>
      <div class="price"><?= number_format((float)$prod['precio_base'], 2, ',', '.') ?> €</div>
    <?php endif; ?>
    <label for="qty">Cantidad</label>
    <input type="number" id="qty" name="cantidad" min="1" max="999" value="1" <?= $available ? '' : 'disabled' ?>>
    <button type="submit" <?= $available ? '' : 'disabled' ?>>Añadir al carrito</button>
  </form>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
