<?php // $favoriteProduct contiene id y nombre de un producto del catálogo. ?>
<?php $isFavorite = in_array((int)$favoriteProduct['id'], favorites_ids(), true); ?>
<form class="favorite-form" method="post" action="<?= h(url('favoritos.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="<?= $isFavorite ? 'remove' : 'add' ?>">
  <input type="hidden" name="id_producto" value="<?= (int)$favoriteProduct['id'] ?>">
  <input type="hidden" name="return" value="<?= h(favorites_current_path()) ?>">
  <button class="favorite-button" type="submit" aria-label="<?= h(($isFavorite ? 'Quitar de favoritos: ' : 'Guardar en favoritos: ') . $favoriteProduct['nombre']) ?>">
    <span aria-hidden="true"><?= $isFavorite ? '♥' : '♡' ?></span> <?= $isFavorite ? 'Guardado · quitar' : 'Guardar perfume' ?>
  </button>
</form>
