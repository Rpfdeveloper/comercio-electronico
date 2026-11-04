<?php
declare(strict_types=1);

// Recuperación de contraseña de clientes registrados.
// Token de 256 bits, guardado solo como hash, válido 60 minutos y de un único uso.
require_once __DIR__ . '/customer.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/seo.php';

const RESET_MINUTES = 60;

class ResetInvalid extends RuntimeException {}

function reset_attempt_keys(string $email, string $ip): array
{
    $secret = auth_secret();
    return [hash_hmac('sha256', 'reset-email:' . strtolower(trim($email)), $secret) => 3,
        hash_hmac('sha256', 'reset-ip:' . $ip, $secret) => 20];
}

// Siempre devuelve sin revelar si la cuenta existe. Lanza CustomerRateLimit si se supera el límite.
function reset_request(PDO $pdo, string $email): void
{
    try { auth_take_attempts($pdo, reset_attempt_keys($email, (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'))); }
    catch (AuthRateLimit $e) { throw new CustomerRateLimit($e->getMessage()); }
    $customer = customer_find($pdo, $email);
    if ($customer === null || empty($customer['password_hash'])) { return; }
    $token = bin2hex(random_bytes(32));
    $pdo->beginTransaction();
    try {
        // Un enlace nuevo invalida los anteriores de la misma cuenta.
        $pdo->prepare('DELETE FROM cliente_recuperacion WHERE id_cliente=?')->execute([(int)$customer['id']]);
        $pdo->prepare('INSERT INTO cliente_recuperacion(token_hash,id_cliente,expira) VALUES (?,?,UTC_TIMESTAMP() + INTERVAL ' . RESET_MINUTES . ' MINUTE)')
            ->execute([hash('sha256', $token), (int)$customer['id']]);
        // La dirección sale de la configuración, nunca de la cabecera Host de la petición.
        $path = 'restablecer.php?token=' . $token;
        $absolute = seo_absolute($path);
        $link = $absolute !== '' ? $absolute : $path;
        $body = 'Hola' . ($customer['nombre'] ? ', ' . $customer['nombre'] : '') . ":\n\nHemos recibido una solicitud para cambiar la contraseña de tu cuenta en Perfume Shop.\n\n" .
            "Abre este enlace en la tienda durante los próximos " . RESET_MINUTES . " minutos:\n" . $link .
            ($absolute === '' ? "\n(Dirección relativa a la carpeta pública de la tienda.)" : '') .
            "\n\nSi no lo has pedido tú, ignora este mensaje: tu contraseña no cambiará.\n";
        mail_queue($pdo, 'recuperacion', null, $customer['email'], 'Cambia tu contraseña de Perfume Shop', $body);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function reset_token_valid($token): bool
{
    return is_string($token) && preg_match('/\A[0-9a-f]{64}\z/', $token) === 1;
}

function reset_find(PDO $pdo, $token, bool $lock = false): array
{
    if (!reset_token_valid($token)) { throw new ResetInvalid(); }
    $st = $pdo->prepare('SELECT r.id_cliente FROM cliente_recuperacion r WHERE r.token_hash=? AND r.usado IS NULL AND r.expira > UTC_TIMESTAMP()' . ($lock ? ' FOR UPDATE' : ''));
    $st->execute([hash('sha256', $token)]);
    $row = $st->fetch();
    if (!$row) { throw new ResetInvalid(); }
    return $row;
}

// Cambia la contraseña y consume el enlace. Las sesiones abiertas de la cuenta dejan de valer
// porque su prueba depende del hash anterior.
function reset_complete(PDO $pdo, $token, string $password): int
{
    $pdo->beginTransaction();
    try {
        $row = reset_find($pdo, $token, true);
        $pdo->prepare('UPDATE cliente_registrado SET password_hash=? WHERE id_cliente=?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$row['id_cliente']]);
        $pdo->prepare('UPDATE cliente_recuperacion SET usado=UTC_TIMESTAMP() WHERE token_hash=?')->execute([hash('sha256', $token)]);
        $pdo->prepare('DELETE FROM cliente_recuperacion WHERE id_cliente=? AND usado IS NULL')->execute([(int)$row['id_cliente']]);
        $pdo->commit();
        return (int)$row['id_cliente'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
