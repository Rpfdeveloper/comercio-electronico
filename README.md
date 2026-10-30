# Comercio electrónico · Tienda de perfumes

Proyecto académico de Comercio Electrónico de la UOC, desarrollado en PHP y MySQL/MariaDB. Se está actualizando mediante mejoras incrementales y comprobadas para evolucionar hacia una tienda completa.

**Estado: prototipo en desarrollo.** Todavía no está preparado para operar con clientes ni cobrar pagos reales.

[Ver el código actualizado](https://github.com/Rpfdeveloper/comercio-electronico/tree/develop) · [Registro de progreso](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/PROGRESO.md) · [Hoja de ruta](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/HOJA_DE_RUTA.md)

## Último avance: protección del panel de administración

- Sesión administrativa independiente de la cuenta de cliente, con renovación de cookie y CSRF al entrar y salir.
- Caducidad tras 15 minutos de inactividad o cuatro horas totales; cambio de contraseña o secreto invalida la sesión.
- Límites por cuenta e IP, con contadores separados de clientes y sin guardar identificadores en claro.
- Páginas administrativas sin caché, errores genéricos de acceso y rechazo controlado de identificadores de pedido malformados.
- Se conservan la cuenta e historial privado de clientes, el carrito y las protecciones de compra y stock.

Validación: **163 comprobaciones por entorno**: 41 de compra, 45 de cuenta/privacidad, 48 de administración y 29 de concurrencia. [Consultar la ejecución de GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions/runs/41137901221), con PHP 8.3 y 8.4 y MariaDB 11.4. También se comprueban 31 archivos PHP, patrones sensibles y los logs del servidor. Las pruebas no equivalen a una auditoría de seguridad ni a una prueba de carga sostenida.

[Actualizar el acceso administrativo](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/ADMINISTRACION.md) · [Configurar las cuentas de clientes](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/CUENTAS_CLIENTE.md) · [Ejecutar las pruebas](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/tests/README.md)

## Funcionalidad y siguientes pasos

La tienda incluye catálogo, categorías, fichas, carrito, compra invitada o registrada y administración de pedidos. Las mejoras anteriores incorporaron CSRF, validación de cantidades y variantes, relectura de precios, descuento transaccional de stock y pruebas de compras simultáneas.

Pendientes principales: verificación de correo, recuperación de contraseña, MFA administrativo, búsqueda y filtros, reservas y pagos en modo de pruebas, envío real de correos y revisión de producción.

## Ramas y recuperación

- `develop`: código actualizado, pruebas y registro de progreso.
- `main`: presentación del proyecto.
- `original`: entrega académica recuperada, conservada como referencia.

La recuperación del 30 de octubre de 2026 verificó 43 archivos: 40 idénticos a la entrega y tres ajustados para retirar contraseñas incrustadas. Las 24 imágenes se conservaron sin compresión ni cambios. [Historial de recuperación](https://github.com/Rpfdeveloper/comercio-electronico/commits/original/).

## Configuración privada

Definir `TIENDA_DB_PASS`, `TIENDA_ADMIN_PASS` y `TIENDA_AUTH_SECRET` en el entorno del servidor. La aplicación no carga `.env` automáticamente. Mantener `TIENDA_DEBUG=false` fuera de las pruebas. No publicar credenciales, sesiones, logs ni copias de datos.

Para una instalación nueva, adaptar la contraseña de ejemplo del esquema SQL en una copia privada. Para actualizar una base existente, aplicar las migraciones indicadas en la guía de cuentas, sin volver a importar el esquema que recrea tablas. HTTPS y la configuración final del servidor deben revisarse antes de un despliegue público.
