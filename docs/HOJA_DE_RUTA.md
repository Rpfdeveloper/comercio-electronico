# Evolución hacia una tienda completa

El objetivo es evolucionar esta práctica académica, mediante mejoras incrementales y verificadas, hasta convertirla en una tienda de comercio electrónico real y completa.

Estado actual: prototipo funcional en desarrollo. Todavía no está preparado para operar con clientes ni cobrar pagos reales.

## Organización
- `original`: entrega recuperada, conservada como referencia.
- `develop`: desarrollo de la tienda y correcciones.
- `main`: presentación del proyecto.
- Publicar cambios pequeños, comprobar cada publicación y ejecutar pruebas acordes al cambio.
- Probar con datos ficticios y servicios de pago en modo de pruebas.

## 1. Seguridad y consistencia (prioridad actual)
- [x] Bloquear compras con cuentas existentes sin comprobar su contraseña.
- [x] Conservar el hash de contraseña de cuentas existentes.
- [x] Corregir la imagen Ocean Graphite y añadir migración para instalaciones existentes.
- [ ] Protección CSRF en todas las operaciones que modifican datos.
- [ ] Inicio y cierre de sesión de clientes; verificación de correo y recuperación de contraseña.
- [ ] Retirar contraseñas en claro de la sesión de checkout.
- [ ] Endurecer sesiones, limitar intentos de acceso y desactivar depuración en producción.
- [ ] Validar variantes, productos activos, cantidades, stock y datos de entrada en el servidor.
- [ ] Reservar o descontar stock de forma transaccional, evitando sobreventa.
- [ ] Pruebas de regresión automatizadas y comprobación continua en GitHub.

## 2. Experiencia de compra
- [ ] Búsqueda, filtros, ordenación y paginación.
- [ ] Carrito persistente y actualización clara de cantidades.
- [ ] Checkout accesible, adaptable a móviles y con errores comprensibles.
- [ ] Política de precios, impuestos, gastos de envío y totales coherentes.
- [ ] Historial y seguimiento de pedidos.

## 3. Operación comercial
- [ ] Administración de productos, variantes, imágenes, categorías, stock y pedidos.
- [ ] Integración de pagos en modo de pruebas; webhooks verificados e idempotencia.
- [ ] Confirmaciones por correo realmente enviadas y registradas.
- [ ] Envíos, cancelaciones, devoluciones y reembolsos.
- [ ] Facturación y textos comerciales ajustados al modelo de negocio.

## 4. Preparación y puesta en producción
- [ ] Entorno reproducible con versiones mantenidas de PHP y MariaDB/MySQL.
- [ ] Migraciones versionadas, copias de seguridad y restauración probada.
- [ ] HTTPS, secretos externos al repositorio, logs y monitorización.
- [ ] Revisión de accesibilidad, rendimiento y seguridad.
- [ ] Revisión de privacidad, cookies y condiciones antes de operar.
- [ ] Validación integral en preproducción antes de activar cobros reales.

## Primera iteración — 30 de octubre de 2026
Pruebas locales con PHP 7.3.30 y MariaDB 10.4.21 (versiones disponibles en el equipo, no propuestas para producción):
- 15 archivos PHP sin errores de sintaxis.
- Catálogo, categoría, ficha y respuesta 404 de producto inexistente.
- Carrito, resumen y creación de pedido como invitado.
- Compra con cuenta nueva y con cuenta existente usando contraseña correcta.
- Intento de sustituir contraseña ajena bloqueado; hash anterior conservado.
- Acceso administrativo y consulta de pedidos.

Limitaciones: no se han probado cobros, envío de correo, concurrencia, accesibilidad visual ni despliegue público. Las imágenes se han contrastado por sus rutas; no es una inspección visual de todas ellas. Las correcciones del código no reparan cambios de contraseña que pudieran haberse producido antes.
