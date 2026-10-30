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
- [x] Protección CSRF en todas las operaciones que modifican datos.
- [ ] Inicio y cierre de sesión de clientes; verificación de correo y recuperación de contraseña.
- [x] Retirar contraseñas en claro de la sesión de checkout.
- [ ] Endurecer sesiones, limitar intentos de acceso y desactivar depuración en producción.
- [x] Validar variantes, productos activos, cantidades y stock en el servidor; rechazar datos de compra no escalares.
- [x] Reservar o descontar stock de forma transaccional, evitando sobreventa.
- [x] Pruebas locales de regresión automatizadas.
- [ ] Ejecutar las pruebas automáticamente en GitHub.

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

## Segunda iteración — formularios y stock
- Protección CSRF en todos los formularios que modifican datos, incluido acceso y cierre de sesión administrativo.
- Borrado del carrito y cierre de sesión exclusivamente mediante POST.
- Cantidades enteras entre 1 y 999, validación de producto activo y pertenencia y disponibilidad de variantes; cero elimina líneas al actualizar.
- Relectura de precios y bloqueo de stock al confirmar; descuento y pedido en una transacción. Los cambios de precio requieren volver a revisar el resumen.
- Contraseñas retiradas de la sesión: autenticación antes del resumen y comprobación de que las credenciales siguen vigentes al confirmar.
- Configuración por entorno y depuración desactivada por defecto.
- Eliminación de marcas BOM que emitían contenido antes de iniciar sesiones; redirecciones y 404 antes de renderizar HTML.
- Suite publicada en `tests/`: 41 comprobaciones de integración correctas con PHP 7.3.30 y MariaDB 10.4.21, sin avisos PHP en el log de la ejecución. Pruebas también con `output_buffering=0`.

Pendiente inmediato: pruebas de concurrencia reales e integración continua, límites de intentos de acceso, cuenta de cliente independiente, verificación de correo y política de contraseñas. El descuento actual se realiza al registrar el pedido; se deberá adaptar a reservas, caducidad y estados de pago antes de integrar cobros.
