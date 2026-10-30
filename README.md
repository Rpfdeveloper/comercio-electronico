# Comercio electrónico · Tienda de perfumes

Proyecto académico de Comercio Electrónico de la UOC, desarrollado en PHP y MySQL/MariaDB. Se está actualizando mediante mejoras incrementales y comprobadas para evolucionar hacia una tienda completa.

**Estado: prototipo en desarrollo.** Todavía no está preparado para operar con clientes ni cobrar pagos reales.

[Ver el código actualizado](https://github.com/Rpfdeveloper/comercio-electronico/tree/develop) · [Registro de progreso](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/PROGRESO.md) · [Hoja de ruta](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/HOJA_DE_RUTA.md)

## Último avance: cuenta e historial privado

- Acceso de clientes, cierre de sesión y consulta paginada de sus pedidos.
- Detalle autorizado por cliente: cambiar el identificador no permite ver pedidos ajenos. Las compras invitadas se mantienen separadas, aunque coincida el correo.
- Compra con los datos de la cuenta, renovación y caducidad de sesiones y límites compartidos de intentos entre acceso y checkout.
- Contraseñas nuevas de al menos 15 caracteres, migración aditiva y secreto de acceso definido fuera del repositorio.
- Comprobación automática de archivos y patrones sensibles; pruebas con datos ficticios.

Validación: **115 comprobaciones por entorno**: 41 de compra, 45 de cuenta/privacidad y 29 de concurrencia. [Consultar la ejecución de GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions/runs/41134720548), con PHP 8.3 y 8.4 y MariaDB 11.4. También se comprueban 27 archivos PHP, patrones sensibles y los logs del servidor. Las pruebas no equivalen a una auditoría de seguridad ni a una prueba de carga sostenida.

[Ver la nueva pantalla de acceso](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/capturas/acceso-cliente-20261030.jpg) · [Instalar la mejora de cuentas](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/CUENTAS_CLIENTE.md) · [Ejecutar las pruebas](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/tests/README.md)

## Funcionalidad y siguientes pasos

La tienda incluye catálogo, categorías, fichas, carrito, compra invitada o registrada y administración de pedidos. Las mejoras anteriores incorporaron CSRF, validación de cantidades y variantes, relectura de precios, descuento transaccional de stock y pruebas de compras simultáneas.

Pendientes principales: verificación de correo, recuperación de contraseña, límites del acceso administrativo, búsqueda y filtros, reservas y pagos en modo de pruebas, envío real de correos y revisión de producción.

## Ramas y recuperación

- `develop`: código actualizado, pruebas y registro de progreso.
- `main`: presentación del proyecto.
- `original`: entrega académica recuperada, conservada como referencia.

La recuperación del 30 de octubre de 2026 verificó 43 archivos: 40 idénticos a la entrega y tres ajustados para retirar contraseñas incrustadas. Las 24 imágenes se conservaron sin compresión ni cambios. [Historial de recuperación](https://github.com/Rpfdeveloper/comercio-electronico/commits/original/).

## Configuración privada

Definir `TIENDA_DB_PASS`, `TIENDA_ADMIN_PASS` y `TIENDA_AUTH_SECRET` en el entorno del servidor. La aplicación no carga `.env` automáticamente. Mantener `TIENDA_DEBUG=false` fuera de las pruebas. No publicar credenciales, sesiones, logs ni copias de datos.

Para una instalación nueva, adaptar la contraseña de ejemplo del esquema SQL en una copia privada. Para actualizar una base existente, aplicar las migraciones indicadas en la guía de cuentas, sin volver a importar el esquema que recrea tablas. HTTPS y la configuración final del servidor deben revisarse antes de un despliegue público.
