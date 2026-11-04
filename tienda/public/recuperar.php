<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/password_reset.php';
customer_no_cache();
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
$pdo = db();
$error = ''; $sent = false; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    if (!is_string($email) || strlen($email) > 160 || preg_match('/[\x00-\x1f\x7f]/', $email)) {
        http_response_code(422); $email = ''; $error = 'Introduce un correo electrónico válido.';
    } elseif (filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
        http_response_code(422); $error = 'Introduce un correo electrónico válido.';
    } else {
        $email = trim($email);
        try {
            reset_request($pdo, $email);
            $sent = true;
        } catch (CustomerRateLimit $e) {
            http_response_code(429); header('Retry-After: 900'); $error = $e->getMessage();
        } catch (Throwable $e) {
            http_response_code(503); $error = 'No podemos atender la solicitud en este momento. Inténtalo más tarde.';
        }
    }
}
$pageTitle = 'Recuperar contraseña';
require_once __DIR__ . '/includes/header.php';
?>
<section class="account-card account-login" aria-labelledby="reset-title">
  <p class="account-eyebrow">TU ESPACIO PERSONAL</p>
  <h2 id="reset-title">Recupera tu contraseña</h2>
  <?php if ($sent): ?>
    <p class="account-notice" role="status" data-reset-sent>Si existe una cuenta con ese correo, te hemos preparado un enlace para cambiar la contraseña. Es válido durante <?= RESET_MINUTES ?> minutos.</p>
    <p class="account-help">Este prototipo todavía no envía correos: el mensaje queda registrado en la administración de la tienda.</p>
  <?php else: ?>
    <p>Escribe el correo de tu cuenta y te enviaremos un enlace para elegir una contraseña nueva.</p>
    <?php if ($error !== ''): ?><p class="account-error" role="alert"><?= h($error) ?></p><?php endif; ?>
    <form method="post" action="recuperar.php" class="account-form">
      <?= csrf_field() ?>
      <label for="reset-email">Correo electrónico</label>
      <input id="reset-email" name="email" type="email" autocomplete="email" maxlength="160" value="<?= h($email) ?>" required>
      <button type="submit">Enviar enlace</button>
    </form>
  <?php endif; ?>
  <a href="acceso.php">Volver al acceso</a>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
