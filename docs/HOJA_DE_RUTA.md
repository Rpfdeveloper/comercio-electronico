# Evolución hacia una tienda completa

El objetivo es evolucionar esta práctica académica, mediante mejoras incrementales y verificadas, hasta convertirla en una tienda de comercio electrónico real y completa.

Estado actual: prototipo funcional en desarrollo. Todavía no está preparado para operar con clientes ni cobrar pagos reales.

## Retención y captación — 31 de octubre de 2026

- [x] Favoritos sin registro, persistentes en el navegador y con precios actuales.
- [x] Metadatos, canónicas y sitemap preparados, con indexación desactivada por defecto.
- [ ] Sincronizar favoritos entre dispositivos mediante cuenta.
- [ ] Avisos de reposición solicitados, con preferencias y baja.
- [ ] Contenido original por familias olfativas y ocasiones, con datos verificados.
- [ ] Lanzamiento real, Search Console y marcado Product/variantes.
- [ ] Medir retorno y recompra antes de diseñar un programa de puntos o descuentos.

[Entrega, investigación y límites](RETENCION_SEO.md). Favoritos y SEO no prueban por sí solos un aumento de retención o tráfico.

## Organización
- `original`: entrega recuperada, conservada como referencia.
- `develop`: desarrollo de la tienda y correcciones.
- `main`: tienda, pruebas y documentación integradas mediante pull request.
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
- [x] Sesión independiente, caducidad y límites de intentos para el acceso administrativo.
- [ ] MFA, roles y revisión de la configuración de producción.
- [x] Validar variantes, productos activos, cantidades y stock en el servidor; rechazar datos de compra no escalares.
- [x] Reservar o descontar stock de forma transaccional, evitando sobreventa.
- [x] Pruebas locales de regresión automatizadas.
- [x] Ejecutar las pruebas automáticamente en GitHub.

## 2. Experiencia de compra
- [x] Portada adaptable con colecciones y productos del catálogo.
- [x] Guía por preferencias, presupuesto y disponibilidad, sin dependencia de JavaScript.
- [ ] Asesor con IA conectado al catálogo, con datos verificados, límites de uso y alternativa sin modelo.
- [x] Búsqueda, filtros, ordenación y paginación.
- [x] Actualización clara de cantidades, guardadas al continuar hacia la compra.
- [ ] Carrito persistente entre sesiones.
- [x] Checkout adaptable, errores asociados a campos y navegación con foco.
- [ ] Auditoría completa de accesibilidad y pruebas en dispositivos móviles reales.
- [x] Desglose coherente de productos, IVA y envío en carrito, resumen y pedido.
- [ ] Política comercial de precios, impuestos y gastos de envío configurable.
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

## Quinta iteración — protección administrativa

El panel incorpora sesión y cookie independientes de la cuenta de cliente, renovación de identificador y CSRF, caducidad, invalidación al cambiar configuración y límites por cuenta/IP. Los contadores administrativos y de clientes se mantienen separados. Se corrigen respuestas ante identificadores de pedido malformados y se extrae el limitador común sin cambiar las claves de clientes.

Validación local: 163 comprobaciones correctas (41 + 45 + 48 + 29), 31 PHP sin errores de sintaxis y logs sin avisos. [Resultados de CI y detalle](PROGRESO.md). [Guía de actualización](ADMINISTRACION.md). El panel sigue teniendo una única cuenta; MFA, roles y revisión final de producción permanecen pendientes.

## Sexta iteración — catálogo con filtros

Búsqueda por nombre, marca y descripción, filtros combinables, ordenación estable y paginación de doce productos. Los precios y la disponibilidad distinguen variantes activas, agotadas e inactivas, y las fichas no muestran productos ocultos. Se conservan las URL de categorías.

Validación: 222 comprobaciones por entorno, incluidas 59 de catálogo, 35 archivos PHP sin errores y logs sin avisos. PHP 8.3 y 8.4 con MariaDB 11.4 pasan en [GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions/runs/41141911642). [Detalle y captura](PROGRESO.md) · [Uso y límites](CATALOGO.md). Quedan pendientes pruebas con catálogos grandes, móvil real y auditoría completa de accesibilidad.

## Séptima iteración — revisión e integración en main (31 de octubre de 2026)

Corregida la compra de productos de categorías retiradas, tanto desde carritos anteriores como mediante POST directo y durante confirmaciones simultáneas. La validación transaccional mantiene un bloqueo compartido de la categoría.

239 comprobaciones correctas por entorno (41 + 45 + 48 + 73 + 32), con PHP 8.3 y 8.4 y MariaDB 11.4. El [PR #1](https://github.com/Rpfdeveloper/comercio-electronico/pull/1) se fusionó tras corregir la revisión y superar sus comprobaciones. `main` incluye ahora el código de la tienda. Las copias `backup/2026-10-31-main-85fbc66` y `backup/2026-10-31-develop-e300be6` conservan el estado previo; `original` no cambia.

## Octava iteración — carrito y compra clara (31 de octubre de 2026)

Las cantidades se guardan al continuar, los importes se muestran desde el carrito y los datos de envío se validan antes del resumen. Errores enlazados, foco visible, autocompletado y elección de compra invitada o con cuenta. Diseño revisado en escritorio y marcos de 320, 390 y 768 px; no equivale a probar dispositivos reales.

286 comprobaciones locales correctas, incluidas 47 nuevas de checkout; sintaxis y logs PHP correctos. Copia previa `backup/2026-10-31-antes-checkout-e19ae30`. [Detalle, pruebas y evidencia](PROGRESO.md) · [Uso y límites](COMPRA.md).
