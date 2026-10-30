# Progreso de la tienda

Registro técnico de cambios verificables en `develop`. La rama `original` conserva la entrega recuperada. El proyecto sigue siendo un prototipo: no procesa pagos ni envía correos reales.

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
- Protección equivalente del acceso administrativo.
- Búsqueda y filtros del catálogo; revisión completa del checkout en móvil y con teclado.
- Reservas y estados de pago antes de conectar una pasarela en modo de pruebas.

## Criterio para registrar avances

Cada entrega debe indicar comportamiento, comprobaciones realizadas y limitaciones. Publicar solo código, documentación técnica y datos ficticios; excluir conversaciones, correos personales, credenciales, sesiones, logs y exportaciones de clientes. Utilizar correo `noreply` en nuevos commits y comprobar los metadatos de autoría tras publicar. La revisión automática ayuda a detectar patrones comunes, pero no sustituye la revisión del diff ni analiza exhaustivamente archivos binarios o el historial anterior.
