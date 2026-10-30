<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/customer.php';
customer_no_cache();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Utiliza el botón de cerrar sesión.');
}
customer_forget();
session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
header('Location: acceso.php', true, 303);
exit;
