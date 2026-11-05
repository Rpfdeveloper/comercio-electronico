<?php
declare(strict_types=1);

require_once __DIR__ . '/customer.php';
require_once __DIR__ . '/checkout.php';

class ProfileConflict extends DomainException {}
class ProfileSessionExpired extends RuntimeException {}
class ProfilePasswordInvalid extends DomainException {}

function profile_fields(): array
{
    $fields = checkout_fields();
    unset($fields['email']);
    return $fields;
}

function profile_values(array $customer): array
{
    $values = [];
    foreach (profile_fields() as $key => $field) { $values[$key] = (string)($customer[$key] ?? ''); }
    return $values;
}

function profile_version(array $customer): string
{
    return hash_hmac('sha256', json_encode([(int)$customer['id'], profile_values($customer)], JSON_THROW_ON_ERROR), auth_secret());
}

// Cuenta antes que datos personales: mismo orden que carrito y checkout.
// Se relee la prueba de sesión después de obtener el bloqueo.
function profile_lock(PDO $pdo, array $customer): array
{
    $q = $pdo->prepare('SELECT * FROM cliente_registrado WHERE id_cliente=? FOR UPDATE');
    $q->execute([(int)$customer['id']]);
    $row = $q->fetch();
    if (!$row || !hash_equals($customer['password_hash'], $row['password_hash'])) {
        throw new ProfileSessionExpired();
    }
    $q = $pdo->prepare('SELECT id,email,nombre FROM cliente WHERE id=? FOR UPDATE');
    $q->execute([(int)$customer['id']]);
    return array_merge($row, $q->fetch());
}

function profile_save(PDO $pdo, array $customer, array $data, string $version): void
{
    $pdo->beginTransaction();
    try {
        $current = profile_lock($pdo, $customer);
        if (!hash_equals(profile_version($current), $version)) {
            throw new ProfileConflict('Tus datos han cambiado desde que abriste el formulario. Recarga los datos guardados antes de volver a editar.');
        }
        $pdo->prepare('UPDATE cliente SET nombre=? WHERE id=?')->execute([$data['nombre'], (int)$customer['id']]);
        $pdo->prepare('UPDATE cliente_registrado SET direccion=?,cp=?,poblacion=?,provincia=?,pais=? WHERE id_cliente=?')
            ->execute([$data['direccion'], $data['cp'], $data['poblacion'], $data['provincia'] ?: null, $data['pais'], (int)$customer['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    unset($_SESSION['checkout']);
}

function profile_password_keys(int $id, string $ip): array
{
    return [hash_hmac('sha256', 'password-change:customer:' . $id, auth_secret()) => 5,
        hash_hmac('sha256', 'password-change:ip:' . $ip, auth_secret()) => 30];
}

function profile_change_password(PDO $pdo, array $customer, string $currentPassword, string $password): array
{
    auth_take_attempts($pdo, profile_password_keys((int)$customer['id'], (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')));
    if (!customer_password_matches($customer, $currentPassword)) {
        throw new ProfilePasswordInvalid('La contraseña actual no es correcta.');
    }
    if (password_verify($password, $customer['password_hash'])) {
        throw new ProfilePasswordInvalid('Elige una contraseña diferente de la actual.');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->beginTransaction();
    try {
        $current = profile_lock($pdo, $customer);
        $pdo->prepare('UPDATE cliente_registrado SET password_hash=? WHERE id_cliente=?')->execute([$hash, (int)$customer['id']]);
        // Un enlace emitido antes del cambio ya no puede restablecer esta contraseña.
        $pdo->prepare('DELETE FROM cliente_recuperacion WHERE id_cliente=?')->execute([(int)$customer['id']]);
        $pdo->commit();
        $current['password_hash'] = $hash;
        return $current;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
