<?php
declare(strict_types=1);

/**
 * Arranque de la aplicación (sesión + configuración + helpers).
 */

require_once __DIR__ . '/../src/lib/security.php';

// Cargar configuración y conexión
require_once __DIR__ . '/config.php';
ini_set('display_errors', APP_DEBUG ? '1' : '0');
require_once __DIR__ . '/database.php';

// Funciones auxiliares (escape, etc.)
require_once __DIR__ . '/../src/lib/helpers.php';
require_once __DIR__ . '/../src/lib/favorites.php';
require_once __DIR__ . '/../src/lib/seo.php';
