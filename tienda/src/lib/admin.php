<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

function admin_proof(): string
{
    $password = getenv('TIENDA_ADMIN_PASS') ?: '';
    if ($password === '') { throw new RuntimeException('Administration configuration unavailable'); }
    return hash_hmac('sha256', 'admin-session:' . $password, auth_secret());
}

function admin_current(): bool
{
    // No aceptar el indicador booleano de versiones anteriores.
    unset($_SESSION['admin_logged']);
    $session = $_SESSION['admin'] ?? null;
    if (!is_array($session)) { return false; }
    try {
        $valid = time() - ($session['last_active'] ?? 0) <= 900 &&
            time() - ($session['started'] ?? 0) <= 14400 &&
            hash_equals(admin_proof(), (string)($session['proof'] ?? ''));
    } catch (Throwable $e) { $valid = false; }
    if (!$valid) { unset($_SESSION['admin']); return false; }
    $_SESSION['admin']['last_active'] = time();
    return true;
}

function admin_require(): void
{
    auth_no_cache();
    header('Referrer-Policy: no-referrer');
    if (!admin_current()) {
        header('Location: login.php', true, 302);
        exit;
    }
}

function admin_sign_in(): void
{
    $proof = admin_proof();
    session_regenerate_id(true);
    $_SESSION = ['csrf_token'=>bin2hex(random_bytes(32)),
        'admin'=>['proof'=>$proof,'started'=>time(),'last_active'=>time()]];
}

function admin_attempt_keys(string $ip): array
{
    $secret = auth_secret();
    // Una única cuenta administrativa: cambiar el usuario no reinicia el límite.
    // Los prefijos separan estos contadores de los de clientes.
    return [hash_hmac('sha256','admin:account:admin',$secret)=>5,
        hash_hmac('sha256','admin:ip:'.$ip,$secret)=>30];
}
