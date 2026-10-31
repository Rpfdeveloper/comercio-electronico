# Protección del acceso administrativo

El panel continúa usando una única cuenta `admin`, con contraseña en `TIENDA_ADMIN_PASS`. Incluye resumen, consulta de pedidos e inventario y gestión del catálogo. No añade roles, MFA ni pagos.

## Gestión del catálogo — 31 de octubre de 2026

Después de entrar se abre **Resumen**. **Catálogo** permite crear perfumes inactivos y editar sus fichas, precios, ofertas y stock; también añadir formatos. **Inventario** enlaza cada perfume con su editor. Los cambios se guardan junto con su historial en una transacción; un formulario antiguo devuelve 409 y exige recargar los datos. [Funcionamiento, migración 004, validación y límites](GESTION_CATALOGO.md).

Las secciones siguientes documentan también entregas anteriores: sus cifras y requisitos de instalación corresponden a esas iteraciones. Para actualizar a la versión actual se requiere además la migración 004.

## Consulta de pedidos — 31 de octubre de 2026

El panel permite combinar búsqueda por nombre o correo con estado y fechas. Una búsqueda compuesta solo por dígitos se interpreta como número exacto de pedido. `%`, `_` y `!` se buscan literalmente. Las fechas inicial y final incluyen todo el día, según la misma zona de la conexión de base de datos utilizada para mostrar las fechas; no se convierten a la zona del navegador.

Se muestran 25 pedidos por página. Es posible ordenar por fecha o importe en ambos sentidos; el identificador desempata los valores iguales. Una página superior a las disponibles se ajusta a la última. Los enlaces de página y detalle conservan los filtros. Si se reciben filtros de retorno malformados en el detalle, se vuelve al listado general.

El resumen cuenta y suma **todos los resultados filtrados**, no solo la página visible. Los importes son valores registrados en pedidos, no ingresos verificados. El listado y el detalle consultan `estado_pedido.descripcion`: `pending` se muestra como pendiente, `paid` como pago registrado, `processing` como en preparación, `shipped` como enviado y `canceled` como cancelado. Los estados personalizados se muestran con su descripción escapada y se agrupan bajo «Otros estados». Se corrige la etiqueta anterior, que llamaba «Confirmado» al estado inicial `1`, aunque en el esquema corresponde a `pending`. No se modifican los estados almacenados.

El detalle conserva nombre de producto, variante, dirección e importes históricos y muestra las observaciones escapadas. El diseño adapta las tablas a tarjetas en pantallas pequeñas y conserva los encabezados. El acceso tiene etiquetas, autocompletado y foco visible.

Los valores se validan antes de consultar y las búsquedas usan parámetros preparados. Estado y orden se restringen a opciones admitidas. Los filtros incorrectos devuelven 422 sin mostrar pedidos; un fallo de consulta del listado devuelve 503 con un mensaje genérico. Se mantiene la autenticación previa, CSRF, caducidad de sesiones, noindex y no-store. La administración utiliza `Referrer-Policy: no-referrer` para no transmitir los filtros al navegar.

**Privacidad:** las búsquedas GET quedan en la URL y pueden aparecer en el historial local y en los logs de acceso del servidor. Evitar registrar la cadena de consulta del panel y aplicar la política de conservación correspondiente en el despliegue. No compartir enlaces que contengan nombres o correos. La cabecera Referer no sustituye estas medidas.

**Instalación:** desplegar conjuntamente PHP y `tienda/admin/assets/admin.css`; no se añade ninguna migración. Mantener las migraciones 001–003 y la configuración externa existentes. La contraseña de una vista previa se configura fuera del repositorio: el código no incorpora una clave predeterminada.

**Límites de pedidos:** consulta sin edición de estados; tampoco ejecuta pagos, envíos o correos. La paginación por desplazamiento y la búsqueda parcial no están probadas con volúmenes comerciales grandes. Nuevos pedidos durante la navegación pueden desplazar las páginas; no se ofrece una instantánea congelada. MFA, roles, auditoría completa de accesibilidad y dispositivos reales siguen pendientes.

