<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/cart.php';
foreach (['step', 'tipo', 'email', 'nombre', 'direccion', 'cp', 'poblacion', 'provincia', 'pais', 'password'] as $field) {
    if (isset($_POST[$field]) && !is_string($_POST[$field])) {
        http_response_code(422);
        exit('Los datos del formulario no son válidos.');
    }
}

// Carrito obligatorio
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    require_once __DIR__ . '/includes/header.php';
    echo '<section class="container" style="margin-top:2rem;">
            <h2>Carrito de la compra</h2>
            <p>Tu carrito está vacío. Añade algún producto antes de tramitar la compra.</p>
          </section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = db();
$cart =& $_SESSION['cart'];

// Volver a validar en servidor; nunca aceptar precios ni stock del formulario.
try {
    $items = cart_items($pdo, $cart);
} catch (DomainException $e) {
    http_response_code(409);
    require_once __DIR__ . '/includes/header.php';
    echo '<p role="alert">' . h($e->getMessage()) . '</p>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
$importeProductos = array_sum(array_column($items, 'subtotal'));

// Si por algún motivo no hay items válidos
if (!$items) {
    require_once __DIR__ . '/includes/header.php';
    echo '<section class="container" style="margin-top:2rem;">
            <h2>Proceso de compra</h2>
            <p>No se han encontrado productos válidos en el carrito.</p>
          </section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Paso actual del checkout
$step   = $_POST['step'] ?? 'form';
$errors = [];

// Datos del formulario (cargamos de POST o de sesión para no perderlos)
$stored = $_SESSION['checkout'] ?? [];

$data = [
    'tipo'      => $_POST['tipo']      ?? ($stored['tipo']      ?? 'invitado'),
    'email'     => trim($_POST['email']     ?? ($stored['email']     ?? '')),
    'nombre'    => trim($_POST['nombre']    ?? ($stored['nombre']    ?? '')),
    'direccion' => trim($_POST['direccion'] ?? ($stored['direccion'] ?? '')),
    'cp'        => trim($_POST['cp']        ?? ($stored['cp']        ?? '')),
    'poblacion' => trim($_POST['poblacion'] ?? ($stored['poblacion'] ?? '')),
    'provincia' => trim($_POST['provincia'] ?? ($stored['provincia'] ?? '')),
    'pais'      => trim($_POST['pais']      ?? ($stored['pais']      ?? 'España')),
    'password'  => $_POST['password'] ?? '',
];

// ---- Paso 1: validación del formulario de datos ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'form') {
    unset($_SESSION['checkout']); // Invalidar resúmenes anteriores, incluso si el nuevo formulario falla.

    if (!in_array($data['tipo'], ['registrado', 'invitado'], true)) {
        $data['tipo'] = 'invitado';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Introduce un correo electrónico válido.';
    }

    if ($data['nombre'] === '') {
        $errors['nombre'] = 'Introduce el nombre completo del destinatario.';
    }

    if ($data['direccion'] === '') {
        $errors['direccion'] = 'Introduce la dirección de envío.';
    }

    if ($data['cp'] === '') {
        $errors['cp'] = 'Introduce el código postal.';
    }

    if ($data['poblacion'] === '') {
        $errors['poblacion'] = 'Introduce la población.';
    }

    if ($data['pais'] === '') {
        $errors['pais'] = 'Introduce el país.';
    }

    if ($data['tipo'] === 'registrado' && $data['password'] === '') {
        $errors['password'] = 'Introduce una contraseña para el usuario registrado.';
    }

    // Acreditar la cuenta antes del resumen; no guardar contraseñas en claro.
    $accountProof = null;
    $newPasswordHash = null;
    if (!$errors && $data['tipo'] === 'registrado') {
        $st = $pdo->prepare(
            'SELECT c.id, cr.password_hash FROM cliente c
             LEFT JOIN cliente_registrado cr ON cr.id_cliente = c.id WHERE c.email = ?'
        );
        $st->execute([$data['email']]);
        $existing = $st->fetch();
        if ($existing) {
            if (empty($existing['password_hash']) ||
                !password_verify($data['password'], $existing['password_hash'])) {
                $errors['password'] = 'No se ha podido autenticar la cuenta.';
            } else {
                $accountProof = [
                    'id' => (int)$existing['id'],
                    'hash' => hash('sha256', $existing['password_hash']),
                ];
            }
        } else {
            $newPasswordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        }
    }

    if (!$errors) {
        // Guardamos la información para el resumen
        $_SESSION['checkout'] = $data;
        unset($_SESSION['checkout']['password']);
        $_SESSION['checkout']['account_proof'] = $accountProof;
        $_SESSION['checkout']['new_password_hash'] = $newPasswordHash;
        $_SESSION['checkout']['cart_snapshot'] = $items;
        $step = 'resumen';
    }
}

// ---- Paso 2: confirmación y grabación del pedido ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'confirm') {

    if (empty($_SESSION['checkout'])) {
        // Si se pierde la info, volvemos al formulario
        header('Location: checkout.php');
        exit;
    }

    $data = $_SESSION['checkout'];

    // Cálculo simple de impuestos y total
    $gastosEnvio = 0.00;
    $impuestos   = round($importeProductos * 0.21, 2); // 21% IVA
    $total       = $importeProductos + $gastosEnvio + $impuestos;

    try {
        $pdo->beginTransaction();
        // Releer precios y bloquear stock dentro de la misma transacción que el pedido.
        $items = cart_items($pdo, $cart, true);
        if (($data['cart_snapshot'] ?? null) !== $items) {
            throw new DomainException('El carrito o los precios han cambiado. Revisa el resumen de nuevo.');
        }
        $importeProductos = array_sum(array_column($items, 'subtotal'));
        $impuestos = round($importeProductos * 0.21, 2);
        $total = $importeProductos + $gastosEnvio + $impuestos;
        cart_reduce_stock($pdo, $items);

        $idCliente = null;

        if ($data['tipo'] === 'registrado') {
            // Alta / reutilización de cliente
            $st = $pdo->prepare(
                "SELECT c.id, cr.password_hash
                 FROM cliente c
                 LEFT JOIN cliente_registrado cr ON cr.id_cliente = c.id
                 WHERE c.email = :email
                 FOR UPDATE"
            );
            $st->execute([':email' => $data['email']]);
            $rowCli = $st->fetch();

            if ($rowCli) {
                // Nunca modificar una cuenta existente sin acreditar su contraseña.
                $proof = $data['account_proof'] ?? null;
                if (!$proof || (int)$proof['id'] !== (int)$rowCli['id'] ||
                    empty($rowCli['password_hash']) ||
                    !hash_equals($proof['hash'], hash('sha256', $rowCli['password_hash']))) {
                    throw new RuntimeException('No se ha podido autenticar la cuenta.');
                }
                $idCliente = (int)$rowCli['id'];

                $st = $pdo->prepare(
                    "UPDATE cliente
                     SET nombre = :nombre
                     WHERE id = :id"
                );
                $st->execute([
                    ':nombre' => $data['nombre'],
                    ':id'     => $idCliente,
                ]);
            } else {
                if (empty($data['new_password_hash']) || !empty($data['account_proof'])) {
                    throw new RuntimeException('La cuenta ha cambiado. Repite el proceso.');
                }
                $st = $pdo->prepare(
                    "INSERT INTO cliente (email, nombre)
                     VALUES (:email, :nombre)"
                );
                $st->execute([
                    ':email'  => $data['email'],
                    ':nombre' => $data['nombre'],
                ]);
                $idCliente = (int)$pdo->lastInsertId();
            }

            // Alta / actualización de cliente_registrado
            $passHash = $rowCli ? $rowCli['password_hash'] : $data['new_password_hash'];

            $st = $pdo->prepare(
                "INSERT INTO cliente_registrado
                    (id_cliente, password_hash, direccion, cp, poblacion, provincia, pais)
                 VALUES
                    (:id_cliente, :pw, :dir, :cp, :pob, :prov, :pais)
                 ON DUPLICATE KEY UPDATE
                    direccion     = VALUES(direccion),
                    cp            = VALUES(cp),
                    poblacion     = VALUES(poblacion),
                    provincia     = VALUES(provincia),
                    pais          = VALUES(pais)"
            );
            $st->execute([
                ':id_cliente' => $idCliente,
                ':pw'         => $passHash,
                ':dir'        => $data['direccion'],
                ':cp'         => $data['cp'],
                ':pob'        => $data['poblacion'],
                ':prov'       => $data['provincia'] ?: null,
                ':pais'       => $data['pais'],
            ]);
        }

        // Alta del pedido
        $st = $pdo->prepare(
            "INSERT INTO pedido
                (id_cliente, email,
                 nombre_envio, direccion_envio, cp_envio,
                 poblacion_envio, provincia_envio, pais_envio,
                 importe_productos, gastos_envio, impuestos, total,
                 estado, observaciones)
             VALUES
                (:id_cliente, :email,
                 :nombre_envio, :direccion_envio, :cp_envio,
                 :poblacion_envio, :provincia_envio, :pais_envio,
                 :importe_productos, :gastos_envio, :impuestos, :total,
                 1, :observaciones)"
        );

        $st->execute([
            ':id_cliente'       => $idCliente,
            ':email'            => $data['email'],
            ':nombre_envio'     => $data['nombre'],
            ':direccion_envio'  => $data['direccion'],
            ':cp_envio'         => $data['cp'],
            ':poblacion_envio'  => $data['poblacion'],
            ':provincia_envio'  => $data['provincia'] ?: null,
            ':pais_envio'       => $data['pais'],
            ':importe_productos'=> $importeProductos,
            ':gastos_envio'     => $gastosEnvio,
            ':impuestos'        => $impuestos,
            ':total'            => $total,
            ':observaciones'    => $data['tipo'] === 'registrado'
                                   ? 'Compra como usuario registrado'
                                   : 'Compra como invitado',
        ]);

        $idPedido = (int)$pdo->lastInsertId();

        // Alta de líneas de pedido
        $stLinea = $pdo->prepare(
            "INSERT INTO linea_pedido
                (id_pedido, id_producto, id_variante,
                 nombre_producto, capacidad_ml,
                 unidades, precio_unitario, dto, precio_neto)
             VALUES
                (:id_pedido, :id_producto, :id_variante,
                 :nombre_producto, :capacidad_ml,
                 :unidades, :precio_unitario, :dto, :precio_neto)"
        );

        foreach ($items as $it) {
            $stLinea->execute([
                ':id_pedido'       => $idPedido,
                ':id_producto'     => $it['id_producto'],
                ':id_variante'     => $it['id_variante'],
                ':nombre_producto' => $it['nombre'],
                ':capacidad_ml'    => $it['capacidad_ml'],
                ':unidades'        => $it['cantidad'],
                ':precio_unitario' => $it['precio'],
                ':dto'             => 0.00,
                ':precio_neto'     => $it['subtotal'],
            ]);
        }

        $pdo->commit();

        // Limpiamos carrito y datos de checkout
        unset($_SESSION['cart'], $_SESSION['checkout']);

        require_once __DIR__ . '/includes/header.php';

        echo '<section class="container" style="margin-top:2rem;">
                <h2>Pedido realizado</h2>
                <p>Tu pedido se ha registrado correctamente con el número <strong>#' . (int)$idPedido . '</strong>.</p>
                <p>Correo de contacto del pedido: <strong>' . h($data['email']) . '</strong>.</p>
              </section>';

        require_once __DIR__ . '/includes/footer.php';
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code($e instanceof DomainException ? 409 : 500);
        $errors['general'] = $e instanceof DomainException
            ? $e->getMessage()
            : 'Se ha producido un error al registrar el pedido. Inténtalo de nuevo más tarde.';
        $step = 'form'; // volvemos al formulario
    }
}
require_once __DIR__ . '/includes/header.php';
?>

