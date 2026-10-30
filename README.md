# Comercio electrónico · Tienda de perfumes

Proyecto académico de Comercio Electrónico de la UOC, desarrollado en PHP y MySQL/MariaDB.

## Objetivo y desarrollo

El objetivo es actualizar progresivamente esta práctica hasta convertirla en una **tienda de comercio electrónico real y completa**, con compras seguras, pagos, gestión de pedidos y stock, accesibilidad y un despliegue mantenible.

**[Ver la versión en desarrollo](https://github.com/Rpfdeveloper/comercio-electronico/tree/develop)** · **[Consultar la hoja de ruta](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/HOJA_DE_RUTA.md)**

El proyecto está en desarrollo: todavía no está preparado para operar con clientes ni cobrar pagos reales. Las mejoras se publican en lotes pequeños y comprobados.

Mejoras publicadas en `develop`: protección CSRF, comprobación de credenciales sin almacenar contraseñas en claro en la sesión, validación del carrito y de variantes, comprobación de precios y descuento transaccional de stock, además de la corrección de la imagen Ocean Graphite EDT.

**41 comprobaciones locales de integración correctas.** [Ver las pruebas y cómo ejecutarlas](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/tests/README.md). Las pruebas incluyen compras como invitado, altas y cuentas existentes, intentos de manipulación, cambios de precio y stock, confirmaciones repetidas y acceso administrativo. No equivalen a una auditoría de seguridad ni a pruebas de carga.

## Código de la tienda

**[Abrir la tienda en la rama `original`](https://github.com/Rpfdeveloper/comercio-electronico/tree/original/tienda)**

La rama `main` contiene la presentación del repositorio. La rama `original` conserva los 43 archivos de la tienda recuperados de la entrega académica; las mejoras funcionales se desarrollan en la rama `develop`.

La tienda incluye catálogo de perfumes, categorías, fichas de producto, carrito, proceso de compra y un panel de administración de pedidos.

## Recuperación verificada

El 30 de octubre de 2026 se publicaron los 43 archivos en 15 lotes pequeños. Se compararon sus hashes Git y tamaños con la copia preparada, lote a lote y al terminar.

- 40 archivos se conservaron idénticos, byte a byte, a los de la entrega.
- 3 archivos se ajustaron únicamente para retirar contraseñas incrustadas: `tienda/config/config.php`, `tienda/admin/login.php` y `tienda/sql/tienda_perfumes_schema.sql`.
- Las 24 imágenes se conservaron sin compresión ni cambios.

[Consultar los commits de recuperación](https://github.com/Rpfdeveloper/comercio-electronico/commits/original/)

## Configuración de las credenciales

- Define `TIENDA_DB_PASS` en el entorno del servidor PHP con la contraseña de la base de datos.
- Define `TIENDA_ADMIN_PASS` en ese mismo entorno para acceder al panel. Si está vacía o no está definida, el acceso administrativo se rechaza.
- Antes de importar `tienda/sql/tienda_perfumes_schema.sql`, sustituye `CAMBIAR_ANTES_DE_IMPORTAR` por una contraseña local que coincida con `TIENDA_DB_PASS`. No publiques esa copia con la contraseña.

Esta recuperación verifica la integridad de los archivos publicados. No constituye una prueba funcional de la aplicación ni de la importación de la base de datos.
