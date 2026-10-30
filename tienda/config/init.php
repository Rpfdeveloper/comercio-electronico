<?php
declare(strict_types=1);

/**
 * Arranque de la aplicación (sesión + configuración + helpers).
 */

// Iniciar sesión si no existe
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar configuración y conexión
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Funciones auxiliares (escape, etc.)
require_once __DIR__ . '/../src/lib/helpers.php';