<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/profile.php';
customer_no_cache();
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405); header('Allow: GET, POST'); exit;
}
$pdo = db();
$customer = customer_require($pdo);
$data = profile_values($customer);
$errors = [];
$notice = $_SESSION['profile_notice'] ?? ''; unset($_SESSION['profile_notice']);
$version = '';
try {
    $version = profile_version($customer);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? null;
        if ($action === 'profile') {
            $version = is_string($_POST['profile_version'] ?? null) ? $_POST['profile_version'] : '';
            if (!preg_match('/\A[0-9a-f]{64}\z/', $version)) { $errors['general'] = 'Recarga los datos guardados antes de editar.'; }
            foreach (profile_fields() as $key => $field) {
                $raw = $_POST[$key] ?? '';
                if (!is_string($raw) || strlen($raw) > $field['max'] * 4) {
                    $errors[$key] = 'Introduce un texto de hasta ' . $field['max'] . ' caracteres.';
                    $data[$key] = '';
                } else { $data[$key] = trim($raw); }
            }
            $errors += checkout_validate($data + ['email'=>$customer['email'], 'tipo'=>'registrado']);
            if (!$errors) {
                profile_save($pdo, $customer, $data, $version);
                $_SESSION['profile_notice'] = 'Datos guardados. Usaremos esta dirección al empezar tu próxima compra.';
                header('Location: perfil.php', true, 303); exit;
            }
        } elseif ($action === 'password') {
            $old = $_POST['current_password'] ?? null;
            $new = $_POST['password'] ?? null;
            $repeat = $_POST['password2'] ?? null;
            if (!is_string($old) || strlen($old) > 4096 || strpos($old, "\0") !== false || $old === '') {
                $errors['current_password'] = 'Introduce tu contraseña actual.';
            }
            if (!is_string($new) || !customer_valid_new_password($new)) {
                $errors['password'] = 'Usa al menos 15 caracteres y como máximo 72 bytes; los caracteres con tilde pueden ocupar varios bytes.';
            }
            if (!is_string($new) || !is_string($repeat) || strlen($repeat) > 4096 || !hash_equals($new, $repeat)) {
                $errors['password2'] = 'Las dos contraseñas nuevas deben coincidir.';
            }
            if (!$errors) {
                $customer = profile_change_password($pdo, $customer, $old, $new);
                customer_sign_in($customer);
                $_SESSION['profile_notice'] = 'Contraseña actualizada. Las demás sesiones y los enlaces de recuperación anteriores ya no son válidos.';
                header('Location: perfil.php', true, 303); exit;
            }
        } else { $errors['general'] = 'La acción del formulario no es válida.'; }
        http_response_code(422);
    }
} catch (ProfileConflict $e) {
    http_response_code(409); $errors['general'] = $e->getMessage();
} catch (ProfileSessionExpired $e) {
    customer_forget();
    http_response_code(409); $errors['general'] = 'Tu sesión ha cambiado. Vuelve a acceder antes de guardar.';
} catch (ProfilePasswordInvalid $e) {
    http_response_code(422); $errors['current_password'] = $e->getMessage();
} catch (AuthRateLimit $e) {
    http_response_code(429); header('Retry-After: 900'); $errors['general'] = $e->getMessage();
} catch (Throwable $e) {
    http_response_code(503); $errors['general'] = 'No podemos guardar los cambios en este momento. Inténtalo más tarde.';
}
$pageTitle = 'Mis datos y seguridad';
require __DIR__ . '/includes/header.php';
?>
<section class="account-card profile-page" aria-labelledby="profile-title">
  <a href="cuenta.php">← Volver a mi cuenta</a>
  <p class="account-eyebrow">TU ESPACIO PERSONAL</p>
  <h1 id="profile-title">Mis datos y seguridad</h1>
  <p class="account-help">Tu dirección habitual, lista para la próxima compra. Puedes elegir otra dirección al realizar cada pedido.</p>
  <?php if ($notice !== '' && !$errors): ?><p class="account-notice" role="status" tabindex="-1" data-focus-on-load><?= h($notice) ?></p><?php endif; ?>
  <?php if ($errors): ?>
    <div class="account-error" role="alert" tabindex="-1" data-focus-on-load>
      <h2>Revisa los datos</h2><ul>
      <?php foreach ($errors as $key => $message): ?><li><?php if ($key !== 'general'): ?><a href="#profile-<?= h($key) ?>"><?= h($message) ?></a><?php else: ?><?= h($message) ?><?php endif; ?></li><?php endforeach; ?>
      </ul><a href="perfil.php">Recargar datos guardados</a>
    </div>
  <?php endif; ?>
  <div class="profile-layout">
    <section aria-labelledby="profile-address-title">
      <h2 id="profile-address-title">Datos y dirección habitual</h2>
      <p class="account-help">Todos los campos son obligatorios salvo la provincia. Los cambios no modifican pedidos anteriores.</p>
      <p class="profile-email"><strong>Correo de acceso</strong><br><?= h($customer['email']) ?></p>
      <form action="perfil.php" method="post" class="account-form" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <input type="hidden" name="profile_version" value="<?= h($version) ?>">
        <div class="profile-fields">
          <?php foreach (profile_fields() as $key => $field): ?>
          <div class="profile-field <?= in_array($key, ['nombre', 'direccion'], true) ? 'profile-wide' : '' ?>">
            <label for="profile-<?= h($key) ?>"><?= h($field['label']) ?><?= !$field['required'] ? ' (opcional)' : '' ?></label>
            <input id="profile-<?= h($key) ?>" name="<?= h($key) ?>" type="text" value="<?= h($data[$key]) ?>" maxlength="<?= $field['max'] ?>" autocomplete="<?= h($field['autocomplete']) ?>" <?= $field['required'] ? 'required' : '' ?> <?= isset($errors[$key]) ? 'aria-invalid="true" aria-describedby="error-' . h($key) . '"' : '' ?>>
            <?php if (isset($errors[$key])): ?><p class="field-error" id="error-<?= h($key) ?>"><?= h($errors[$key]) ?></p><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="submit">Guardar mis datos</button>
      </form>
    </section>
    <section class="profile-security" aria-labelledby="profile-security-title">
      <h2 id="profile-security-title">Cambiar contraseña</h2>
      <p class="account-help">Confirma tu contraseña actual. Al cambiarla tendrás que volver a acceder en los demás dispositivos.</p>
      <form action="perfil.php" method="post" class="account-form" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <?php foreach (['current_password'=>'Contraseña actual', 'password'=>'Nueva contraseña', 'password2'=>'Repite la nueva contraseña'] as $key => $label): ?>
        <label for="profile-<?= h($key) ?>"><?= h($label) ?></label>
        <input id="profile-<?= h($key) ?>" name="<?= h($key) ?>" type="password" autocomplete="<?= $key === 'current_password' ? 'current-password' : 'new-password' ?>" maxlength="4096" required aria-describedby="profile-password-help<?= isset($errors[$key]) ? ' error-' . h($key) : '' ?>" <?= isset($errors[$key]) ? 'aria-invalid="true"' : '' ?>>
        <?php if (isset($errors[$key])): ?><p class="field-error" id="error-<?= h($key) ?>"><?= h($errors[$key]) ?></p><?php endif; ?>
        <?php endforeach; ?>
        <p class="account-help" id="profile-password-help">Usa una frase de al menos 15 caracteres, con un máximo de 72 bytes. Los caracteres con tilde pueden ocupar varios bytes.</p>
        <button type="submit">Actualizar contraseña</button>
      </form>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
