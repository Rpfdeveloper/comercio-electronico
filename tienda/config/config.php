<?php
declare(strict_types=1);

const APP_NAME = 'Perfume Shop';
define('APP_DEBUG', filter_var(getenv('TIENDA_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));

define(
    'BASE_URL',
    rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/')
);

// Las variables de entorno permiten separar desarrollo, pruebas y producción.
define('DB_HOST', getenv('TIENDA_DB_HOST') ?: 'localhost');
define('DB_PORT', (int)(getenv('TIENDA_DB_PORT') ?: 3306));
define('DB_NAME', getenv('TIENDA_DB_NAME') ?: 'tienda_perfumes');
define('DB_USER', getenv('TIENDA_DB_USER') ?: 'tienda_user');
define('DB_PASS', getenv('TIENDA_DB_PASS') ?: '');
const DB_CHARSET = 'utf8mb4';
