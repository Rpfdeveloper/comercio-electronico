<?php
require_once __DIR__ . '/../config/init.php';      // Configuración general y sesión
require_once __DIR__ . '/includes/header.php';     // Cabecera común de la web

// Slug de la categoría recibida por GET (ej: perfumes-men, perfumes-women)
$slug = $_GET['c'] ?? '';

// Paginación sencilla
$limit  = 12;                                      // Nº de productos por página
$page   = max(1, (int)($_GET['page'] ?? 1));       // Página actual (mínimo 1)
$offset = ($page - 1) * $limit;

// ----------------------------------------------------
// Consulta de productos de una categoría concreta
// ----------------------------------------------------
$sql = "SELECT p.id,
               p.nombre,
               p.slug,
               p.descripcion_corta,
               p.imagen_principal,
               -- Precio mínimo: variantes activas o precio base del producto
               COALESCE(
                 (SELECT MIN(COALESCE(NULLIF(v.precio_oferta,0.00), v.precio))
                  FROM producto_variante v
                  WHERE v.id_producto = p.id AND v.activo = 1),
                 COALESCE(NULLIF(p.precio_oferta,0.00), p.precio)
               ) AS precio_desde
        FROM producto p
        JOIN categoria c ON c.id = p.id_categoria
        WHERE c.slug = :slug
          AND p.activo = 1
        ORDER BY p.nombre
        LIMIT :limit OFFSET :offset";

$st = db()->prepare($sql);
$st->bindValue(':slug', $slug);
$st->bindValue(':limit', $limit, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();

$prods = $st->fetchAll();
?>

<h2>Productos</h2>

<?php if (!$prods): ?>
  <!-- No hay productos para esta categoría -->
  <p>No hay productos en esta categoría por ahora.</p>

<?php else: ?>
  <div class="grid">
    <?php foreach ($prods as $p): ?>
      <article class="card">

        <!-- Enlace a la ficha detallada del producto -->
        <a href="producto.php?p=<?= h($p['slug']) ?>">

          <!-- Imagen principal (si existe en BD) -->
          <?php if (!empty($p['imagen_principal'])): ?>
            <img
              src="<?= h($p['imagen_principal']) ?>"
              alt="<?= h($p['nombre']) ?>"
              style="width:100%;height:auto"
            >
          <?php endif; ?>

          <h3><?= h($p['nombre']) ?></h3>
        </a>

        <!-- Descripción corta opcional -->
        <?php if (!empty($p['descripcion_corta'])): ?>
          <p><?= h($p['descripcion_corta']) ?></p>
        <?php endif; ?>

        <!-- Precio mínimo calculado ─ “desde ... €” -->
        <div class="price">
          desde <?= number_format((float)$p['precio_desde'], 2) ?> €
        </div>

      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>