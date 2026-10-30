# Progreso de la tienda

Registro técnico de cambios verificables en `develop`. La rama `original` conserva la entrega recuperada. El proyecto sigue siendo un prototipo: no procesa pagos ni envía correos reales.

## Panel de administración — nueva iteración

- Sesión propia `TIENDAADMIN`, con cookie limitada al directorio administrativo. Cerrar la administración conserva la cuenta y el carrito del cliente, y cerrar la cuenta no cierra el panel.
- Renovación de identificador y CSRF; caducidad tras 15 minutos de inactividad o cuatro horas totales. La huella de configuración invalida sesiones cuando cambia la contraseña o el secreto.
- Límites persistidos por cuenta e IP, separados de los de clientes. Cambiar el usuario enviado no reinicia el contador; una autenticación correcta conserva el contador por IP.
- Páginas sin caché, mensajes genéricos y 404 para pedidos inexistentes o identificadores malformados.
- Actualización de `actions/checkout` a [v7.0.1](https://github.com/actions/checkout/releases/tag/v7.0.1), fijada a su commit y con Node.js 24, conservando permisos de solo lectura y `persist-credentials: false`.
- Limitador común extraído a `auth.php`, conservando las claves de contadores y el comportamiento de los clientes. Se reutiliza la migración 002.
- La prueba de dos intentos detectó un 503 transitorio: se incorpora bloqueo exclusivo desde la lectura/alta del contador y reintento acotado de la transacción completa, conservando el límite y su rechazo 429.

Validación local con PHP 8.3.6 y MariaDB 10.11.14: **163 comprobaciones correctas** (41 de compra, 45 de cuenta, 48 administrativas y 29 de concurrencia), 31 PHP sin errores de sintaxis y log sin avisos. [Ejecución en GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions/runs/41137901221), sobre `6c80fe2a20361b5f92f0dd9cc5bd602536823b5d`: **163 comprobaciones correctas en cada versión**, PHP 8.3 y 8.4 con MariaDB 11.4 (326 ejecuciones, no 326 casos distintos), sintaxis correcta y logs sin avisos PHP.

![GitHub Actions: validación administrativa correcta en ambos entornos](capturas/ci-administracion-20261030.jpg)

[Configuración, límites y actualización del panel](ADMINISTRACION.md). Al desplegar esta mejora los administradores deben volver a iniciar sesión; no se admiten las sesiones antiguas. La administración sigue usando una única cuenta, sin MFA ni roles múltiples. La separación de cookies no sustituye las defensas contra XSS en el mismo origen.

## Cuenta de cliente e historial privado

Implementado:
- Acceso y cierre de sesión independientes, con formularios protegidos por CSRF.
- Historial paginado de 20 pedidos y detalle restringido al identificador del cliente autenticado. Las compras invitadas no se vinculan por coincidencia de correo.
- Datos de la cuenta disponibles en el checkout sin repetir la contraseña durante una sesión válida.
- Renovación de sesión al entrar y salir; caducidad tras 30 minutos de inactividad u ocho horas totales. Un cambio del hash de contraseña invalida la sesión.
- Páginas de cuenta con `Cache-Control: no-store` y contenido escapado.
- Límites compartidos entre acceso y checkout: cinco intentos por correo y 30 por IP en ventanas de 15 minutos. Los identificadores se guardan como HMAC con un secreto externo, sin correo ni IP en claro en esos contadores.
- Contraseñas nuevas de al menos 15 caracteres y un máximo de 72 bytes; las cuentas existentes conservan sus credenciales.
- Migración aditiva `002_customer_access.sql` y revisión automática de archivos/patrones sensibles antes de las pruebas.

Validación local: PHP 8.3.6 y MariaDB 10.11.14; **41 pruebas de compra + 45 de cuenta/privacidad + 29 de concurrencia = 115 comprobaciones**. Los 27 archivos PHP pasan la comprobación de sintaxis y el log del servidor no presenta avisos, errores ni deprecaciones en esa ejecución. Los datos utilizados son ficticios.

**GitHub Actions verificado:** [ejecución 41134720548](https://github.com/Rpfdeveloper/comercio-electronico/actions/runs/41134720548), sobre `816b101fe2f6d37ce9d1c3a49195fa1c858d1cc2`. PHP 8.3 y 8.4 con MariaDB 11.4: 115 comprobaciones correctas en cada entorno (230 ejecuciones, no 230 casos distintos), sintaxis correcta, revisión de patrones sensibles correcta y logs sin avisos PHP. Se incluye el rechazo de alias Unicode que la colación SQL podría confundir con otro correo. La lectura posterior de GitHub confirmó el contenido publicado y correo `noreply` en los nuevos commits.

Se ha revisado visualmente la pantalla de acceso en escritorio. La revisión completa de móvil, teclado y lectores de pantalla sigue pendiente.

![Pantalla de acceso de clientes, sin datos personales](capturas/acceso-cliente-20261030.jpg)

[Historial de cambios](https://github.com/Rpfdeveloper/comercio-electronico/commits/develop/) · [Resultados de CI](https://github.com/Rpfdeveloper/comercio-electronico/actions) · [Instalación de esta mejora](CUENTAS_CLIENTE.md) · [Hoja de ruta](HOJA_DE_RUTA.md)

## Siguiente trabajo

- Verificación del correo y recuperación de contraseña con tokens de un solo uso y caducidad.
- MFA y roles administrativos, con revisión de despliegue y acceso restringido.
- Búsqueda y filtros del catálogo; revisión completa del checkout en móvil y con teclado.
- Reservas y estados de pago antes de conectar una pasarela en modo de pruebas.

## Criterio para registrar avances

Cada entrega debe indicar comportamiento, comprobaciones realizadas y limitaciones. Publicar solo código, documentación técnica y datos ficticios; excluir conversaciones, correos personales, credenciales, sesiones, logs y exportaciones de clientes. Utilizar correo `noreply` en nuevos commits y comprobar los metadatos de autoría tras publicar. La revisión automática ayuda a detectar patrones comunes, pero no sustituye la revisión del diff ni analiza exhaustivamente archivos binarios o el historial anterior.
