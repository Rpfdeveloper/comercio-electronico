# Acceso de clientes

La cuenta se crea al completar una compra como usuario registrado. Después se puede entrar desde **Mi cuenta**, consultar sus pedidos y volver a comprar con los datos guardados. El alta independiente, la verificación del correo y la recuperación de contraseña siguen pendientes.

## Actualizar una instalación existente

1. Hacer una copia privada de la base de datos y comprobar que puede restaurarse.
2. Aplicar `tienda/sql/migrations/002_customer_access.sql` a esa base. Solo añade la tabla de contadores `acceso_intento`; no borra pedidos ni clientes. Es idempotente. No volver a importar el esquema completo sobre datos que deban conservarse.
3. Definir `TIENDA_AUTH_SECRET` en el entorno del proceso PHP con al menos 32 bytes aleatorios. Por ejemplo, generar localmente con `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` y guardar el resultado en la configuración privada del servidor. No copiarlo al repositorio ni a logs. La aplicación no carga archivos `.env` automáticamente.
4. Desplegar el código y reiniciar PHP si el cambio de variables lo requiere. Mantener `TIENDA_DEBUG=false` y usar HTTPS al preparar un entorno público; configurar HTTPS en el servidor, sin confiar en cabeceras de proxy enviadas por el cliente.
5. Comprobar acceso, salida e historial con una cuenta ficticia en preproducción.

Una instalación nueva puede importar el esquema actualizado, que incluye la tabla. La preparación automática de pruebas aplica además todas las migraciones.

## Sesiones e intentos

- Los formularios de acceso y salida requieren CSRF. La salida usa POST y conserva el carrito, pero borra la autenticación y cualquier resumen de compra pendiente.
- La sesión se renueva al entrar y salir. Caduca tras 30 minutos sin actividad en las páginas que consultan la cuenta, con un máximo absoluto de ocho horas. Un cambio del hash de contraseña o la eliminación de la cuenta la invalida.
- Acceso y checkout comparten límites persistidos en base de datos, incluso si se eliminan las cookies. El límite es cinco intentos por correo y 30 por IP en una ventana de 15 minutos. Un acceso correcto borra el contador de correo; conserva el de IP. Redes compartidas pueden alcanzar el límite por IP.
- El origen se obtiene de `REMOTE_ADDR`. Antes de desplegar detrás de un proxy hay que configurar la resolución de la IP de confianza en el servidor; no habilitar confianza general en `X-Forwarded-For`.
- Los contadores guardan HMAC de correo/IP, fecha de ventana y número de intentos. Cada intento elimina hasta 100 filas de más de un día. Si se requiere un plazo máximo de retención, programar también la consulta `DELETE FROM acceso_intento WHERE ventana < UTC_TIMESTAMP() - INTERVAL 1 DAY` desde un mantenimiento privado: sin tráfico, la limpieza oportunista no se ejecuta. Los logs del servidor tienen su propia configuración de retención.
- Sin secreto válido o sin migración, el acceso devuelve un error genérico y no autoriza la cuenta. La compra invitada sigue disponible.

## Límites actuales

No se implementan todavía verificación del correo, recuperación de contraseña, MFA ni envío de correos. El historial muestra el estado guardado del pedido, sin seguimiento del transportista. El acceso no prueba la propiedad del buzón. No se enlazan pedidos invitados por correo, pues esa coincidencia no acredita propiedad. Las pruebas no sustituyen una auditoría de seguridad, accesibilidad ni una prueba de carga sostenida.

La administración dispone ahora de [sesión y límites propios](ADMINISTRACION.md), separados de los de clientes.
