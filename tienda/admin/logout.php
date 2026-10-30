<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Utiliza el botón de cerrar sesión.');
}
$_SESSION = [];
session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
header('Location: login.php', true, 302);
exit;
