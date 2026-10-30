<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/init.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Utiliza el botón de cerrar sesión.');
}
unset($_SESSION['admin_logged']);
session_regenerate_id(true);
header('Location: login.php');
exit;
