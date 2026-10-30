# Protección del acceso administrativo

El panel continúa usando una única cuenta `admin`, con contraseña en `TIENDA_ADMIN_PASS`. Esta mejora protege el acceso existente; no añade roles, MFA, gestión de productos ni pagos.

## Actualización

- Configurar `TIENDA_ADMIN_PASS` y `TIENDA_AUTH_SECRET` en el entorno del servidor PHP. El secreto debe contener al menos 32 bytes aleatorios y mantenerse fuera del repositorio. Usar una contraseña administrativa larga y exclusiva. No hay contraseña de acceso predeterminada.
- Aplicar la migración `002_customer_access.sql` si todavía no existe la tabla `acceso_intento`. Esta iteración reutiliza esa tabla y no necesita otra migración. No reimportar el esquema completo sobre pedidos que deban conservarse.
- Desplegar los archivos PHP conjuntamente. Se extrae el limitador común a `tienda/src/lib/auth.php`; también lo utilizan los clientes.
- Volver a iniciar sesión en el panel: las sesiones administrativas anteriores dejan de autorizar el acceso.
- Mantener HTTPS y depuración desactivada en los entornos públicos. La contraseña y el secreto no deben aparecer en comandos registrados, capturas, informes ni logs.

## Sesiones separadas

La administración usa la cookie `TIENDAADMIN`, con una ruta limitada al directorio del panel, incluido el prefijo de instalación. La tienda pública conserva su propia sesión. Iniciar o cerrar sesión en uno de los ámbitos no inicia ni cierra la del otro; salir del panel conserva el carrito.

La cookie es `HttpOnly`, `SameSite=Lax` y `Secure` cuando PHP recibe HTTPS correctamente. El identificador de sesión y el token CSRF se renuevan al entrar y salir. Las páginas del panel envían `Cache-Control: no-store`.

La sesión administrativa caduca después de 15 minutos de inactividad o cuatro horas en total. El validador comprueba una huella HMAC de la configuración de acceso; cambiar la contraseña o el secreto invalida las sesiones al consultar de nuevo el panel. PHP debe recibir la nueva configuración, con reinicio si procede. Una configuración incompleta no autoriza sesiones previas.

La separación de cookies no sustituye la protección contra XSS: ambas áreas siguen en el mismo origen web. El contenido de los pedidos debe seguir escapándose al mostrarlo.

## Límites de intentos

Se permiten cinco intentos para la cuenta administrativa y 30 por IP en ventanas de 15 minutos. Cambiar el usuario enviado o eliminar cookies no reinicia el contador. Los prefijos HMAC separan estos contadores de los de clientes: un bloqueo en un ámbito no bloquea el otro.

Al entrar correctamente se elimina el contador de la cuenta; se conserva el de IP. Al alcanzar el límite se devuelve 429 con `Retry-After: 900`. Las credenciales incorrectas reciben un error genérico 401; los formularios malformados, 422; un problema de configuración o disponibilidad impide entrar y devuelve 503.

La IP procede de `REMOTE_ADDR`; la resolución de proxies de confianza debe configurarse en el servidor. Se conserva la limpieza acotada de contadores de más de un día descrita en [la guía de cuentas](CUENTAS_CLIENTE.md). Los contadores no guardan usuario ni IP en claro. Los logs del servidor tienen su propia retención.

El límite de cuenta puede impedir temporalmente el acceso legítimo si otra persona agota los intentos. Antes de producción conviene complementar esta protección con acceso administrativo restringido, supervisión y MFA; no desactivar los límites para resolver un bloqueo.

## Validación

`tests/admin.php` prueba acceso restringido, errores genéricos, CSRF, renovación e invalidación de cookies, separación de sesiones, conservación del carrito, caducidad, cambio de credenciales/secreto, límites por cuenta/IP, independencia respecto de clientes, dos intentos que compiten por la última plaza y respuesta 404 ante identificadores malformados o inexistentes.

Las pruebas usan una base local desechable y datos ficticios. La caducidad y rotación de configuración se comprueban con el validador y marcas de tiempo controladas; no simulan el reinicio de un servidor de producción. [Resultados y progreso](PROGRESO.md).
