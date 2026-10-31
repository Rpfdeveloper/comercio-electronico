<h3 id="summary-title">Resumen del pedido</h3>
<?php if ($summaryItems): ?>
  <ul class="summary-products">
    <?php foreach ($summaryItems as $it): ?><li><div><strong><?= h($it['nombre']) ?></strong><span><?= $it['capacidad_ml'] ? (int)$it['capacidad_ml'] . ' ml · ' : '' ?><?= (int)$it['cantidad'] ?> <?= $it['cantidad'] === 1 ? 'unidad' : 'unidades' ?> × <?= money_eur($it['precio']) ?></span></div><strong><?= money_eur($it['subtotal']) ?></strong></li><?php endforeach; ?>
  </ul>
<?php endif; ?>
<dl class="commerce-totals">
  <dt>Importe productos</dt><dd><?= money_eur($totals['products']) ?></dd>
  <dt>IVA (21%)</dt><dd><?= money_eur($totals['tax']) ?></dd>
  <dt>Gastos de envío</dt><dd><?= money_eur($totals['shipping']) ?></dd>
  <dt class="commerce-grand-total">Total</dt><dd class="commerce-grand-total"><?= money_eur($totals['total']) ?></dd>
</dl>
