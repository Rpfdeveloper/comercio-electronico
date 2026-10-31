# Comercio electrónico · Tienda de perfumes

Proyecto académico de Comercio Electrónico de la UOC, desarrollado en PHP y MySQL/MariaDB. Se está actualizando mediante mejoras incrementales y comprobadas para evolucionar hacia una tienda completa.

**Estado: prototipo en desarrollo.** Todavía no está preparado para operar con clientes ni cobrar pagos reales.

[Ver el código de la tienda](https://github.com/Rpfdeveloper/comercio-electronico/tree/main/tienda) · [Registro de progreso](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/PROGRESO.md) · [Hoja de ruta](https://github.com/Rpfdeveloper/comercio-electronico/blob/develop/docs/HOJA_DE_RUTA.md)

## Último avance: favoritos que acompañan a la cuenta

- Favoritos sin registro conservados, y colección vinculada a la cuenta al iniciar sesión.
- Recuperación desde otra sesión o dispositivo, con precios y disponibilidad actuales.
- Unión explícita de favoritos del navegador, sin duplicados ni importación automática.
- Listas de clientes aisladas y límite de 24 protegido incluso con cambios simultáneos.
- Acceso desde «Mi cuenta» y diseño adaptable que indica dónde se guarda la selección.
- SEO preparado y «Contacto próximamente» conservados; indexación pública desactivada.

Validación local: **438 comprobaciones**, 50 archivos PHP con sintaxis correcta y logs sin avisos. [Estado de GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions). CI para PHP 8.3, 8.4 y 8.5. La sincronización se prueba con sesiones independientes; faltan dispositivos físicos y auditoría completa de accesibilidad.

**Actualización necesaria:** aplicar la migración aditiva `003_customer_favorites.sql` antes de desplegar este código. [Uso, instalación, pruebas y límites](docs/FAVORITOS_CUENTA.md) · [Retención y SEO](docs/RETENCION_SEO.md).

La guía actual usa filtros del catálogo. El asesor con IA es una siguiente fase y todavía no está conectado a un modelo.

[Portada y guía](docs/DESCUBRIMIENTO.md) · [Carrito y checkout](docs/COMPRA.md) · [Catálogo y filtros](docs/CATALOGO.md) · [Administración](docs/ADMINISTRACION.md) · [Cuentas de clientes](docs/CUENTAS_CLIENTE.md) · [Pruebas](tests/README.md)

## Funcionalidad y siguientes pasos

La tienda incluye catálogo, categorías, fichas, favoritos en el navegador o vinculados a la cuenta, carrito, compra invitada o registrada y administración de pedidos. Las mejoras anteriores incorporaron CSRF, validación de cantidades y variantes, relectura de precios, descuento transaccional de stock y pruebas de compras simultáneas.

Pendientes principales: verificación de correo, recuperación de contraseña, MFA administrativo, carrito persistente, reservas y pagos en modo de pruebas, envío real de correos y revisión de producción.

La primera integración en `main` se realizó mediante el [PR #1](https://github.com/Rpfdeveloper/comercio-electronico/pull/1), después de verificar las copias de seguridad y corregir la observación de revisión.

## Ramas y recuperación

- `develop`: preparación y comprobación de las siguientes mejoras.
- `main`: tienda, pruebas y documentación de las entregas integradas mediante pull request.
- `original`: entrega académica recuperada, conservada como referencia.
- `backup/2026-10-31-*`: copias verificadas antes de cada integración. La mejora de checkout conserva el estado anterior en `backup/2026-10-31-antes-checkout-e19ae30`.

La recuperación del 30 de octubre de 2026 verificó 43 archivos: 40 idénticos a la entrega y tres ajustados para retirar contraseñas incrustadas. Las 24 imágenes se conservaron sin compresión ni cambios. [Historial de recuperación](https://github.com/Rpfdeveloper/comercio-electronico/commits/original/).

## Configuración privada

Definir `TIENDA_DB_PASS`, `TIENDA_ADMIN_PASS` y `TIENDA_AUTH_SECRET` en el entorno del servidor. La aplicación no carga `.env` automáticamente. Mantener `TIENDA_DEBUG=false` fuera de las pruebas. No publicar credenciales, sesiones, logs ni copias de datos.

Para una instalación nueva, adaptar la contraseña de ejemplo del esquema SQL en una copia privada. Para actualizar una base existente, aplicar las migraciones indicadas en la guía de cuentas, sin volver a importar el esquema que recrea tablas. HTTPS y la configuración final del servidor deben revisarse antes de un despliegue público.

SEO: `TIENDA_PUBLIC_URL` y `TIENDA_INDEXABLE` permanecen sin configurar hasta el lanzamiento. [Configuración y límites](docs/RETENCION_SEO.md).
