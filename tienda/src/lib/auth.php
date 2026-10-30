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
    // Incluso con orden estable, InnoDB puede detectar un interbloqueo.
    // Reintentar la transacción completa nunca omite la comprobación de límites.
    for ($attempt = 0; $attempt < 3; ++$attempt) {
        try {
            $pdo->exec('DELETE FROM acceso_intento WHERE ventana < UTC_TIMESTAMP() - INTERVAL 1 DAY LIMIT 100');
            $pdo->beginTransaction();
            foreach ($keys as $key => $limit) {
                // Tomar un bloqueo exclusivo desde el alta/lectura del contador.
                // INSERT IGNORE puede tomar un bloqueo compartido que luego compite
                // con el FOR UPDATE de otra solicitud sobre la misma fila.
                $q = $pdo->prepare('INSERT INTO acceso_intento (clave,ventana,intentos) VALUES (?,UTC_TIMESTAMP(),0)
                    ON DUPLICATE KEY UPDATE clave=acceso_intento.clave');
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
            return;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($e instanceof PDOException && in_array((int)($e->errorInfo[1] ?? 0), [1205,1213], true) && $attempt < 2) {
                usleep(random_int(1000,5000));
                continue;
            }
            throw $e;
        }
    }
}
