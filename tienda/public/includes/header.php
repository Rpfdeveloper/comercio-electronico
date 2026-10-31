<?php
require_once __DIR__ . '/../../config/init.php';

/**
 * Carga de categorías activas para el menú principal
 */
$menuCategorias = db()->query("
    SELECT id, nombre, slug
    FROM categoria
    WHERE activa = 1
    ORDER BY orden, nombre
")->fetchAll();

/**
 * Contador de unidades en el carrito (suma cantidades)
 */
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)$item['cantidad'];
    }
}
$pageIndexable = ($pageIndexable ?? false) && seo_ready() && http_response_code() < 400;
$canonical = !empty($pageCanonical) ? seo_absolute($pageCanonical) : '';
$robots = $pageIndexable ? 'index, follow' : 'noindex, follow';
header('X-Robots-Tag: ' . $robots);
header('Cache-Control: no-store, private, max-age=0');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($pageTitle) ? h($pageTitle) . ' · ' : '' ?><?= h(APP_NAME) ?></title>
  <meta name="description" content="<?= h($pageDescription ?? 'Explora las colecciones y el catálogo de Perfume Shop. Proyecto en desarrollo.') ?>">
  <meta name="robots" content="<?= h($robots) ?>">
  <?php if ($canonical !== ''): ?>
  <link rel="canonical" href="<?= h($canonical) ?>">
  <meta property="og:title" content="<?= h(($pageTitle ?? APP_NAME) . ' · ' . APP_NAME) ?>">
  <meta property="og:description" content="<?= h($pageDescription ?? '') ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= h($canonical) ?>">
  <?php endif; ?>
  <meta name="theme-color" content="#f8f5f0">

  <!-- Hoja de estilos principal de la tienda -->
  <link rel="stylesheet" href="<?= asset('css/style.css?v=1.4') ?>">
  <link rel="stylesheet" href="<?= asset('css/storefront.css?v=1.1') ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Saltar al contenido</a>

<!-- HEADER PRINCIPAL -->
<header class="header-sticky">
  <div class="container header-bar">

    <!-- IZQUIERDA: LOGO + MENÚ -->
    <div class="header-left">

      <!-- Título / Logo -->
      <p class="shop-title">
        <a href="<?= url('/') ?>" class="shop-title-link">
          <?= h(APP_NAME) ?>
        </a>
      </p>

      <!-- Menú de categorías -->
      <nav id="menu" class="main-menu" aria-label="Navegación principal">
        <a href="<?= url('catalogo.php') ?>">Catálogo</a>
        <a href="<?= url('descubrir.php') ?>">Encuentra tu perfume</a>
        <?php foreach ($menuCategorias as $c): ?>
          <a href="<?= url('categoria.php?c=' . rawurlencode($c['slug'])) ?>">
            <?= h(category_label($c['nombre'])) ?>
          </a>
        <?php endforeach; ?>
      </nav>

    </div>

    <div class="header-actions">
    <a href="<?= h(url('favoritos.php')) ?>" class="favorites-nav-link">♡ Mis favoritos<?php if (favorites_ids()): ?> <span>(<?= count(favorites_ids()) ?>)</span><?php endif; ?></a>
    <a href="<?= url('cuenta.php') ?>" class="account-nav-link">Mi cuenta</a>
    <!-- DERECHA: BOTÓN CARRITO -->
    <a href="<?= url('carrito.php') ?>" class="cart-button">
      <span class="cart-icon">🛒</span>

      <?php if ($cartCount > 0): ?>
        <span class="cart-count"><?= (int)$cartCount ?></span>
      <?php endif; ?>

      <span class="cart-label">Carrito</span>
    </a>
    </div>

  </div>
</header>

<!-- Contenedor principal -->
<main id="main-content" class="container" tabindex="-1">
<?php if (isset($_SESSION['favorites_notice'])): ?>
  <div class="favorites-notice" role="status" tabindex="-1" data-focus-on-load><?= h($_SESSION['favorites_notice']) ?> <a href="<?= h(url('favoritos.php')) ?>">Ver mis favoritos</a></div>
  <?php unset($_SESSION['favorites_notice']); ?>
<?php endif; ?>
