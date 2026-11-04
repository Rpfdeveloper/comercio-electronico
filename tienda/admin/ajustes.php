<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
admin_require();
require_once __DIR__ . '/../src/lib/policy.php';
require_once __DIR__ . '/../src/lib/cart.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
$errors = []; $conflict = ''; $unavailable = false; $policy = POLICY_DEFAULT; $history = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        policy_write(db(), $_POST);
        $_SESSION['policy_notice'] = 'Política guardada. Se aplica a los carritos y a los pedidos nuevos; los pedidos registrados conservan sus importes.';
        header('Location: ajustes.php', true, 303); exit;
    } catch (PolicyValidation $e) { $errors = $e->errors; http_response_code(422); }
    catch (PolicyConflict $e) { $conflict = $e->getMessage(); http_response_code(409); }
    catch (PDOException $e) { $unavailable = true; http_response_code(503); }
}
try {
    $policy = policy_read(db());
    $history = db()->query('SELECT antes,despues,fecha FROM tienda_politica_cambio ORDER BY id DESC LIMIT 10')->fetchAll();
} catch (PDOException $e) { $unavailable = true; if (http_response_code() < 400) { http_response_code(503); } }
$values = [];
foreach (['iva', 'envio', 'envio_gratis_desde'] as $key) {
    $values[$key] = $errors && is_string($_POST[$key] ?? null) && strlen($_POST[$key]) <= 40 ? $_POST[$key] :
        ($policy[$key] === null ? '' : str_replace('.', ',', $policy[$key]));
}
// Ejemplo calculado con la política guardada: un pedido de 50 € en productos.
$example = cart_totals([['subtotal'=>50.0]], $policy);
$describe = static function (array $p): string {
    return 'IVA ' . policy_percent((string)$p['iva']) . ' % · envío ' . ((float)$p['envio'] > 0 ? money_eur((float)$p['envio']) : 'gratis') .
        ($p['envio_gratis_desde'] !== null ? ' · gratis desde ' . money_eur((float)$p['envio_gratis_desde']) : '');
};
$notice = $_SESSION['policy_notice'] ?? ''; unset($_SESSION['policy_notice']);
$field = static function (string $name, string $label, string $hint) use ($values, $errors): void { ?>
<div class="field"><label for="policy-<?= $name ?>"><?= h($label) ?></label><input id="policy-<?= $name ?>" name="<?= $name ?>" type="text" inputmode="decimal" maxlength="12" value="<?= h($values[$name]) ?>"<?= isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="error-' . $name . '"' : ' aria-describedby="hint-' . $name . '"' ?>>
<?php if (isset($errors[$name])): ?><p class="error-text" id="error-<?= $name ?>"><?= h($errors[$name]) ?></p><?php else: ?><p class="muted hint" id="hint-<?= $name ?>"><?= h($hint) ?></p><?php endif; ?></div>
<?php };
admin_page_start('Ajustes de venta', true, 'ajustes');
?>
<p class="eyebrow">Operaciones / Ajustes de venta</p>
<h1>Impuestos y envío.</h1>
<p class="lede">Define el IVA y los gastos de envío que se aplican al carrito y a los pedidos nuevos.</p>
<?php if ($notice): ?><p class="notice success" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($errors): ?><div class="notice" role="alert" tabindex="-1" autofocus><strong>No se ha guardado. Revisa los datos:</strong><ul><?php foreach ($errors as $key=>$message): ?><li><a href="#policy-<?= h($key) ?>"><?= h($message) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($conflict): ?><div class="notice" role="alert"><p><?= h($conflict) ?></p><a class="button secondary" href="ajustes.php">Ver valores actuales</a></div><?php endif; ?>
<?php if ($unavailable): ?><p class="notice" role="alert">No se pueden consultar ni guardar los ajustes. Aplica la migración <code>007_commerce_policy.sql</code>; mientras tanto la tienda usa IVA del 21 % y envío gratuito.</p><?php endif; ?>
<?php if (!$unavailable && !$conflict): ?>
<form method="post" action="ajustes.php" class="catalog-editor">
<?= csrf_field() ?><input type="hidden" name="version" value="<?= h($errors && is_string($_POST['version'] ?? null) ? $_POST['version'] : policy_version($policy)) ?>">
<section class="panel"><div class="editor-grid">
<?php $field('iva', 'IVA (%)', 'Entre 0 y 30. Se aplica a productos y envío.'); ?>
<?php $field('envio', 'Gastos de envío por pedido (€, sin IVA)', 'Entre 0 y 1.000. Usa 0 para envío gratuito siempre.'); ?>
<?php $field('envio_gratis_desde', 'Envío gratis desde (€ en productos) · opcional', 'Déjalo vacío para no ofrecer envío gratis por importe.'); ?>
</div></section>
<div class="editor-actions"><button class="button" type="submit">Guardar política</button></div>
</form>
<section class="panel" aria-labelledby="example-title"><h2 id="example-title">Ejemplo con la política guardada</h2>
<dl class="totals"><div><dt>Productos</dt><dd><?= money_eur($example['products']) ?></dd></div><div><dt>Envío</dt><dd><?= $example['shipping'] > 0 ? money_eur($example['shipping']) : 'Gratis' ?></dd></div>
<div><dt>IVA (<?= h(policy_percent($example['rate'])) ?> %)</dt><dd><?= money_eur($example['tax']) ?></dd></div><div class="grand-total"><dt>Total</dt><dd data-example-total><?= money_eur($example['total']) ?></dd></div></dl>
<p class="muted hint">Los precios del catálogo son sin IVA. Un cliente con el carrito abierto verá los nuevos importes al recargar; si cambias la política mientras confirma, se le pide revisar el resumen.</p></section>
<section class="panel"><h2>Últimos cambios</h2>
<?php if (!$history): ?><p class="muted">Sin cambios desde la instalación: IVA 21 % y envío gratuito.</p><?php else: ?><dl class="catalog-history"><?php foreach ($history as $change): ?><div><dt><?= h($change['fecha']) ?></dt><dd><span>Antes:</span> <?= h($describe(json_decode($change['antes'], true))) ?><br><span>Después:</span> <?= h($describe(json_decode($change['despues'], true))) ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
</section>
<?php endif; admin_page_end(); ?>