<section class="container" style="margin-top:2rem;">
  <h2>Proceso de compra</h2>

  <?php if (!empty($errors['general'])): ?>
    <p role="alert" style="color:red;"><?= h($errors['general']) ?></p>
  <?php endif; ?>

  <?php if ($step === 'form'): ?>
    <h3>Datos del comprador</h3>

    <form method="post" action="checkout.php" style="max-width:480px;">
      <?= csrf_field() ?>
      <input type="hidden" name="step" value="form">

      <p>
        <label>
          <input type="radio" name="tipo" value="registrado"
            <?= $data['tipo'] === 'registrado' ? 'checked' : '' ?>>
          Usuario registrado
        </label>
        &nbsp;
        <label>
          <input type="radio" name="tipo" value="invitado"
            <?= $data['tipo'] === 'invitado' ? 'checked' : '' ?>>
          Comprar como invitado
        </label>
      </p>

      <p>
        <label>Correo electrónico<br>
          <input type="email" name="email" value="<?= h($data['email']) ?>" required>
        </label><br>
        <?php if (!empty($errors['email'])): ?>
          <small style="color:red;"><?= h($errors['email']) ?></small>
        <?php endif; ?>
      </p>

      <p>
        <label>Nombre completo<br>
          <input type="text" name="nombre" value="<?= h($data['nombre']) ?>" required>
        </label><br>
        <?php if (!empty($errors['nombre'])): ?>
          <small style="color:red;"><?= h($errors['nombre']) ?></small>
        <?php endif; ?>
      </p>

      <p>
        <label>Dirección de envío<br>
          <input type="text" name="direccion" value="<?= h($data['direccion']) ?>" required>
        </label><br>
        <?php if (!empty($errors['direccion'])): ?>
          <small style="color:red;"><?= h($errors['direccion']) ?></small>
        <?php endif; ?>
      </p>

      <p>
        <label>Código postal<br>
          <input type="text" name="cp" value="<?= h($data['cp']) ?>" required>
        </label><br>
        <?php if (!empty($errors['cp'])): ?>
          <small style="color:red;"><?= h($errors['cp']) ?></small>
        <?php endif; ?>
      </p>

      <p>
        <label>Población<br>
          <input type="text" name="poblacion" value="<?= h($data['poblacion']) ?>" required>
        </label><br>
        <?php if (!empty($errors['poblacion'])): ?>
          <small style="color:red;"><?= h($errors['poblacion']) ?></small>
        <?php endif; ?>
      </p>

      <p>
        <label>Provincia<br>
          <input type="text" name="provincia" value="<?= h($data['provincia']) ?>">
        </label>
      </p>

      <p>
        <label>País<br>
          <input type="text" name="pais" value="<?= h($data['pais']) ?>" required>
        </label><br>
        <?php if (!empty($errors['pais'])): ?>
          <small style="color:red;"><?= h($errors['pais']) ?></small>
        <?php endif; ?>
      </p>

      <div id="password-block">
        <p>
          <label>Contraseña (solo para usuario registrado)<br>
            <input type="password" name="password" value="">
          </label><br>
          <?php if (!empty($errors['password'])): ?>
            <small style="color:red;"><?= h($errors['password']) ?></small>
          <?php endif; ?>
        </p>
      </div>

      <p>
        <button type="submit">Continuar</button>
      </p>
    </form>

    <h3>Resumen del carrito</h3>
    <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
      <thead>
      <tr>
        <th style="text-align:left; border-bottom:1px solid #e5e7eb;">Producto</th>
        <th style="text-align:right; border-bottom:1px solid #e5e7eb;">Precio</th>
        <th style="text-align:center; border-bottom:1px solid #e5e7eb;">Cantidad</th>
        <th style="text-align:right; border-bottom:1px solid #e5e7eb;">Subtotal</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td>
            <?= h($it['nombre']) ?>
            <?php if ($it['capacidad_ml']): ?>
              (<?= (int)$it['capacidad_ml'] ?> ml)
            <?php endif; ?>
          </td>
          <td style="text-align:right;"><?= number_format($it['precio'], 2) ?> €</td>
          <td style="text-align:center;"><?= (int)$it['cantidad'] ?></td>
          <td style="text-align:right;"><?= number_format($it['subtotal'], 2) ?> €</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <p style="text-align:right; margin-top:1rem; font-weight:bold;">
      Importe productos: <?= number_format($importeProductos, 2) ?> €
    </p>

  <?php elseif ($step === 'resumen'): ?>
    <h3>Resumen de la compra</h3>

    <p><strong>Tipo de compra:</strong>
      <?= $data['tipo'] === 'registrado' ? 'Usuario registrado' : 'Invitado' ?>
    </p>

    <p><strong>Datos de envío</strong><br>
      <?= h($data['nombre']) ?><br>
      <?= h($data['direccion']) ?><br>
      <?= h($data['cp']) . ' ' . h($data['poblacion']) ?><br>
      <?php if ($data['provincia']): ?>
        <?= h($data['provincia']) ?><br>
      <?php endif; ?>
      <?= h($data['pais']) ?><br>
      Email: <?= h($data['email']) ?>
    </p>

    <h4>Productos</h4>
    <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
      <thead>
      <tr>
        <th style="text-align:left; border-bottom:1px solid #e5e7eb;">Producto</th>
        <th style="text-align:right; border-bottom:1px solid #e5e7eb;">Precio</th>
        <th style="text-align:center; border-bottom:1px solid #e5e7eb;">Cantidad</th>
        <th style="text-align:right; border-bottom:1px solid #e5e7eb;">Subtotal</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td>
            <?= h($it['nombre']) ?>
            <?php if ($it['capacidad_ml']): ?>
              (<?= (int)$it['capacidad_ml'] ?> ml)
            <?php endif; ?>
          </td>
          <td style="text-align:right;"><?= number_format($it['precio'], 2) ?> €</td>
          <td style="text-align:center;"><?= (int)$it['cantidad'] ?></td>
          <td style="text-align:right;"><?= number_format($it['subtotal'], 2) ?> €</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <?php
      $gastosEnvio = 0.00;
      $impuestos   = round($importeProductos * 0.21, 2);
      $total       = $importeProductos + $gastosEnvio + $impuestos;
    ?>

    <p style="text-align:right; margin-top:1rem;">
      Importe productos: <?= number_format($importeProductos, 2) ?> €<br>
      IVA (21%): <?= number_format($impuestos, 2) ?> €<br>
      Gastos de envío: <?= number_format($gastosEnvio, 2) ?> €<br>
      <strong>Total: <?= number_format($total, 2) ?> €</strong>
    </p>

    <form method="post" action="checkout.php" style="margin-top:1.5rem;">
      <?= csrf_field() ?>
      <input type="hidden" name="step" value="confirm">
      <button type="submit">Confirmar pedido</button>
    </form>

    <p style="margin-top:1rem;">
      <a href="checkout.php">Volver y modificar datos</a>
    </p>

  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

