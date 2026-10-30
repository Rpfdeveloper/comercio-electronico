<?php
declare(strict_types=1);

session_start();                         // Inicia la sesión para el área admin

// Credenciales fijas del panel (demo/PRA)
const ADMIN_USER = 'admin';
define('ADMIN_PASS', getenv('TIENDA_ADMIN_PASS') ?: '');

// Si ya está logueado, ir directamente al listado de pedidos
if (!empty($_SESSION['admin_logged'])) {
    header('Location: pedidos.php');
    exit;
}

$error = '';

// Procesar envío del formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    // Comprobación simple de usuario/contraseña
    if (ADMIN_PASS !== '' && $user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['admin_logged'] = true;    // Marca sesión de admin
        header('Location: pedidos.php');     // Redirige al panel
        exit;
    } else {
        $error = 'Credenciales incorrectas.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Admin - Login</title>
</head>
<body>
  <h2>Panel de administración</h2>

  <!-- Mensaje de error, si lo hay -->
  <?php if ($error): ?>
    <p style="color:red;"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <!-- Formulario de acceso -->
  <form method="post">
    <label>Usuario<br>
      <input type="text" name="user" required>
    </label>
    <br><br>

    <label>Contraseña<br>
      <input type="password" name="pass" required>
    </label>
    <br><br>

    <button type="submit">Entrar</button>
  </form>

</body>
</html>