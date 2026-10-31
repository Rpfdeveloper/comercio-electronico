<?php
require_once __DIR__ . '/../../config/init.php';

$navPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');

/**
 * Contador de unidades en el carrito (suma cantidades)
 */
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)$item['cantidad'];
    }
}
$favoritesContext = favorites_context(db());
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
  <link rel="stylesheet" href="<?= asset('css/storefront.css?v=1.2') ?>">
  <link rel="stylesheet" href="<?= asset('css/canva-theme.css?v=1') ?>">
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

      <nav id="menu" class="main-menu" aria-label="Navegación principal">
        <a href="<?= h(url('index.php')) ?>"<?= $navPage === 'index.php' ? ' aria-current="page"' : '' ?>>Inicio</a>
        <a href="<?= h(url('catalogo.php')) ?>"<?= in_array($navPage, ['catalogo.php','categoria.php','producto.php'], true) ? ' aria-current="page"' : '' ?>>Catálogo</a>
        <a href="<?= h(url('descubrir.php')) ?>"<?= $navPage === 'descubrir.php' ? ' aria-current="page"' : '' ?>>Asesor</a>
        <a href="#contacto">Contacto</a>
      </nav>

    </div>

    <div class="header-actions">
    <a href="<?= h(url('favoritos.php')) ?>" class="favorites-nav-link">♡ Mis favoritos<?php if ($favoritesContext['ids']): ?> <span>(<?= count($favoritesContext['ids']) ?>)</span><?php endif; ?></a>
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
