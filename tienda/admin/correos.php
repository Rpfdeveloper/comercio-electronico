<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
admin_require();
$types = ['pedido'=>'Confirmación de pedido', 'recuperacion'=>'Recuperación de contraseña'];
$type = $_GET['tipo'] ?? '';
if (!is_string($type) || ($type !== '' && !isset($types[$type]))) { http_response_code(422); $type = ''; }
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) : null;
if ($id === false) { http_response_code(404); exit('Correo no encontrado.'); }
$messages = []; $message = null; $unavailable = false;
try {
    if ($id !== null) {
        $st = db()->prepare('SELECT * FROM correo_saliente WHERE id=?'); $st->execute([$id]);
        $message = $st->fetch();
        if (!$message) { http_response_code(404); exit('Correo no encontrado.'); }
    } else {
        $st = db()->prepare('SELECT id,tipo,referencia,destinatario,asunto,estado,creado FROM correo_saliente' . ($type !== '' ? ' WHERE tipo=?' : '') . ' ORDER BY id DESC LIMIT 50');
        $st->execute($type !== '' ? [$type] : []);
        $messages = $st->fetchAll();
    }
} catch (PDOException $e) { http_response_code(503); $unavailable = true; }
admin_page_start('Correos', true, 'correos');
?>
<p class="eyebrow">Operaciones / Correos</p>
<h1>Correos de la tienda.</h1>
<p class="lede">La tienda registra aquí los mensajes que envía a los clientes. Todavía no se envían de verdad: falta conectar un proveedor de correo.</p>
<?php if ($unavailable): ?><p class="notice" role="alert">No se puede consultar la bandeja. Aplica la migración <code>008_mail_and_password_reset.sql</code>.</p><?php endif; ?>
<?php if ($message): ?>
<a href="correos.php">← Volver a la bandeja</a>
<section class="panel" aria-labelledby="mail-title">
<h2 id="mail-title" class="word-break"><?= h($message['asunto']) ?></h2>
<dl class="totals"><div><dt>Para</dt><dd class="word-break"><?= h($message['destinatario']) ?></dd></div><div><dt>Tipo</dt><dd><?= h($types[$message['tipo']] ?? $message['tipo']) ?></dd></div><div><dt>Fecha</dt><dd><?= h($message['creado']) ?></dd></div><div><dt>Estado</dt><dd><span class="badge pending">Registrado, sin enviar</span></dd></div></dl>
<?php if ($message['tipo'] === 'recuperacion'): ?><p class="notice info">Este mensaje contiene un enlace que permite cambiar la contraseña del cliente. No lo compartas; caduca a los 60 minutos y solo sirve una vez.</p><?php endif; ?>
<pre class="mail-body"><?= h($message['cuerpo']) ?></pre>
</section>
<?php elseif (!$unavailable): ?>
<form method="get" action="correos.php" class="catalog-search panel" role="search" aria-label="Filtrar correos">
<div class="field"><label for="mail-type">Tipo</label><select id="mail-type" name="tipo"><option value="">Todos</option><?php foreach ($types as $value=>$label): ?><option value="<?= h($value) ?>"<?= $type === $value ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
<button class="button" type="submit">Filtrar</button>
</form>
<?php if (!$messages): ?><div class="empty"><h2>No hay correos registrados.</h2><p class="muted">Aparecerán al confirmar pedidos o pedir la recuperación de una contraseña.</p></div><?php else: ?>
<table class="orders-table mail-table" aria-label="Correos registrados"><thead><tr><th scope="col">Fecha</th><th scope="col">Para</th><th scope="col">Asunto</th><th scope="col">Tipo</th><th scope="col">Detalle</th></tr></thead><tbody>
<?php foreach ($messages as $row): ?><tr data-mail="<?= (int)$row['id'] ?>">
<td data-label="Fecha"><?= h($row['creado']) ?></td><td data-label="Para"><?= h($row['destinatario']) ?></td><td data-label="Asunto"><strong><?= h($row['asunto']) ?></strong></td>
<td data-label="Tipo"><?= h($types[$row['tipo']] ?? $row['tipo']) ?></td><td data-label="Detalle"><a class="order-link" href="correos.php?id=<?= (int)$row['id'] ?>">Ver <span class="sr-only"><?= h($row['asunto']) ?></span>→</a></td>
</tr><?php endforeach; ?></tbody></table><?php endif; ?>
<p class="muted inventory-note">Se muestran los 50 más recientes. Los correos contienen datos personales: consúltalos solo para atender pedidos y pruebas.</p>
<?php endif; admin_page_end(); ?>
