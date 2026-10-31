<?php
declare(strict_types=1);

function admin_page_start(string $title, bool $signedIn = true, string $section = 'pedidos'): void
{
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= h($title) ?> · Administración · Perfume Shop</title>
  <link rel="stylesheet" href="assets/admin.css?v=3">
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="admin-header">
  <a class="brand" href="<?= $signedIn ? 'index.php' : '../public/index.php' ?>">PERFUME<span>SHOP / ADMIN</span></a>
  <nav aria-label="Navegación administrativa">
    <a href="../public/index.php">Ver tienda <span aria-hidden="true">↗</span></a>
    <?php if ($signedIn): ?><form method="post" action="logout.php"><?= csrf_field() ?><button class="button secondary" type="submit">Cerrar sesión</button></form><?php endif; ?>
  </nav>
</header>
<main id="contenido" class="admin-main">
<?php if ($signedIn): ?><nav class="admin-sections" aria-label="Secciones del panel">
  <a href="index.php"<?= $section === 'resumen' ? ' aria-current="page"' : '' ?>>Resumen</a>
  <a href="pedidos.php"<?= $section === 'pedidos' ? ' aria-current="page"' : '' ?>>Pedidos</a>
  <a href="catalogo.php"<?= $section === 'catalogo' ? ' aria-current="page"' : '' ?>>Catálogo</a>
  <a href="inventario.php"<?= $section === 'inventario' ? ' aria-current="page"' : '' ?>>Inventario</a>
</nav><?php endif; ?>
<?php
}

function admin_page_end(): void
{
    ?>
</main>
<footer class="admin-footer">PERFUME SHOP <span>Espacio privado de administración</span></footer>
</body>
</html>
<?php
}
