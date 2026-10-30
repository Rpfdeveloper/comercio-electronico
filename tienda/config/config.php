<?php
declare(strict_types=1);

/**
 * Configuración global de la aplicación.
 */

// Nombre de la tienda
const APP_NAME = 'Perfume Shop';

// Modo depuración
const APP_DEBUG = true;

/**
 * BASE_URL dinámica.
 * Permite que la tienda funcione aunque se cambie el nombre de la carpeta.
 */
define(
    'BASE_URL',
    rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/')
);

// Parámetros de conexión a la base de datos
const DB_HOST    = 'localhost';
const DB_NAME    = 'tienda_perfumes';
const DB_USER    = 'tienda_user';
define('DB_PASS', getenv('TIENDA_DB_PASS') ?: '');
const DB_CHARSET = 'utf8mb4';


