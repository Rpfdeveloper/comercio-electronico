# Pruebas de integración, cuenta y concurrencia

Estado actual: 41 comprobaciones de compra, 45 de cuenta/privacidad, 48 de administración y 29 de concurrencia: **163 por entorno**, más sintaxis y revisión de archivos sensibles. Validación local de esta iteración con PHP 8.3.6 y MariaDB 10.11.14. Los resultados anteriores se conservan más abajo como referencia.

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
$env:TIENDA_AUTH_SECRET='clave-ficticia-solo-pruebas-con-mas-de-32-bytes'
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
php tests/customer.php
php tests/admin.php
python3 tests/privacy.py
```

La URL y el DSN deben apuntar a la misma instalación. La cuenta de pruebas consulta y prepara datos; el servidor usa el usuario de aplicación. Con la clave administrativa definida deben pasar 41 comprobaciones. Si se omite, no se ejecuta el recorrido administrativo completo.

La suite prueba tokens ausentes, inválidos y de otra sesión; cantidades inválidas; productos y variantes no disponibles; relación producto-variante; altas, cambios y borrado del carrito; pedidos y descuento de stock; confirmaciones repetidas; cambios de precio y disponibilidad; acceso con cuentas nuevas y existentes; autenticación administrativa y cierre de sesión. Restaura el stock y elimina sus pedidos y clientes al terminar, también ante errores.

La suite de integración simula un cambio de stock entre resumen y confirmación. La suite independiente `tests/concurrency.php` comprueba además peticiones simultáneas reales. Ninguna de las dos es una prueba de carga. La inspección visual, accesibilidad, pagos y correo siguen pendientes.

## Compras simultáneas (Linux / macOS)

Requiere MariaDB, la extensión cURL y al menos dos procesos HTTP. La cuenta `TIENDA_TEST_USER` necesita el permiso `PROCESS` para observar `INNODB_LOCK_WAITS`; la cuenta de la aplicación no necesita ese permiso. Con las variables anteriores configuradas y desde la raíz:

```bash
mkdir -p tests/sessions
PHP_CLI_SERVER_WORKERS=4 php -d session.save_path="$PWD/tests/sessions" -d output_buffering=0 -S 127.0.0.1:8080 -t tienda
```

En otra terminal, con las mismas variables de pruebas:

```bash
php tests/integration.php
php tests/customer.php
php tests/admin.php
php tests/concurrency.php
python3 tests/privacy.py
```

El servidor integrado de PHP admite múltiples procesos desde PHP 7.4 y no soporta esa opción en Windows. En Windows, usa WSL o ejecuta las pruebas mediante GitHub Actions. Un servidor secuencial debe fallar en la prueba de concurrencia: no basta con lanzar dos peticiones que se atiendan una detrás de otra.

La prueba mantiene temporalmente un bloqueo de base de datos. Inicia la segunda confirmación cuando la primera ya está esperando y no libera el bloqueo hasta observar dos transacciones distintas esperando a la vez. Las observaciones se separan 250 ms para permitir que InnoDB actualice su información de bloqueos. Después comprueba las respuestas HTTP, los pedidos, sus líneas y el stock:

- Última unidad de una variante: una compra y un conflicto 409.
- Última unidad de un producto sin variantes: mismo resultado.
- Dos productos añadidos en orden inverso, con una unidad de cada uno: un pedido completo y ninguna compra parcial.
- Los mismos carritos, con dos unidades disponibles: ambos pedidos completos.
- Dos confirmaciones simultáneas de la misma sesión: un solo pedido y un solo descuento.

Utiliza correos `example.invalid`, restaura el stock y elimina sus pedidos y el producto temporal al terminar, también ante un fallo controlado. No ejecutar las suites en paralelo sobre la misma base.

## GitHub Actions

`.github/workflows/tienda-tests.yml` ejecuta las cuatro suites cuando cambian la aplicación, las pruebas o el workflow en `develop` o `main`, y en pull requests hacia esas ramas. La matriz prueba PHP 8.3 y 8.4 con MariaDB 11.4 en una base desechable por job. También comprueba la sintaxis de todos los PHP y rechaza avisos, errores y deprecaciones del log.

El workflow solo tiene permisos de lectura del repositorio; sus contraseñas ficticias sirven exclusivamente para los servicios temporales de CI. Las acciones se fijan a commits concretos. Resultado verificado el 30 de octubre de 2026: **41 comprobaciones de integración + 29 de concurrencia por versión de PHP**, ambas ejecuciones correctas; 21 archivos PHP sin errores de sintaxis y logs sin avisos. [Ejecución verificada](https://github.com/Rpfdeveloper/comercio-electronico/actions/runs/41126154248). Los resultados posteriores aparecen en [GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions).

Referencias técnicas: [servidor integrado de PHP](https://www.php.net/manual/en/features.commandline.webserver.php) y [bloqueos observables en MariaDB](https://mariadb.com/docs/server/reference/system-tables/information-schema/information-schema-tables/information-schema-innodb-tables/information-schema-innodb_lock_waits-table).

## Variables de la aplicación
`TIENDA_DB_HOST`, `TIENDA_DB_PORT`, `TIENDA_DB_NAME`, `TIENDA_DB_USER`, `TIENDA_DB_PASS`, `TIENDA_ADMIN_PASS`, `TIENDA_AUTH_SECRET` y `TIENDA_DEBUG` se leen del entorno del proceso PHP. No se carga automáticamente un archivo `.env`. La depuración está desactivada por defecto. Servidor y suites deben compartir `TIENDA_AUTH_SECRET`; para la cuenta se requiere la migración 002, aplicada por `prepare_database.php`.

## Cuenta y privacidad

`tests/customer.php` comprueba autorización del historial/detalle, pedidos ajenos e invitados, paginación, escape HTML, cabeceras de caché, CSRF, renovación e invalidación de sesiones, cierre de sesión conservando el carrito, checkout autenticado, límite compartido entre endpoints, vencimiento y limpieza de contadores y límites de contraseñas. La caducidad temporal se comprueba llamando al validador con marcas de tiempo controladas. El límite por IP se prepara en la BD para comprobar el rechazo del siguiente intento. Elimina sus clientes/pedidos y limpia los contadores de la base desechable; ejecutar las suites secuencialmente y sin usuarios reales.

`python3 tests/privacy.py` revisa archivos versionados y nuevos no ignorados: rechaza ficheros de entorno, sesiones, logs, claves privadas, algunos formatos de tokens y correos fuera de los dominios de ejemplo o `noreply`. No imprime el contenido sospechoso. Esta revisión es acotada, no detecta todos los secretos ni revisa exhaustivamente binarios o commits anteriores. Revisar también el diff antes de publicar.


## Administración

`tests/admin.php` requiere `TIENDA_TEST_ADMIN_PASS` y el mismo `TIENDA_AUTH_SECRET` que el servidor. Comprueba el aislamiento entre cuenta y panel, cookies con alcance administrativo, renovación de identificador/CSRF, expiración y rotación de configuración, límites separados de clientes y competencia de dos solicitudes por el último intento permitido. El secreto y la contraseña son ficticios en CI. Ejecutar secuencialmente: la suite limpia los contadores de la base desechable y elimina sus pedidos y cliente.
