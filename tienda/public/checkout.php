<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/cart.php';
require_once __DIR__ . '/../src/lib/customer.php';
require_once __DIR__ . '/../src/lib/checkout.php';
customer_no_cache();
foreach (['step', 'tipo', 'email', 'nombre', 'direccion', 'cp', 'poblacion', 'provincia', 'pais', 'password'] as $field) {
    if (isset($_POST[$field]) && !is_string($_POST[$field])) {
        unset($_SESSION['checkout']);
        http_response_code(422);
        exit('Los datos del formulario no son válidos.');
    }
}

if (isset($_POST['password']) && (strlen($_POST['password']) > 4096 || strpos($_POST['password'], "\0") !== false)) {
    unset($_SESSION['checkout']);
    http_response_code(422);
    exit('Los datos del formulario no son válidos.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($_POST['step'] ?? 'form', ['form', 'confirm'], true)) {
    unset($_SESSION['checkout']);
    http_response_code(422);
    require __DIR__ . '/includes/header.php';
    echo '<p role="alert">El paso de compra no es válido. <a href="checkout.php">Volver a tus datos</a>.</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Carrito obligatorio
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    require_once __DIR__ . '/includes/header.php';
    echo '<section class="container" style="margin-top:2rem;">
            <h2>Carrito de la compra</h2>
            <p>Tu carrito está vacío. Añade algún producto antes de tramitar la compra.</p><a href="catalogo.php">Ver perfumes</a>
          </section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = db();
$currentCustomer = customer_current($pdo);
$cart =& $_SESSION['cart'];

// Volver a validar en servidor; nunca aceptar precios ni stock del formulario.
try {
    $items = cart_items($pdo, $cart);
} catch (DomainException $e) {
    http_response_code(409);
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="commerce-error" role="alert"><p>' . h($e->getMessage()) . '</p><a href="carrito.php">Revisar el carrito</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

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
$stored = $_SESSION['checkout'] ?? ($currentCustomer ?? []);

$data = [
    'tipo'      => $_POST['tipo']      ?? ($stored['tipo']      ?? ($currentCustomer ? 'registrado' : 'invitado')),
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

    $errors = checkout_validate($data);

    if ($data['tipo'] === 'registrado' && $data['password'] === '' &&
        !($currentCustomer && strcasecmp($currentCustomer['email'], $data['email']) === 0)) {
        $errors['password'] = 'Introduce una contraseña para el usuario registrado.';
    }

    // Acreditar la cuenta antes del resumen; no guardar contraseñas en claro.
    $accountProof = null;
    $newPasswordHash = null;
    if (!$errors && $data['tipo'] === 'registrado') {
        try {
            if ($currentCustomer && strcasecmp($currentCustomer['email'], $data['email']) === 0) {
                $existing = $currentCustomer;
                $authenticated = true;
            } else {
                customer_take_attempt($pdo, $data['email']);
                $existing = customer_find($pdo, $data['email']);
                $authenticated = customer_password_matches($existing, $data['password']);
            }
            if ($existing) {
                if (!$authenticated) {
                    $errors['password'] = 'No se ha podido autenticar la cuenta.';
                } else {
                    customer_clear_attempts($pdo, $data['email']);
                    $accountProof = [
                        'id' => (int)$existing['id'],
                        'hash' => hash('sha256', $existing['password_hash']),
                    ];
                }
            } elseif (!customer_valid_new_password($data['password'])) {
                $errors['password'] = 'Usa al menos 15 caracteres y como máximo 72 bytes para crear tu cuenta.';
            } else {
                $newPasswordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            }
        } catch (CustomerRateLimit $e) {
            http_response_code(429);
            header('Retry-After: 900');
            $errors['general'] = $e->getMessage();
        } catch (Throwable $e) {
            http_response_code(503);
            $errors['general'] = 'El acceso de clientes no está disponible. Inténtalo más tarde.';
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
    } elseif (http_response_code() === 200) {
        http_response_code(422);
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

    // Los importes definitivos se calculan después de releer y bloquear el carrito.

    try {
        $pdo->beginTransaction();
        // Releer precios y bloquear stock dentro de la misma transacción que el pedido.
        $items = cart_items($pdo, $cart, true);
        if (($data['cart_snapshot'] ?? null) !== $items) {
            throw new DomainException('El carrito o los precios han cambiado. Revisa el resumen de nuevo.');
        }
        $totals = cart_totals($items);
        $importeProductos = $totals['products'];
        $impuestos = $totals['tax'];
        $gastosEnvio = $totals['shipping'];
        $total = $totals['total'];
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

        echo '<section class="commerce commerce-panel commerce-success" tabindex="-1" data-focus-on-load>
                <p class="account-eyebrow">PEDIDO REGISTRADO</p><h2>Pedido realizado</h2>
                <p>Tu pedido se ha registrado correctamente con el número <strong>#' . (int)$idPedido . '</strong>.</p>
                <p>Total del pedido: <strong>' . money_eur($total) . '</strong>.</p>
                <p>Correo de contacto del pedido: <strong>' . h($data['email']) . '</strong>.</p>
                <a class="btn" href="catalogo.php">Seguir comprando</a>
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
        unset($_SESSION['checkout']);
        $step = 'form'; // El siguiente intento requiere revisar de nuevo los datos.
    }
}
$totals = cart_totals($items);
require __DIR__ . '/includes/checkout-view.php';
