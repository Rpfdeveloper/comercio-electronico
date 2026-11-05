# Comercio electrónico · Tienda de perfumes

Proyecto académico de Comercio Electrónico de la UOC, desarrollado en PHP y MySQL/MariaDB. Se está actualizando mediante mejoras incrementales y comprobadas para evolucionar hacia una tienda completa.

**Estado: prototipo en desarrollo.** Todavía no está preparado para operar con clientes ni cobrar pagos reales.

[Ver el código de la tienda](https://github.com/Rpfdeveloper/comercio-electronico/tree/main/tienda) · [Registro de progreso](docs/PROGRESO.md) · [Hoja de ruta](docs/HOJA_DE_RUTA.md)

## Último avance: datos de cuenta y cambio de contraseña

- **Mi cuenta → Mis datos y seguridad** permite editar el nombre y la dirección habitual.
- La dirección guardada se precarga al empezar la compra. Elegir otro destino para un pedido conserva el perfil y los pedidos anteriores.
- Detección de formularios antiguos para evitar sobrescribir cambios de otro dispositivo.
- Cambio de contraseña con comprobación de la actual, límites de intentos, renovación de sesión e invalidación de otras sesiones y enlaces de recuperación anteriores.
- Conserva carrito y favoritos; los cambios invalidan los resúmenes pendientes de la sesión actual.

Validación local: **995 comprobaciones en 24 suites**, sintaxis de 91 archivos PHP y revisión de patrones sensibles. PHP 8.3.6 y MariaDB 10.11.14; CI configurada para PHP 8.3, 8.4 y 8.5. Revisión visual de perfil y errores en escritorio y marcos de 320, 390 y 768 px.

[Uso y límites del perfil](docs/PERFIL_CLIENTE.md). Las correcciones anteriores del carrito y la compra se conservan: [revisión del 5 de noviembre](docs/REVISION_2026-11-05.md).

Esta entrega no añade migraciones. La versión actual utiliza las migraciones **001–009** de `tienda/sql/migrations`; aplicar solo las pendientes y revisar cada archivo antes de actualizar. La **009 añade perfumes ficticios de ejemplo**. No reimportar el esquema completo en una base existente. [Gestión del catálogo](docs/GESTION_CATALOGO.md) · [Carrito de cuenta](docs/CARRITO_CUENTA.md) · [Impuestos y envío](docs/POLITICA_COMERCIAL.md).

La guía actual usa filtros del catálogo. El asesor con IA es una siguiente fase y todavía no está conectado a un modelo.

[Portada y guía](docs/DESCUBRIMIENTO.md) · [Carrito y checkout](docs/COMPRA.md) · [Catálogo y filtros](docs/CATALOGO.md) · [Administración](docs/ADMINISTRACION.md) · [Cuentas de clientes](docs/CUENTAS_CLIENTE.md) · [Pruebas](tests/README.md)

## Funcionalidad y siguientes pasos

La tienda incluye catálogo, colecciones y marcas, fichas y marcado de producto, favoritos y carrito de cuenta, compra invitada o registrada, edición de datos de cuenta y contraseña, consulta de pedidos, gestión de estados con historial, catálogo, stock, imágenes y ajustes de venta. También incorpora recuperación de contraseña y registro administrativo de correos en modo de prueba. Las compras validan CSRF, cantidades, formatos, precios y disponibilidad y descuentan el stock de forma transaccional.

Pendientes principales: verificación y envío real de correo, MFA administrativo, reservas y pagos en modo de pruebas, canal de contacto y revisión de producción. PayPal, Bizum y tarjeta todavía no están integrados.

La primera integración en `main` se realizó mediante el [PR #1](https://github.com/Rpfdeveloper/comercio-electronico/pull/1), después de verificar las copias de seguridad y corregir la observación de revisión.

## Ramas y recuperación

- `develop`: preparación y comprobación de las siguientes mejoras.
- `main`: tienda, pruebas y documentación de las entregas integradas mediante pull request.
- `original`: entrega académica recuperada, conservada como referencia.
- `backup/2026-10-31-*`: copias verificadas antes de cada integración. La mejora de checkout conserva el estado anterior en `backup/2026-10-31-antes-checkout-e19ae30`.
- `backup/2026-11-05-antes-revision-c29e6fb`: código anterior a la revisión de compra.
- `backup/2026-11-05-antes-perfil-2ffccc2`: código anterior a la edición del perfil. Estas ramas no contienen copias de bases de datos de instalaciones.

La recuperación del 30 de octubre de 2026 verificó 43 archivos: 40 idénticos a la entrega y tres ajustados para retirar contraseñas incrustadas. Las 24 imágenes se conservaron sin compresión ni cambios. [Historial de recuperación](https://github.com/Rpfdeveloper/comercio-electronico/commits/original/).

## Configuración privada

Definir `TIENDA_DB_PASS`, `TIENDA_ADMIN_PASS` y `TIENDA_AUTH_SECRET` en el entorno del servidor. La aplicación no carga `.env` automáticamente. Mantener `TIENDA_DEBUG=false` fuera de las pruebas. No publicar credenciales, sesiones, logs ni copias de datos.

Para una instalación nueva, adaptar la contraseña de ejemplo del esquema SQL en una copia privada. Para actualizar una base existente, aplicar las migraciones indicadas en la guía de cuentas, sin volver a importar el esquema que recrea tablas. HTTPS y la configuración final del servidor deben revisarse antes de un despliegue público.

SEO: `TIENDA_PUBLIC_URL` y `TIENDA_INDEXABLE` permanecen sin configurar hasta el lanzamiento. [Configuración y límites](docs/RETENCION_SEO.md).
