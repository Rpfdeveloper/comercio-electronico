<?php
require_once __DIR__ . '/../config/init.php';      // Configuración + sesión
require_once __DIR__ . '/includes/header.php';     // Cabecera común

// Slug del producto recibido por GET (identificador único)
$slug = $_GET['p'] ?? '';


// Cargar datos principales del producto
$st = db()->prepare(
    "SELECT id, nombre, slug, descripcion_larga,
            COALESCE(NULLIF(precio_oferta,0.00), precio) AS precio_base,
            imagen_principal
     FROM producto
     WHERE slug = :s
     LIMIT 1"
);
$st->execute([':s' => $slug]);
$prod = $st->fetch();

// Si el producto no existe → 404
if (!$prod) {
    http_response_code(404);
    echo '<p>Producto no encontrado</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Imágenes adicionales del producto
 
$imgs = db()->prepare(
    "SELECT url, alt, es_principal
     FROM producto_imagen
     WHERE id_producto = :id
     ORDER BY es_principal DESC, orden, id"
);
$imgs->execute([':id' => $prod['id']]);
$imgs = $imgs->fetchAll();


// Variantes del producto (capacidad, precio…)
$vars = db()->prepare(
    "SELECT id, capacidad_ml,
            COALESCE(NULLIF(precio_oferta,0.00), precio) AS precio,
            stock
     FROM producto_variante
     WHERE id_producto = :id AND activo = 1
     ORDER BY capacidad_ml"
);
$vars->execute([':id' => $prod['id']]);
$vars = $vars->fetchAll();
?>

<article class="card">
  <h2><?= h($prod['nombre']) ?></h2>
  <!-- Imagen principal -->
  <?php if (!empty($prod['imagen_principal'])): ?>
    <img
      src="<?= h($prod['imagen_principal']) ?>"
      alt="<?= h($prod['nombre']) ?>"
      style="width:280px;height:auto"
    >
  <?php endif; ?>

  <!-- Descripción larga del producto -->
  <p><?= nl2br(h($prod['descripcion_larga'] ?? '')) ?></p>

  <!-- Formulario para añadir al carrito -->
  <form method="post" action="carrito.php" style="margin-top:1rem;">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="id_producto" value="<?= (int)$prod['id'] ?>">

    <!-- Variantes disponibles -->
    <?php if ($vars): ?>
      <label for="var">Capacidad</label>
      <select id="var" name="id_variante">
        <?php foreach ($vars as $v): ?>
          <option value="<?= (int)$v['id'] ?>">
            <?= (int)$v['capacidad_ml'] ?> ml — <?= number_format((float)$v['precio'], 2) ?> €
          </option>
        <?php endforeach; ?>
      </select>

    <!-- Si no hay variantes, mostrar el precio base -->
    <?php else: ?>
      <div class="price">
        <?= number_format((float)$prod['precio_base'], 2) ?> €
      </div>
    <?php endif; ?>

    <!-- Cantidad -->
    <label for="qty" style="margin-left:0.5rem;">Cantidad</label>
    <input
      type="number"
      id="qty"
      name="cantidad"
      min="1"
      value="1"
      style="width:80px;"
    >

    <!-- Botón añadir -->
    <button type="submit" style="margin-left:0.5rem;">
      Añadir al carrito
    </button>
  </form>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>