<?php
declare(strict_types=1);

function admin_page_start(string $title, bool $signedIn = true): void
{
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= h($title) ?> · Administración · Perfume Shop</title>
  <link rel="stylesheet" href="assets/admin.css?v=1">
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="admin-header">
  <a class="brand" href="<?= $signedIn ? 'pedidos.php' : '../public/index.php' ?>">PERFUME<span>SHOP / ADMIN</span></a>
  <nav aria-label="Navegación administrativa">
    <a href="../public/index.php">Ver tienda <span aria-hidden="true">↗</span></a>
    <?php if ($signedIn): ?><form method="post" action="logout.php"><?= csrf_field() ?><button class="button secondary" type="submit">Cerrar sesión</button></form><?php endif; ?>
  </nav>
</header>
<main id="contenido" class="admin-main">
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
