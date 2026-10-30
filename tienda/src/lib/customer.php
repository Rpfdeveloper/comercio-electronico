<?php
declare(strict_types=1);

class CustomerRateLimit extends RuntimeException {}

function customer_no_cache(): void
{
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('Referrer-Policy: same-origin');
}

function customer_forget(): void
{
    unset($_SESSION['customer'], $_SESSION['checkout']);
}

function customer_current(PDO $pdo): ?array
{
    $session = $_SESSION['customer'] ?? null;
    if (!is_array($session)) { return null; }
    $now = time();
    if ($now - ($session['last_active'] ?? 0) > 1800 || $now - ($session['started'] ?? 0) > 28800) {
        customer_forget();
        return null;
    }
    $q = $pdo->prepare('SELECT c.id,c.email,c.nombre,cr.password_hash,cr.direccion,cr.cp,
        cr.poblacion,cr.provincia,cr.pais FROM cliente c
        JOIN cliente_registrado cr ON cr.id_cliente=c.id WHERE c.id=?');
    $q->execute([(int)($session['id'] ?? 0)]);
    $customer = $q->fetch();
    if (!$customer || !hash_equals((string)($session['proof'] ?? ''), hash('sha256', $customer['password_hash']))) {
        customer_forget();
        return null;
    }
    $_SESSION['customer']['last_active'] = $now;
    return $customer;
}

function customer_require(PDO $pdo): array
{
    customer_no_cache();
    $customer = customer_current($pdo);
    if ($customer === null) {
        header('Location: acceso.php', true, 302);
        exit;
    }
    return $customer;
}

function customer_sign_in(array $customer): void
{
    session_regenerate_id(true);
    unset($_SESSION['checkout']);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['customer'] = [
        'id' => (int)$customer['id'],
        'proof' => hash('sha256', $customer['password_hash']),
        'started' => time(),
        'last_active' => time(),
    ];
}

// Los contadores no contienen correos ni IP en claro. El secreto debe ser externo.
function customer_attempt_keys(string $email, string $ip): array
{
    $secret = getenv('TIENDA_AUTH_SECRET') ?: '';
    if (strlen($secret) < 32) { throw new RuntimeException('Authentication configuration unavailable'); }
    return [
        hash_hmac('sha256', 'email:' . strtolower(trim($email)), $secret) => 5,
        hash_hmac('sha256', 'ip:' . $ip, $secret) => 30,
    ];
}

function customer_take_attempt(PDO $pdo, string $email): void
{
    // No confiar en X-Forwarded-For sin una configuración explícita de proxies.
    $keys = customer_attempt_keys($email, (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    ksort($keys);
    // Limpieza acotada: no conservar indefinidamente identificadores de intentos.
    $pdo->exec('DELETE FROM acceso_intento WHERE ventana < UTC_TIMESTAMP() - INTERVAL 1 DAY LIMIT 100');
    try {
        $pdo->beginTransaction();
        foreach ($keys as $key => $limit) {
            $q = $pdo->prepare('INSERT IGNORE INTO acceso_intento (clave,ventana,intentos) VALUES (?,UTC_TIMESTAMP(),0)');
            $q->execute([$key]);
            $q = $pdo->prepare('SELECT intentos,(ventana <= UTC_TIMESTAMP() - INTERVAL 15 MINUTE) AS vencido
                FROM acceso_intento WHERE clave=? FOR UPDATE');
            $q->execute([$key]);
            $row = $q->fetch();
            if ((int)$row['vencido']) {
                $pdo->prepare('UPDATE acceso_intento SET intentos=0,ventana=UTC_TIMESTAMP() WHERE clave=?')->execute([$key]);
            } elseif ((int)$row['intentos'] >= $limit) {
                throw new CustomerRateLimit('Espera 15 minutos antes de volver a intentarlo.');
            }
            $pdo->prepare('UPDATE acceso_intento SET intentos=intentos+1 WHERE clave=?')->execute([$key]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function customer_find(PDO $pdo, string $email): ?array
{
    // Usar el mismo formato que el alta: la colación SQL puede igualar acentos.
    // Rechazar alias Unicode impide eludir el contador del correo original.
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) { return null; }
    $q = $pdo->prepare('SELECT c.id,c.email,c.nombre,cr.password_hash FROM cliente c
        LEFT JOIN cliente_registrado cr ON cr.id_cliente=c.id WHERE c.email=?');
    $q->execute([$email]);
    return $q->fetch() ?: null;
}

function customer_password_matches(?array $customer, string $password): bool
{
    // Hash público de una cadena ficticia para no omitir el trabajo en cuentas inexistentes.
    $dummy = '$2y$12$w8/JEGNz.E/ebzem3o143eSfh5jyjftks7s/YPRCeILgEVn39glJW';
    $valid = password_verify($password, $customer['password_hash'] ?? $dummy);
    return $customer !== null && !empty($customer['password_hash']) && $valid;
}

function customer_clear_attempts(PDO $pdo, string $email): void
{
    $keys = customer_attempt_keys($email, '');
    $pdo->prepare('DELETE FROM acceso_intento WHERE clave=?')->execute([array_key_first($keys)]);
    // El contador por IP se conserva incluso después de un acceso correcto.
}

function customer_valid_new_password(string $password): bool
{
    // Límite de bytes explícito: bcrypt no debe truncar silenciosamente la contraseña.
    return strpos($password, "\0") === false && strlen($password) <= 72 &&
        preg_match('/\A.{15,}\z/us', $password) === 1;
}
