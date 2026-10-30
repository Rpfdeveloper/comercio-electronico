<?php
declare(strict_types=1);

// Recrea exclusivamente las tablas de una base LOCAL con sufijo _test.
$dsn = getenv('TIENDA_TEST_DSN') ?: '';
if (($argv[1] ?? '') !== '--reset' ||
    !preg_match('/(?:^|;)host=(127\.0\.0\.1|localhost)(?:;|$)/', str_replace('mysql:', '', $dsn)) ||
    !preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+_test)(?:;|$)/', str_replace('mysql:', '', $dsn), $m)) {
    fwrite(STDERR, "Usa --reset y TIENDA_TEST_DSN local con base *_test. Se borran sus tablas.\n");
    exit(2);
}
$database = $m[1];
$password = getenv('TIENDA_DB_PASS') ?: '';
if ($password === '') { fwrite(STDERR, "Define TIENDA_DB_PASS para el usuario de pruebas.\n"); exit(2); }
$serverDsn = preg_replace('/;dbname=[^;]+/', '', $dsn);
$pdo = new PDO($serverDsn, getenv('TIENDA_TEST_USER') ?: 'root', getenv('TIENDA_TEST_PASS') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$sql = file_get_contents(__DIR__ . '/../tienda/sql/tienda_perfumes_schema.sql');
$sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
$sql = str_replace(['tienda_perfumes', 'tienda_user', "'CAMBIAR_ANTES_DE_IMPORTAR'"],
    [$database, 'tienda_test_user', $pdo->quote($password)], $sql);
$pdo->exec($sql);
echo "Base de pruebas preparada: $database; usuario de aplicación: tienda_test_user\n";
