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
- [x] Inicio y cierre de sesión de clientes.
- [ ] Verificación de correo y recuperación de contraseña.
- [x] Retirar contraseñas en claro de la sesión de checkout.
- [x] Caducidad y renovación de sesiones de clientes, límites de intentos y depuración desactivada por defecto.
- [ ] Límites equivalentes para el acceso administrativo y revisión de la configuración de producción.
- [x] Validar variantes, productos activos, cantidades y stock en el servidor; rechazar datos de compra no escalares.
- [x] Reservar o descontar stock de forma transaccional, evitando sobreventa.
- [x] Pruebas locales de regresión automatizadas.
- [x] Ejecutar las pruebas automáticamente en GitHub.

## 2. Experiencia de compra
- [ ] Búsqueda, filtros, ordenación y paginación.
- [ ] Carrito persistente y actualización clara de cantidades.
- [ ] Checkout accesible, adaptable a móviles y con errores comprensibles.
- [ ] Política de precios, impuestos, gastos de envío y totales coherentes.
- [x] Historial y detalle privado de pedidos propios.
- [ ] Seguimiento del transportista.

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

Pendiente inmediato: límites de intentos de acceso, cuenta de cliente independiente, verificación de correo y política de contraseñas. El descuento actual se realiza al registrar el pedido; se deberá adaptar a reservas, caducidad y estados de pago antes de integrar cobros.

## Tercera iteración — concurrencia e integración continua (30 de octubre de 2026)

- GitHub Actions ejecuta sintaxis, regresiones HTTP, concurrencia y revisión de logs en PHP 8.3 y 8.4 con MariaDB 11.4.
- 41 comprobaciones de integración y 29 de concurrencia correctas en **cada** versión: 70 por entorno, 140 ejecuciones de comprobaciones en total, no 140 casos distintos.
- 21 archivos PHP sin errores de sintaxis; logs del servidor sin avisos, errores ni deprecaciones.
- Dos compradores con sesiones independientes compiten por la última unidad, tanto de una variante como de un producto sin variantes: un único pedido, stock cero y rechazo HTTP 409 de la otra compra.
- Carritos con dos productos añadidos en orden inverso: pedidos completos, sin descuentos parciales ni interbloqueos en los escenarios probados.
- Doble confirmación simultánea de la misma sesión: un solo pedido y una sola unidad descontada.
- La barrera de pruebas comprueba dos transacciones realmente solapadas antes de continuar, con observaciones separadas para renovar el snapshot de InnoDB.
- Las contraseñas de CI son ficticias y corresponden a servicios desechables. Las acciones están fijadas a commits y el workflow solo tiene permisos de lectura.
- Se corrige el mensaje de confirmación: muestra el correo de contacto sin prometer un envío que aún no existe. Los conflictos de stock/precios devuelven 409; los errores inesperados, 500.

Evidencia: [ejecución correcta en GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions/runs/41126154248), sobre el commit `887c0720d0c4f519ea2af62814ce6ce017ae6674`. Se verificó por lectura posterior el contenido de cada lote publicado.

Los escenarios cubren concurrencia funcional, no carga sostenida, fallos de red ni recuperación tras una caída del servidor. La cuenta de cliente independiente, límites de intentos, recuperación/verificación de correo y pagos siguen pendientes.

## Cuarta iteración — cuenta e historial privado

Se completan acceso, salida, historial, detalle autorizado por cliente, caducidad de sesiones y límites compartidos de acceso/checkout. Se añade una migración aditiva, configuración externa del secreto y revisión de patrones sensibles. Las tareas de cuenta y límites indicadas como pendientes en las iteraciones anteriores quedan cubiertas para clientes; recuperación/verificación de correo, protección equivalente del administrador y pagos continúan pendientes.

Validación local: 115 comprobaciones correctas (41 + 45 + 29), 27 PHP sin errores de sintaxis y log sin avisos en PHP 8.3.6 / MariaDB 10.11.14. La misma suite pasa en PHP 8.3 y 8.4 con MariaDB 11.4 en [GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions/runs/41134720548). [Detalle del avance y muestra visual](PROGRESO.md). [Instrucciones de actualización](CUENTAS_CLIENTE.md).
