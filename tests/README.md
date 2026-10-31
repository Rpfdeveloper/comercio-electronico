# Pruebas de la tienda

Estado actual: **630 comprobaciones por entorno**, distribuidas en 13 suites: compra (41), cuenta/privacidad (45), acceso administrativo (48), consulta de pedidos (64), inventario (50), gestión del catálogo (75), catálogo público (73), checkout (47), portada/descubrimiento/ayuda (33), concurrencia de compra (32), favoritos invitados (38), favoritos de cuenta (45) y SEO (39). Además se verifica la sintaxis de 64 PHP, el log y los patrones de archivos sensibles. Validación local con PHP 8.3.6 y MariaDB 10.11.14; CI configurada para PHP 8.3, 8.4 y 8.5.

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
php tests/admin_orders.php
php tests/admin_inventory.php
php tests/catalog.php
php tests/checkout.php
php tests/storefront.php
php tests/favorites.php
php tests/favorites_account.php
php tests/seo.php
python3 tests/privacy.py
```

La URL y el DSN deben apuntar a la misma instalación. La cuenta de pruebas consulta y prepara datos; el servidor usa el usuario de aplicación. Con la clave administrativa definida deben pasar 41 comprobaciones. Si se omite, no se ejecuta el recorrido administrativo completo.

La suite prueba tokens ausentes, inválidos y de otra sesión; cantidades inválidas; productos y variantes no disponibles; relación producto-variante; altas, cambios y borrado del carrito; pedidos y descuento de stock; confirmaciones repetidas; cambios de precio y disponibilidad; acceso con cuentas nuevas y existentes; autenticación administrativa y cierre de sesión. Restaura el stock y elimina sus pedidos y clientes al terminar, también ante errores.

La suite de integración simula un cambio de stock entre resumen y confirmación. La suite independiente `tests/concurrency.php` comprueba además peticiones simultáneas reales. Ninguna de las dos es una prueba de carga. La revisión visual parcial se documenta en `docs/COMPRA.md`; no sustituye una auditoría de accesibilidad ni pruebas en dispositivos reales. Pagos y correo siguen pendientes.

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
php tests/admin_orders.php
php tests/admin_inventory.php
php tests/catalog.php
php tests/admin_catalog.php
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

`.github/workflows/tienda-tests.yml` ejecuta las trece suites cuando cambian la aplicación, las pruebas o el workflow en `develop`, `main` o ramas `mejora/**`, y en pull requests hacia esas ramas. La matriz prueba PHP 8.3, 8.4 y 8.5 con MariaDB 11.4 en una base desechable por job. También comprueba la sintaxis de todos los PHP y rechaza avisos, errores y deprecaciones del log.

El workflow solo tiene permisos de lectura del repositorio; sus contraseñas ficticias sirven exclusivamente para los servicios temporales de CI. Las acciones se fijan a commits concretos. Resultado verificado el 30 de octubre de 2026: **41 comprobaciones de integración + 29 de concurrencia por versión de PHP**, ambas ejecuciones correctas; 21 archivos PHP sin errores de sintaxis y logs sin avisos. [Ejecución verificada](https://github.com/Rpfdeveloper/comercio-electronico/actions/runs/41126154248). Los resultados posteriores aparecen en [GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions).

Referencias técnicas: [servidor integrado de PHP](https://www.php.net/manual/en/features.commandline.webserver.php) y [bloqueos observables en MariaDB](https://mariadb.com/docs/server/reference/system-tables/information-schema/information-schema-tables/information-schema-innodb-tables/information-schema-innodb_lock_waits-table).

## Variables de la aplicación
`TIENDA_DB_HOST`, `TIENDA_DB_PORT`, `TIENDA_DB_NAME`, `TIENDA_DB_USER`, `TIENDA_DB_PASS`, `TIENDA_ADMIN_PASS`, `TIENDA_AUTH_SECRET` y `TIENDA_DEBUG` se leen del entorno del proceso PHP. No se carga automáticamente un archivo `.env`. La depuración está desactivada por defecto. Servidor y suites deben compartir `TIENDA_AUTH_SECRET`; se requieren las migraciones 001–004, aplicadas por `prepare_database.php`.

## Cuenta y privacidad

`tests/customer.php` comprueba autorización del historial/detalle, pedidos ajenos e invitados, paginación, escape HTML, cabeceras de caché, CSRF, renovación e invalidación de sesiones, cierre de sesión conservando el carrito, checkout autenticado, límite compartido entre endpoints, vencimiento y limpieza de contadores y límites de contraseñas. La caducidad temporal se comprueba llamando al validador con marcas de tiempo controladas. El límite por IP se prepara en la BD para comprobar el rechazo del siguiente intento. Elimina sus clientes/pedidos y limpia los contadores de la base desechable; ejecutar las suites secuencialmente y sin usuarios reales.

`python3 tests/privacy.py` revisa archivos versionados y nuevos no ignorados: rechaza ficheros de entorno, sesiones, logs, claves privadas, algunos formatos de tokens y correos fuera de los dominios de ejemplo o `noreply`. No imprime el contenido sospechoso. Esta revisión es acotada, no detecta todos los secretos ni revisa exhaustivamente binarios o commits anteriores. Revisar también el diff antes de publicar.


## Administración

`tests/admin.php` requiere `TIENDA_TEST_ADMIN_PASS` y el mismo `TIENDA_AUTH_SECRET` que el servidor. Comprueba el aislamiento entre cuenta y panel, cookies con alcance administrativo, renovación de identificador/CSRF, expiración y rotación de configuración, límites separados de clientes y competencia de dos solicitudes por el último intento permitido. El secreto y la contraseña son ficticios en CI. Ejecutar secuencialmente: la suite limpia los contadores de la base desechable y elimina sus pedidos y cliente.

## Catálogo

`tests/catalog.php` crea categorías, marcas, productos y variantes ficticios y comprueba búsqueda literal, acentos, escape HTML, filtros combinados, precios de oferta, disponibilidad, ordenación estable, paginación con filtros conservados y errores 404/422. Contrasta el precio de una variante con el carrito y comprueba las fichas ocultas o agotadas. Elimina sus fixtures al terminar, también ante un fallo controlado. La conexión de pruebas utiliza UTF-8 explícito para no alterar las fixtures con acentos.

No es una prueba de rendimiento con catálogos grandes. [Comportamiento y límites del catálogo](../docs/CATALOGO.md).

## Categorías retiradas durante una compra

La suite de catálogo comprueba el rechazo de altas directas al carrito, cambios de cantidades, entrada al checkout y confirmaciones de resúmenes anteriores cuando la categoría está inactiva, con y sin variantes. También verifica que el artículo se pueda eliminar y que no se creen pedidos ni se descuente stock. Dos conexiones comprueban que el bloqueo compartido conserva la categoría hasta terminar la transacción.

La suite de concurrencia retira una categoría mientras dos confirmaciones ya han pasado la validación inicial y esperan el producto: ambas deben devolver 409, conservar stock y no registrar pedidos. Las pruebas anteriores siguen comprobando que dos compras de la misma categoría pueden completarse cuando hay existencias suficientes.

## Carrito y checkout

`php tests/checkout.php` añade 47 comprobaciones HTTP: guardar cantidades al continuar, total e IVA coherentes hasta la base de datos, rechazo sin modificar el carrito, campos obligatorios y longitudes Unicode, controles y UTF-8 inválido, tipo de compra, contraseña, formularios malformados, invalidación de resúmenes anteriores, escape HTML, conservación de datos, provincia opcional, vaciado y recuperación ante falta de stock. Crea una compra ficticia y la elimina al terminar, restaurando el producto. El resto de suites conserva las comprobaciones de cuentas, permisos y concurrencia.

## Portada y descubrimiento

`php tests/storefront.php` incluye 33 comprobaciones HTTP sobre portada, colecciones, precios de variantes, disponibilidad, búsqueda en descripción larga, entradas malformadas, escape HTML, estados vacíos y paginación. Prepara y elimina fixtures; modifica temporalmente categorías solo en la base desechable y restaura su estado. No ejecutar suites en paralelo sobre la misma base.

La CI incluye PHP 8.5, además de 8.3 y 8.4. Se libera el objeto cURL sin la llamada obsoleta a `curl_close()`. [Detalles y límites de esta entrega](../docs/DESCUBRIMIENTO.md).

## Favoritos y SEO

`tests/favorites.php` comprueba persistencia entre sesiones, cookie acotada, CSRF, entradas malformadas, redirecciones, repetición, retirada de productos y lectura actual de precios y stock.

`tests/seo.php` comprueba noindex por defecto y levanta temporalmente un segundo servidor PHP local con configuración de lanzamiento, dominio ficticio y la misma base desechable. Requiere `proc_open` y sockets TCP locales. La consola de pruebas debe recibir también las variables de aplicación `TIENDA_DB_*` indicadas para el servidor. Comprueba metadatos, paginación, exclusiones y que cada URL del sitemap coincide con una página indexable. Elimina el proceso, sesiones y fixtures al terminar; no conecta con el dominio ficticio ni con Google. El servidor habitual de pruebas debe conservar `TIENDA_DEBUG=true` y `TIENDA_INDEXABLE` desactivado.

[Decisiones y fuentes](../docs/RETENCION_SEO.md).

## Favoritos de cuenta

`tests/favorites_account.php` usa dos clientes ficticios, sesiones independientes y cuatro escenarios concurrentes. Requiere al menos dos workers PHP; la cuenta de pruebas necesita PROCESS para observar bloqueos y TRIGGER para simular un fallo SQL durante una unión. La cuenta de aplicación no necesita estos permisos. La prueba elimina el trigger y sus fixtures también ante errores, y verifica el reintento tras la reversión.

Comprueba unión elegida, identidad obtenida de la sesión, acceso/modificación ajenos, CSRF, cambio de sesión, cierre, precios/stock, límite, persistencia, migración repetida y cascadas de referencias. La barrera exige observar dos transacciones a la vez: no basta con lanzar solicitudes que se atiendan en serie. No es una prueba de carga ni de dispositivos físicos. Ejecutar las suites secuencialmente sobre la base desechable.

## Consulta administrativa de pedidos

`tests/admin_orders.php` incorpora 64 comprobaciones HTTP sobre 30 pedidos ficticios y un estado personalizado temporal. Comprueba orden y paginación estable, búsqueda por número/nombre/correo, caracteres literales, resumen de todo el resultado, estados, fechas inclusivas, filtros combinados, retorno desde el detalle, no-store/noindex/no-referrer, entradas malformadas, SQL/XSS y conservación de los registros. Elimina sus pedidos, líneas y estado temporal al finalizar. Se ejecuta sobre la misma base local desechable que las demás suites; no prepara datos de producción. La revisión visual utiliza muestras separadas y no demuestra funcionamiento de formularios: ese comportamiento lo verifican las peticiones HTTP.


## Gestión administrativa del catálogo

`tests/admin_catalog.php` comprueba altas inactivas, edición, formatos, precios, ofertas, existencias, historial, conservación de pedidos y del enlace público, acceso y CSRF, errores 404/409/422, duplicados, Unicode, SQL/XSS, datos malformados y reversión completa si falla el historial. Repite la migración aditiva y comprueba dos carreras reales: edición contra edición y edición contra compra.

Requiere Linux/macOS con al menos dos workers PHP y una cuenta de pruebas con PROCESS y TRIGGER, como favoritos de cuenta. No añadirla a un servidor secuencial de Windows: usar WSL o CI. Las barreras observan dos transacciones esperando el mismo producto; no es una prueba de carga. La cuenta de aplicación no necesita esos permisos. Limpia sus fixtures y trigger al terminar, incluso si falla; ejecutar secuencialmente sobre la base desechable.

La revisión visual de las pantallas administrativas usa HTML obtenido mediante peticiones autenticadas a esa base, con datos ficticios y formularios desactivados. Las escrituras se comprueban por HTTP; esas muestras visuales no se publican como páginas de la tienda.
