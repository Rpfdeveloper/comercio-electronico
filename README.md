# Comercio electrónico · Tienda de perfumes

Proyecto académico de Comercio Electrónico de la UOC, desarrollado en PHP y MySQL/MariaDB. Se está actualizando mediante mejoras incrementales y comprobadas para evolucionar hacia una tienda completa.

**Estado: prototipo en desarrollo.** Todavía no está preparado para operar con clientes ni cobrar pagos reales.

[Ver el código de la tienda](https://github.com/Rpfdeveloper/comercio-electronico/tree/main/tienda) · [Registro de progreso](docs/PROGRESO.md) · [Hoja de ruta](docs/HOJA_DE_RUTA.md)

## Último avance: revisión del carrito, la compra y las fichas

- Carrito de cuenta protegido frente a cambios simultáneos y formularios antiguos de otro dispositivo.
- Pedido, stock y vaciado del carrito guardados en una misma transacción; dos dispositivos no pueden confirmar el mismo carrito dos veces.
- Compra detenida si no pueden comprobarse el carrito de la cuenta o los impuestos y el envío.
- Imágenes completas en la ficha, cantidad máxima según el formato y precios del catálogo identificados como importes sin IVA.
- Recuperación de contraseña con aviso explícito del modo de prueba, sin prometer correos que no se envían.

Validación local: **922 comprobaciones en 22 suites**, sintaxis de **87 archivos PHP**, JavaScript y revisión de patrones sensibles. PHP 8.3.6 y MariaDB 10.11.14. CI configurada para PHP 8.3, 8.4 y 8.5: [resultados de GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions). Revisión visual de la ficha con datos ficticios en escritorio y marcos de 320, 390 y 768 px; no sustituye una auditoría de accesibilidad ni dispositivos físicos. [Hallazgos y validación](docs/REVISION_2026-11-05.md).

Esta revisión no añade migraciones. La versión actual utiliza las migraciones **001–009** de `tienda/sql/migrations`; aplicar solo las pendientes y revisar cada archivo antes de actualizar. La **009 añade perfumes ficticios de ejemplo**. No reimportar el esquema completo en una base existente. [Gestión del catálogo](docs/GESTION_CATALOGO.md) · [Carrito de cuenta](docs/CARRITO_CUENTA.md) · [Impuestos y envío](docs/POLITICA_COMERCIAL.md).

La guía actual usa filtros del catálogo. El asesor con IA es una siguiente fase y todavía no está conectado a un modelo.

[Portada y guía](docs/DESCUBRIMIENTO.md) · [Carrito y checkout](docs/COMPRA.md) · [Catálogo y filtros](docs/CATALOGO.md) · [Administración](docs/ADMINISTRACION.md) · [Cuentas de clientes](docs/CUENTAS_CLIENTE.md) · [Pruebas](tests/README.md)

## Funcionalidad y siguientes pasos

La tienda incluye catálogo, colecciones y marcas, fichas y marcado de producto, favoritos y carrito de cuenta, compra invitada o registrada, consulta de pedidos, gestión de estados con historial, catálogo, stock, imágenes y ajustes de venta. También incorpora recuperación de contraseña y registro administrativo de correos en modo de prueba. Las compras validan CSRF, cantidades, formatos, precios y disponibilidad y descuentan el stock de forma transaccional.

Pendientes principales: verificación y envío real de correo, MFA administrativo, reservas y pagos en modo de pruebas, canal de contacto y revisión de producción. PayPal, Bizum y tarjeta todavía no están integrados.

La primera integración en `main` se realizó mediante el [PR #1](https://github.com/Rpfdeveloper/comercio-electronico/pull/1), después de verificar las copias de seguridad y corregir la observación de revisión.

## Ramas y recuperación

- `develop`: preparación y comprobación de las siguientes mejoras.
- `main`: tienda, pruebas y documentación de las entregas integradas mediante pull request.
- `original`: entrega académica recuperada, conservada como referencia.
- `backup/2026-10-31-*`: copias verificadas antes de cada integración. La mejora de checkout conserva el estado anterior en `backup/2026-10-31-antes-checkout-e19ae30`.
- `backup/2026-11-05-antes-revision-c29e6fb`: código anterior a esta revisión. No contiene copias de bases de datos de instalaciones.

La recuperación del 30 de octubre de 2026 verificó 43 archivos: 40 idénticos a la entrega y tres ajustados para retirar contraseñas incrustadas. Las 24 imágenes se conservaron sin compresión ni cambios. [Historial de recuperación](https://github.com/Rpfdeveloper/comercio-electronico/commits/original/).

## Configuración privada

Definir `TIENDA_DB_PASS`, `TIENDA_ADMIN_PASS` y `TIENDA_AUTH_SECRET` en el entorno del servidor. La aplicación no carga `.env` automáticamente. Mantener `TIENDA_DEBUG=false` fuera de las pruebas. No publicar credenciales, sesiones, logs ni copias de datos.

Para una instalación nueva, adaptar la contraseña de ejemplo del esquema SQL en una copia privada. Para actualizar una base existente, aplicar las migraciones indicadas en la guía de cuentas, sin volver a importar el esquema que recrea tablas. HTTPS y la configuración final del servidor deben revisarse antes de un despliegue público.

SEO: `TIENDA_PUBLIC_URL` y `TIENDA_INDEXABLE` permanecen sin configurar hasta el lanzamiento. [Configuración y límites](docs/RETENCION_SEO.md).
