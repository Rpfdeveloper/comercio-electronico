# Datos de cuenta y cambio de contraseña

Desde **Mi cuenta → Mis datos y seguridad** se puede editar el nombre y una dirección habitual, o cambiar la contraseña. El correo de acceso permanece sin edición. No se añaden migraciones: requiere la instalación actual con las migraciones 001–009 y `TIENDA_AUTH_SECRET` configurado.

## Dirección habitual

El nombre, dirección, código postal, población y país son obligatorios; provincia es opcional. Se aplican los mismos límites de longitud y validaciones de texto que en la compra, con mensajes asociados a los campos. Los formularios funcionan sin JavaScript.

La dirección habitual aparece al empezar una compra con la cuenta. La dirección elegida en el checkout se guarda en el pedido y **ya no sustituye el perfil de una cuenta existente**. Al crear una cuenta durante su primera compra, esos datos sí establecen su dirección inicial. Los pedidos anteriores conservan el destinatario y dirección originales.

Guardar el perfil invalida el resumen de compra de esa sesión para revisar los datos de nuevo. Un resumen ya preparado en otro dispositivo conserva la dirección expresamente seleccionada para ese pedido; al confirmarlo no sobrescribe la dirección habitual.

El formulario incluye una versión firmada de los datos. Tras obtener un bloqueo de la cuenta se compara con los datos actuales: si otra pestaña o dispositivo ya los modificó, se devuelve 409 sin sobrescribirlos y se ofrece recargar. Un fallo al actualizar cualquiera de las dos tablas revierte toda la modificación. La cuenta editada siempre se obtiene de la sesión.

## Contraseña

Se exige la contraseña actual, una nueva distinta de al menos 15 caracteres y hasta 72 bytes, y su confirmación. El límite considera bytes para evitar truncamientos de bcrypt. No se recortan espacios de las contraseñas ni se devuelven en los formularios de error.

El cambio renueva la sesión y el token CSRF del dispositivo actual, invalida sus resúmenes y conserva carrito y favoritos. Las demás sesiones dejan de autorizar en su siguiente validación porque su prueba depende del hash anterior. Los enlaces de recuperación anteriores se eliminan en la misma transacción que actualiza el hash: si falla el borrado, tampoco cambia la contraseña.

Se permiten cinco intentos de comprobación por cuenta y 30 por IP en 15 minutos, con contadores HMAC separados del acceso normal. Volver a iniciar sesión no reinicia este límite. El correo se mantiene como está; no se envía una notificación por este cambio.

La emisión de enlaces, su uso y el cambio desde el perfil bloquean primero la cuenta y después los enlaces. Tras esperar un bloqueo se revalida la contraseña de sesión o el token; dos peticiones concurrentes no pueden sobrescribirse usando la autorización anterior.

## Actualizar

Con una instalación ya actualizada a las migraciones 001–009, basta con sustituir los archivos de código, conservar la configuración privada y las imágenes subidas, y recargar el navegador. No reimportar la base de datos. Copia de código anterior: `backup/2026-11-05-antes-perfil-2ffccc2`.

## Validación y límites

**995 comprobaciones locales correctas en 24 suites**, incluidas 61 de perfil y 12 de concurrencia de perfil. Sintaxis de 91 archivos PHP y log sin avisos inesperados; PHP 8.3.6 y MariaDB 10.11.14.

`tests/profile.php` comprueba mediante HTTP autorización, CSRF, entrada malformada, Unicode, privacidad, versiones antiguas, reversión SQL, perfil y dirección de pedido, conservación del historial, invalidación de sesiones y enlaces, carrito/favoritos y límites de contraseña.

`tests/profile_concurrency.php` solapa peticiones mediante una barrera de bloqueo observable en MariaDB: edición contra edición, cambio de contraseña contra cambio y cambio desde perfil contra recuperación en dos órdenes de lanzamiento. Comprueba los resultados HTTP y la contraseña final; no presupone qué proceso concede primero el motor.

La revisión visual utiliza datos ficticios y marcos de escritorio y móvil. No equivale a una auditoría de accesibilidad ni a pruebas en dispositivos físicos. No añade varias direcciones, cambio de correo, MFA, correo real ni pagos.
