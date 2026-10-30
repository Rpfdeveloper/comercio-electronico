<?php
require_once __DIR__ . '/../config/init.php';      // Configuración + sesión
require_once __DIR__ . '/includes/header.php';     // Cabecera común

// Obtener categorías activas ordenadas por posición y nombre
$stmt = db()->query(
    "SELECT nombre, slug, imagen_url
     FROM categoria
     WHERE activa = 1
     ORDER BY orden, nombre"
);
$cats = $stmt->fetchAll();
?>

<section class="container" style="margin-top:2rem;">
  <h2>Categorías</h2>

  <div class="grid">
    <?php foreach ($cats as $c): ?>
      <article class="card">

        <!-- Enlace a la página de productos de la categoría -->
        <a href="categoria.php?c=<?= h($c['slug']) ?>">

          <!-- Imagen de categoría (si existe en la BD) -->
          <?php if (!empty($c['imagen_url'])): ?>
            <img
              src="<?= h($c['imagen_url']) ?>"
              alt="<?= h($c['nombre']) ?>"
              style="width:100%;height:auto;"
            >
          <?php endif; ?>

          <!-- Nombre visible de la categoría -->
          <h3><?= h($c['nombre']) ?></h3>

        </a>

      </article>
    <?php endforeach; ?>
  </div>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


