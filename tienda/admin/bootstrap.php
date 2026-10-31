<?php
declare(strict_types=1);

// Cookie y almacenamiento de sesión independientes de la tienda pública.
// La ruta incluye el prefijo de instalación, por ejemplo /tienda/admin/.
session_name('TIENDAADMIN');
define('TIENDA_SESSION_PATH', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/');
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../src/lib/admin.php';
require_once __DIR__ . '/../src/lib/admin_view.php';
auth_no_cache();
header('Referrer-Policy: no-referrer');
