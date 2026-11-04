# Correos y recuperación de contraseña

## Bandeja de correo (modo registro)

La tienda guarda en `correo_saliente` los mensajes que envía a los clientes y los muestra en **Administración → Correos**. **Todavía no se envían**: falta elegir y conectar un proveedor (SMTP o API). Cuando se conecte, el registro servirá también de historial de envíos.

- **Confirmación de pedido**: se registra al confirmar la compra, una sola vez por pedido (referencia única). Resume líneas, envío, IVA y total, y aclara que no hay cobro. Un fallo al registrar el correo nunca anula el pedido.
- **Recuperación de contraseña**: contiene el enlace para elegir una contraseña nueva.
- Asuntos sin saltos de línea y destinatarios validados, para que el futuro envío no permita inyectar cabeceras.
- El panel lista los 50 más recientes, filtra por tipo y muestra cada mensaje escapado.

## Recuperar la contraseña

1. En **Accede a tu cuenta → ¿Has olvidado tu contraseña?**, el cliente escribe su correo.
2. La respuesta es idéntica exista o no la cuenta, para no revelar qué correos están registrados.
3. Si la cuenta existe, se genera un enlace `restablecer.php?token=…` de 256 bits, válido **60 minutos** y de **un solo uso**. Solo se guarda su hash SHA-256. Un enlace nuevo invalida los anteriores.
4. La nueva contraseña exige al menos 15 caracteres (máximo 72 bytes) y repetirla. Al guardarla, las sesiones abiertas con la contraseña anterior dejan de valer.

Límites: 3 solicitudes por correo y 20 por IP cada 15 minutos (429 con `Retry-After`). La página del enlace usa `Referrer-Policy: no-referrer` y no se guarda en caché. La dirección del enlace sale de `TIENDA_PUBLIC_URL`, nunca de la cabecera Host; sin esa variable se registra una ruta relativa a la carpeta pública.

**En modo registro, quien acceda al panel puede ver los enlaces de recuperación.** Es aceptable en el prototipo para poder probar el flujo, pero no en producción: antes de operar hay que conectar el envío real y dejar de mostrar esos enlaces.

## Actualización

Aplicar `tienda/sql/migrations/008_mail_and_password_reset.sql` (aditiva e idempotente).

## Validación

`tests/password_reset.php`: 42 comprobaciones (bandeja idempotente y sin cabeceras inyectadas, respuestas idénticas, hash y caducidad del enlace, invalidación del anterior, enlaces caducados, reutilizados o manipulados, validación de la contraseña, CSRF, cierre de sesiones, límite de solicitudes, confirmación de pedido y panel). **899 comprobaciones por entorno** en PHP 8.3, 8.4 y 8.5.

## Pendiente

Envío real con un proveedor, verificación del correo al crear la cuenta y avisos de cambio de estado del pedido.
