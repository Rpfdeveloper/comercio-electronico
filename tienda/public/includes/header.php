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
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(APP_NAME) ?></title>

  <!-- Hoja de estilos principal de la tienda -->
  <link rel="stylesheet" href="<?= asset('css/style.css?v=1.4') ?>">
</head>
<body>

<!-- HEADER PRINCIPAL -->
<header class="header-sticky">
  <div class="container header-bar">

    <!-- IZQUIERDA: LOGO + MENÚ -->
    <div class="header-left">

      <!-- Título / Logo -->
      <h1 class="shop-title">
        <a href="<?= url('/') ?>" class="shop-title-link">
          <?= h(APP_NAME) ?>
        </a>
      </h1>

      <!-- Menú de categorías -->
      <nav id="menu" class="main-menu" aria-label="Navegación principal">
        <a href="<?= url('catalogo.php') ?>">Catálogo</a>
        <?php foreach ($menuCategorias as $c): ?>
          <a href="<?= url('categoria.php?c=' . rawurlencode($c['slug'])) ?>">
            <?= h($c['nombre']) ?>
          </a>
        <?php endforeach; ?>
      </nav>

    </div>

    <div class="header-actions">
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
<main class="container">
