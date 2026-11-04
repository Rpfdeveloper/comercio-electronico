<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/password_reset.php';
customer_no_cache();
// El enlace lleva un secreto en la URL: no se transmite a otras páginas ni sitios.
header('Referrer-Policy: no-referrer');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
$pdo = db();
$token = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['token'] ?? null) : ($_GET['token'] ?? null);
$valid = false; $error = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? null; $repeat = $_POST['password2'] ?? null;
        if (!is_string($password) || !is_string($repeat) || strlen($password) > 4096) {
            http_response_code(422); $error = 'Los datos del formulario no son válidos.'; reset_find($pdo, $token);
        } elseif (!hash_equals($password, $repeat)) {
            http_response_code(422); $error = 'Las dos contraseñas no coinciden.'; reset_find($pdo, $token);
        } elseif (!customer_valid_new_password($password)) {
            http_response_code(422); $error = 'Usa una frase de al menos 15 caracteres (máximo 72 bytes).'; reset_find($pdo, $token);
        } else {
            reset_complete($pdo, $token, $password);
            customer_forget();
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['account_notice'] = 'Contraseña actualizada. Ya puedes acceder con la nueva.';
            header('Location: acceso.php', true, 303); exit;
        }
    } else {
        reset_find($pdo, $token);
    }
    $valid = true;
} catch (ResetInvalid $e) {
    http_response_code(410);
} catch (Throwable $e) {
    http_response_code(503); $error = 'No podemos cambiar la contraseña en este momento. Inténtalo más tarde.';
}
$pageTitle = 'Nueva contraseña';
require_once __DIR__ . '/includes/header.php';
?>
<section class="account-card account-login" aria-labelledby="new-password-title">
  <p class="account-eyebrow">TU ESPACIO PERSONAL</p>
  <h2 id="new-password-title">Elige una contraseña nueva</h2>
  <?php if (!$valid && http_response_code() === 410): ?>
    <p class="account-error" role="alert" data-reset-invalid>El enlace no es válido, ya se ha usado o ha caducado.</p>
    <a class="btn" href="recuperar.php">Pedir un enlace nuevo</a>
  <?php elseif (!$valid): ?>
    <p class="account-error" role="alert"><?= h($error) ?></p>
  <?php else: ?>
    <?php if ($error !== ''): ?><p class="account-error" role="alert"><?= h($error) ?></p><?php endif; ?>
    <form method="post" action="restablecer.php" class="account-form">
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= h((string)$token) ?>">
      <label for="new-password">Contraseña nueva</label>
      <input id="new-password" name="password" type="password" autocomplete="new-password" minlength="15" required aria-describedby="new-password-help">
      <p class="account-help" id="new-password-help">Al menos 15 caracteres. Una frase fácil de recordar es más segura que una palabra corta.</p>
      <label for="new-password2">Repite la contraseña</label>
      <input id="new-password2" name="password2" type="password" autocomplete="new-password" minlength="15" required>
      <button type="submit">Guardar contraseña</button>
    </form>
  <?php endif; ?>
  <a href="acceso.php">Volver al acceso</a>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