**Validación:** `tests/admin_orders.php` añade 64 comprobaciones HTTP con 30 pedidos ficticios: privacidad, acceso, búsqueda, caracteres literales, inyección SQL/XSS, los cinco estados y estados personalizados, fechas inclusivas, filtros combinados, resumen, ordenación, paginación, retorno y entradas inválidas. El conjunto suma 502 comprobaciones; sintaxis correcta en 53 PHP y log sin avisos en PHP 8.3.6/MariaDB 10.11.14. Revisión visual en escritorio y marcos de 320/390/768 px con HTML generado por PHP y formularios de muestra desactivados.

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

El limitador toma un bloqueo exclusivo sobre cada contador y reintenta la transacción completa hasta tres veces ante un interbloqueo o agotamiento de espera de bloqueo. Cada reintento vuelve a comprobar los límites; si persiste el error, el acceso se rechaza con 503.

Al entrar correctamente se elimina el contador de la cuenta; se conserva el de IP. Al alcanzar el límite se devuelve 429 con `Retry-After: 900`. Las credenciales incorrectas reciben un error genérico 401; los formularios malformados, 422; un problema de configuración o disponibilidad impide entrar y devuelve 503.

La IP procede de `REMOTE_ADDR`; la resolución de proxies de confianza debe configurarse en el servidor. Se conserva la limpieza acotada de contadores de más de un día descrita en [la guía de cuentas](CUENTAS_CLIENTE.md). Los contadores no guardan usuario ni IP en claro. Los logs del servidor tienen su propia retención.

El límite de cuenta puede impedir temporalmente el acceso legítimo si otra persona agota los intentos. Antes de producción conviene complementar esta protección con acceso administrativo restringido, supervisión y MFA; no desactivar los límites para resolver un bloqueo.

## Validación

`tests/admin.php` prueba acceso restringido, errores genéricos, CSRF, renovación e invalidación de cookies, separación de sesiones, conservación del carrito, caducidad, cambio de credenciales/secreto, límites por cuenta/IP, independencia respecto de clientes, dos intentos que compiten por la última plaza y respuesta 404 ante identificadores malformados o inexistentes.

Las pruebas usan una base local desechable y datos ficticios. La caducidad y rotación de configuración se comprueban con el validador y marcas de tiempo controladas; no simulan el reinicio de un servidor de producción. [Resultados y progreso](PROGRESO.md).

## Inventario — 31 de octubre de 2026

Desde la navegación del panel, **Inventario** abre `admin/inventario.php`. Consulta 25 referencias por página, con búsqueda literal por perfume, marca o SKU, filtros combinables de existencias y activación, y orden estable por nombre o stock. Una referencia representa una variante, o el producto base únicamente cuando no tiene variantes. No se suman existencias del producto base si hay variantes, aunque todas estén inactivas.

Stock bajo significa entre 1 y 5 unidades; agotado, exactamente 0. Los valores nulos y negativos se señalan como datos por revisar y no se suman a las unidades. El resumen abarca todas las referencias filtradas, incluidas las inactivas cuando se muestran. Una referencia activa requiere producto, categoría y variante activos; esta etiqueta no garantiza disponibilidad. El precio respeta la oferta positiva y el precio propio del formato, como el carrito.

La pantalla de inventario es de consulta y enlaza con el editor del catálogo. Este editor requiere la migración 004; la consulta por sí sola no modifica stock, productos ni variantes. Reutiliza la sesión administrativa y las cabeceras privadas. Los filtros inválidos devuelven 422 y los errores de base de datos, un mensaje genérico 503. Se incorpora `tests/admin_inventory.php` a CI con 50 comprobaciones HTTP de acceso, variantes, activación, precios, datos anómalos, búsqueda, filtros, paginación, SQL/XSS y conservación del inventario. Se mantienen los límites de volumen y paginación descritos para pedidos.
