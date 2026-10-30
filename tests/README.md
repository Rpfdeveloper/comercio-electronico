# Pruebas locales de integración

Esta suite usa peticiones HTTP reales, sesiones independientes y una base MariaDB/MySQL desechable cuyo nombre termina en `_test`. No utiliza pagos ni correo reales.

Requisitos: PHP CLI con PDO MySQL y cURL; MariaDB/MySQL local en marcha. El script de preparación requiere una cuenta con permiso para crear la base de pruebas y su usuario. No ejecutar contra una instalación comercial.

## Preparación (PowerShell)
Desde la raíz del repositorio, sustituir las contraseñas de ejemplo y adaptar el puerto:

```powershell
$env:TIENDA_TEST_DSN='mysql:host=127.0.0.1;port=3306;dbname=tienda_perfumes_test'
$env:TIENDA_TEST_USER='root'
$env:TIENDA_TEST_PASS='CONTRASENA_LOCAL_DE_ADMINISTRACION'
$env:TIENDA_DB_PASS='CONTRASENA_LOCAL_DE_PRUEBAS'
php tests/prepare_database.php --reset
```

`--reset` recrea las tablas de la base de pruebas y restablece el usuario dedicado `tienda_test_user@localhost`. El script rechaza servidores remotos y nombres sin sufijo `_test`.

## Servidor de prueba
En la misma consola:

```powershell
$env:TIENDA_DB_HOST='127.0.0.1'
$env:TIENDA_DB_PORT='3306'
$env:TIENDA_DB_NAME='tienda_perfumes_test'
$env:TIENDA_DB_USER='tienda_test_user'
$env:TIENDA_ADMIN_PASS='CLAVE_ADMIN_SOLO_DE_PRUEBAS'
$env:TIENDA_DEBUG='true'
New-Item -ItemType Directory -Force tests/sessions | Out-Null
$sessionPath=(Resolve-Path tests/sessions).Path
php -d "session.save_path=$sessionPath" -d output_buffering=0 -S 127.0.0.1:8080 -t tienda
```

## Ejecutar
En otra consola, definir las mismas variables `TIENDA_TEST_DSN`, `TIENDA_TEST_USER` y `TIENDA_TEST_PASS`, y ejecutar:

```powershell
$env:TIENDA_TEST_URL='http://127.0.0.1:8080'
$env:TIENDA_TEST_ADMIN_PASS='CLAVE_ADMIN_SOLO_DE_PRUEBAS'
php tests/integration.php
```

La URL y el DSN deben apuntar a la misma instalación. La cuenta de pruebas consulta y prepara datos; el servidor usa el usuario de aplicación. Con la clave administrativa definida deben pasar 41 comprobaciones. Si se omite, no se ejecuta el recorrido administrativo completo.

La suite prueba tokens ausentes, inválidos y de otra sesión; cantidades inválidas; productos y variantes no disponibles; relación producto-variante; altas, cambios y borrado del carrito; pedidos y descuento de stock; confirmaciones repetidas; cambios de precio y disponibilidad; acceso con cuentas nuevas y existentes; autenticación administrativa y cierre de sesión. Restaura el stock y elimina sus pedidos y clientes al terminar, también ante errores.

Las pruebas simulan un cambio de stock entre resumen y confirmación. Todavía no constituyen una prueba de carga ni de peticiones concurrentes. La inspección visual, accesibilidad, pagos y correo siguen pendientes.

## Variables de la aplicación
`TIENDA_DB_HOST`, `TIENDA_DB_PORT`, `TIENDA_DB_NAME`, `TIENDA_DB_USER`, `TIENDA_DB_PASS`, `TIENDA_ADMIN_PASS` y `TIENDA_DEBUG` se leen del entorno del proceso PHP. No se carga automáticamente un archivo `.env`. La depuración está desactivada por defecto.

