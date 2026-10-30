# Comercio electrónico · Tienda de perfumes

Proyecto académico de Comercio Electrónico de la UOC, desarrollado en PHP y MySQL/MariaDB. Se está actualizando mediante mejoras incrementales y comprobadas para evolucionar hacia una tienda completa.

**Estado: prototipo en desarrollo.** Todavía no está preparado para operar con clientes ni cobrar pagos reales.

[Ver el código de la tienda](https://github.com/Rpfdeveloper/comercio-electronico/tree/main/tienda) · [Registro de progreso](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/PROGRESO.md) · [Hoja de ruta](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/HOJA_DE_RUTA.md)

## Último avance: catálogo y validación de categorías

- Búsqueda por nombre, marca o descripción; filtros por categoría, marca, precio y disponibilidad.
- Ordenación por nombre, precio o incorporación reciente; doce perfumes por página conservando filtros.
- Precios «desde» basados en capacidades disponibles, ofertas coherentes con el carrito y etiquetas de agotado.
- Fichas que ocultan productos inactivos y evitan seleccionar capacidades agotadas.
- Formularios sin dependencia de JavaScript, controles etiquetados y estilos adaptables.
- Las categorías retiradas bloquean altas al carrito y confirmaciones, incluso durante compras simultáneas.

Validación: **239 comprobaciones por entorno**: 41 de compra, 45 de cuenta/privacidad, 48 de administración, 73 de catálogo y 32 de concurrencia. [Consultar la ejecución de GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions/runs/41145205094), con PHP 8.3 y 8.4 y MariaDB 11.4. Los 35 archivos PHP pasan sintaxis y los logs no presentan avisos PHP. Las pruebas se ejecutan también en ramas de mejora antes de integrar.

[Uso y límites del catálogo](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/CATALOGO.md) · [Acceso administrativo](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/ADMINISTRACION.md) · [Cuentas de clientes](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/CUENTAS_CLIENTE.md) · [Ejecutar las pruebas](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/tests/README.md)

## Funcionalidad y siguientes pasos

La tienda incluye catálogo, categorías, fichas, carrito, compra invitada o registrada y administración de pedidos. Las mejoras anteriores incorporaron CSRF, validación de cantidades y variantes, relectura de precios, descuento transaccional de stock y pruebas de compras simultáneas.

Pendientes principales: verificación de correo, recuperación de contraseña, MFA administrativo, carrito persistente, reservas y pagos en modo de pruebas, envío real de correos y revisión de producción.

La primera integración en `main` se realizó mediante el [PR #1](https://github.com/Rpfdeveloper/comercio-electronico/pull/1), después de verificar las copias de seguridad y corregir la observación de revisión.

## Ramas y recuperación

- `develop`: preparación y comprobación de las siguientes mejoras.
- `main`: tienda, pruebas y documentación de las entregas integradas mediante pull request.
- `original`: entrega académica recuperada, conservada como referencia.
- `backup/2026-10-31-*`: copias verificadas de `main` y `develop` previas a la primera integración.

La recuperación del 30 de octubre de 2026 verificó 43 archivos: 40 idénticos a la entrega y tres ajustados para retirar contraseñas incrustadas. Las 24 imágenes se conservaron sin compresión ni cambios. [Historial de recuperación](https://github.com/Rpfdeveloper/comercio-electronico/commits/original/).

## Configuración privada

Definir `TIENDA_DB_PASS`, `TIENDA_ADMIN_PASS` y `TIENDA_AUTH_SECRET` en el entorno del servidor. La aplicación no carga `.env` automáticamente. Mantener `TIENDA_DEBUG=false` fuera de las pruebas. No publicar credenciales, sesiones, logs ni copias de datos.

Para una instalación nueva, adaptar la contraseña de ejemplo del esquema SQL en una copia privada. Para actualizar una base existente, aplicar las migraciones indicadas en la guía de cuentas, sin volver a importar el esquema que recrea tablas. HTTPS y la configuración final del servidor deben revisarse antes de un despliegue público.
