<?php
declare(strict_types=1);

class AuthRateLimit extends RuntimeException {}

function auth_secret(): string
{
    $secret = getenv('TIENDA_AUTH_SECRET') ?: '';
    if (strlen($secret) < 32) { throw new RuntimeException('Authentication configuration unavailable'); }
    return $secret;
}

function auth_no_cache(): void
{
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('Referrer-Policy: same-origin');
}

// Las claves HMAC y los límites los define cada ámbito, nunca el formulario.
function auth_take_attempts(PDO $pdo, array $keys): void
{
    ksort($keys);
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
                throw new AuthRateLimit('Espera 15 minutos antes de volver a intentarlo.');
            }
            $pdo->prepare('UPDATE acceso_intento SET intentos=intentos+1 WHERE clave=?')->execute([$key]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
