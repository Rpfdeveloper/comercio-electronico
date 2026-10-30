<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (admin_current()) { header('Location: pedidos.php', true, 302); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';
    if (!is_string($user) || !is_string($pass) || strlen($user)>64 || strlen($pass)>4096 ||
        strpos($pass, "\0")!==false) {
        http_response_code(422);
        $error = 'Los datos del formulario no son válidos.';
    } else {
        try {
            admin_proof(); // Rechazar configuración incompleta antes de autorizar.
            $pdo = db();
            $keys = admin_attempt_keys((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            auth_take_attempts($pdo, $keys);
            $validPassword = hash_equals(hash('sha256', getenv('TIENDA_ADMIN_PASS')), hash('sha256', $pass));
            if ($validPassword && $user === 'admin') {
                $pdo->prepare('DELETE FROM acceso_intento WHERE clave=?')->execute([array_key_first($keys)]);
                admin_sign_in();
                header('Location: pedidos.php', true, 302);
                exit;
            }
            http_response_code(401);
            $error = 'Credenciales incorrectas.';
        } catch (AuthRateLimit $e) {
            http_response_code(429);
            header('Retry-After: 900');
            $error = $e->getMessage();
        } catch (Throwable $e) {
            http_response_code(503);
            $error = 'El acceso no está disponible en este momento. Inténtalo más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Acceso al panel de administración</title>
</head>
<body>
  <main>
    <h1>Panel de administración</h1>
    <?php if ($error !== ''): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
    <form method="post" action="login.php">
      <?= csrf_field() ?>
      <p><label for="admin-user">Usuario</label><br>
      <input id="admin-user" type="text" name="user" autocomplete="username" maxlength="64" required></p>
      <p><label for="admin-pass">Contraseña</label><br>
      <input id="admin-pass" type="password" name="pass" autocomplete="current-password" required></p>
      <button type="submit">Entrar</button>
    </form>
  </main>
</body>
</html>
