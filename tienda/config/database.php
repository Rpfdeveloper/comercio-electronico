<?php
declare(strict_types=1);

/**
 * Conexión a la base de datos mediante PDO.
 */

require_once __DIR__ . '/config.php';

/**
 * Devuelve una única instancia de PDO.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {

        // Construcción del DSN
        $dsn = "mysql:host=" . DB_HOST .
               ";dbname=" . DB_NAME .
               ";charset=" . DB_CHARSET;

        // Opciones recomendadas
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Errores como excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Arrays asociativos
            PDO::ATTR_EMULATE_PREPARES   => false                   // Prepared reales
        ];

        // Crear conexión
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}