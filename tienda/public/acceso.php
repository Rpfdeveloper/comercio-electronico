<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/customer.php';
customer_no_cache();
$pdo = db();
if (customer_current($pdo) !== null) { header('Location: cuenta.php', true, 302); exit; }
$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!is_string($email) || !is_string($password) || strlen($email) > 160 ||
        strlen($password) > 4096 || strpos($password, "\0") !== false) {
        http_response_code(422);
        $email = '';
        $error = 'Los datos del formulario no son válidos.';
    } else {
        $email = trim($email);
        try {
            customer_take_attempt($pdo, $email);
            $customer = customer_find($pdo, $email);
            if (customer_password_matches($customer, $password)) {
                customer_clear_attempts($pdo, $email);
                customer_sign_in($customer);
                header('Location: cuenta.php', true, 303);
                exit;
            }
            http_response_code(401);
            $error = 'El correo o la contraseña no son correctos.';
        } catch (CustomerRateLimit $e) {
            http_response_code(429);
            header('Retry-After: 900');
            $error = $e->getMessage();
        } catch (Throwable $e) {
            http_response_code(503);
            $error = 'El acceso no está disponible en este momento. Inténtalo más tarde.';
        }
    }
}
require_once __DIR__ . '/includes/header.php';
?>
<section class="account-card account-login" aria-labelledby="account-title">
  <p class="account-eyebrow">TU ESPACIO PERSONAL</p>
  <h2 id="account-title">Accede a tu cuenta</h2>
  <p>Consulta los pedidos realizados con tu cuenta de cliente.</p>
  <?php if ($error !== ''): ?><p class="account-error" role="alert"><?= h($error) ?></p><?php endif; ?>
  <form method="post" action="acceso.php" class="account-form">
    <?= csrf_field() ?>
    <label for="account-email">Correo electrónico</label>
    <input id="account-email" name="email" type="email" autocomplete="username" maxlength="160" value="<?= h($email) ?>" required>
    <label for="account-password">Contraseña</label>
    <input id="account-password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit">Entrar en mi cuenta</button>
  </form>
  <p class="account-help">Puedes crear tu cuenta al comprar como usuario registrado. También puedes comprar como invitado.</p>
  <a href="index.php">Volver a la tienda</a>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
