# Comercio electrónico · Tienda de perfumes

## Último avance: cuenta e historial privado

`develop` incorpora acceso de clientes, cierre de sesión, historial paginado y detalle de pedidos propios, límites de intentos y compra con los datos de la cuenta. Validación local: **114 comprobaciones correctas** (41 de compra, 44 de cuenta y privacidad, 29 de concurrencia), con PHP 8.3 y MariaDB 10.11; la matriz de CI comprueba PHP 8.3/8.4 y MariaDB 11.4.

[Registro de progreso](docs/PROGRESO.md) · [Actualizar la instalación y configurar el secreto de acceso](docs/CUENTAS_CLIENTE.md) · [Resultados en GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions)

Las instrucciones siguientes sobre recuperación y las cifras de iteraciones anteriores son antecedentes. Ver el registro de progreso para el estado actual y sus limitaciones.

Proyecto académico de Comercio Electrónico de la UOC, desarrollado en PHP y MySQL/MariaDB.

## Objetivo y desarrollo

El objetivo es actualizar progresivamente esta práctica hasta convertirla en una **tienda de comercio electrónico real y completa**, con compras seguras, pagos, gestión de pedidos y stock, accesibilidad y un despliegue mantenible.

**[Ver la versión en desarrollo](https://github.com/Rpfdeveloper/comercio-electronico/tree/develop)** · **[Consultar la hoja de ruta](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/HOJA_DE_RUTA.md)**

El proyecto está en desarrollo: todavía no está preparado para operar con clientes ni cobrar pagos reales. Las mejoras se publican en lotes pequeños y comprobados.

Mejoras publicadas en `develop`: protección CSRF, comprobación de credenciales sin almacenar contraseñas en claro en la sesión, validación del carrito y de variantes, comprobación de precios y descuento transaccional de stock, además de la corrección de la imagen Ocean Graphite EDT.

**70 comprobaciones correctas en cada versión de PHP (8.3 y 8.4), con MariaDB 11.4 en GitHub Actions:** 41 de integración y 29 de concurrencia. [Ver ejecución verificada](https://github.com/Rpfdeveloper/comercio-electronico/actions/runs/41126154248) · [Ejecutar las pruebas](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/tests/README.md).

Las pruebas incluyen compras, cuentas nuevas y existentes, formularios, precios, stock y administración. Las nuevas comprobaciones fuerzan compras simultáneas de la última unidad, carritos con productos en orden inverso y doble confirmación de una misma sesión: verifican pedidos completos y el descuento exacto de stock. Se han comprobado además 21 archivos PHP sin errores de sintaxis y los logs sin avisos, errores ni deprecaciones. No equivalen a una auditoría de seguridad ni a pruebas de carga.

Las comprobaciones se ejecutan automáticamente al modificar el código o las pruebas. La confirmación devuelve un conflicto HTTP 409 cuando cambia el stock o el carrito y ya no promete un correo que la tienda todavía no envía.

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
