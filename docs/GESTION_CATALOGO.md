# Gestión del catálogo

El panel permite crear perfumes inactivos, editar su ficha, seleccionar una imagen existente del proyecto y gestionar precios, ofertas, existencias y formatos. Se accede por **Administración → Catálogo**; después de iniciar sesión se muestra **Resumen**. La autenticación mantiene una única cuenta administrativa, sesión independiente, CSRF, caducidad, limitador de intentos y respuestas privadas sin caché ni indexación.

## Uso

**Nuevo perfume** solicita nombre, enlace, colección, marca, descripciones y precio/stock del formato base. Se crea siempre inactivo, aunque se manipule el formulario. Después puede revisarse y activarse. Los enlaces admiten minúsculas, números y guiones y deben ser únicos; una vez creado el perfume su enlace se conserva.

**Editar perfume** permite cambiar nombre, colección, marca, descripciones, imagen y visibilidad. Cuando hay formatos, los precios y existencias se editan por formato; los valores base quedan intactos y no se suman. Dejar la oferta vacía la retira. Los importes admiten coma o punto, hasta dos decimales y deben ser positivos; una oferta no puede superar el precio habitual. El stock admite enteros entre cero y 999999. Estos precios siguen siendo anteriores al IVA que calcula el carrito.

**Añadir formato** requiere una capacidad entre 1 y 10000 ml, no repetida en el perfume, y un SKU opcional único. Se admiten hasta 100 formatos por perfume. Capacidad y SKU de un formato existente no se cambian desde el editor. Para añadir el primer formato, las existencias base deben ser cero: evita ocultar unidades disponibles bajo una estructura nueva. Los carritos anteriores deberán elegir una capacidad; sus importes y existencias se vuelven a validar en el servidor.

La activación del perfume no activa su categoría ni todos sus formatos. La disponibilidad pública requiere los estados correspondientes y existencias válidas. Para retirar un perfume o formato, usar **Inactivo**. El panel no borra referencias ni pedidos históricos. La imagen se limita a archivos locales del proyecto; no se suben archivos ni se admiten URL externas en el editor. Una ficha sin imagen muestra «Imagen pendiente».

El listado permite buscar por nombre, marca o enlace, filtrar visibilidad y recorrer 25 perfumes por página. Los filtros inválidos reciben 422. Inventario mantiene sus filtros por referencia y enlaza con el editor del perfume. Resumen muestra cantidades reales del catálogo, pedidos en estado `pending`, referencias con stock bajo o agotado y datos de stock por revisar; incluye referencias activas e inactivas y no interpreta los pedidos como pagos cobrados.

## Consistencia e historial

Cada escritura bloquea primero el producto y después sus formatos, dentro de una transacción, siguiendo el orden compatible con el checkout. Un HMAC del estado leído y de la revisión del historial detecta formularios antiguos. Si otro administrador o una compra ha cambiado el producto, se devuelve 409 sin sobrescribirlo; hay que recargar los datos actuales. Un formulario válido no impide que se comprueben nuevamente el precio y el stock durante la compra.

Todos los campos y formatos se validan en el servidor. Si alguno falla, se revierte la operación completa. El historial se escribe en la misma transacción; si no puede registrarse, no se guardan cambios. Cada entrada conserva campos anteriores y posteriores y la acción realizada. La ficha muestra los últimos 20 cambios; no guarda IP, credenciales, identificadores de sesión ni datos de clientes. Un guardado sin diferencias no añade ruido al historial. Los movimientos de compras se consultan en los pedidos, no se duplican como ediciones de catálogo.

El historial corresponde a la única cuenta administrativa actual; no identifica personas distintas que compartan esa cuenta. No sustituye un sistema de auditoría inmutable ni registra modificaciones directas de SQL. Los enlaces y las líneas de pedidos anteriores se conservan cuando cambian la ficha o el precio.

## Actualización y recuperación

1. Conservar una copia verificada del código y de la base existente, con acceso restringido.
2. Aplicar las migraciones anteriores 001–003 si faltan. Aplicar `tienda/sql/migrations/004_catalog_history.sql` sobre la misma base antes de desplegar el editor. Es aditiva e idempotente: crea `catalogo_cambio` sin reiniciar productos, stock, clientes ni pedidos. La cuenta de migraciones necesita los permisos de DDL; no ampliar los permisos de la cuenta web.
3. Desplegar los PHP y los recursos CSS/JS conjuntamente. Mantener los secretos exclusivamente en el entorno y la depuración desactivada.
4. Verificar el acceso, lectura de catálogo y una edición controlada en preproducción, y comprobar el historial y la ficha pública.

**No importar `tienda_perfumes_schema.sql` sobre una base que deba conservarse:** es un esquema de instalación que recrea tablas. Ahora declara UTF-8 explícitamente para evitar tildes corruptas al cargar los ejemplos; esto no reescribe por sí solo los textos de una instalación antigua. Revisar cualquier reparación de datos existentes por separado.

Para volver al código anterior, se puede conservar la tabla aditiva. No eliminar el historial ni restaurar una copia antigua de la base sin reconciliar las compras y ediciones posteriores. Revertir el código no revierte precios ni existencias ya guardados.

## Ficha pública y ayuda

La ficha continúa la paleta y tipografía adaptadas de Canva. Muestra precio, existencias y controles del formato elegido; JavaScript actualiza solo el texto visible. Sin JavaScript, las opciones conservan sus precios y la compra sigue validándose en PHP. Favoritos no reserva unidades. Se conservan la ruta de navegación, los metadatos, el carrito y la protección CSRF.

`public/ayuda.php` explica formatos, favoritos, cuentas y límites del prototipo con secciones desplegables nativas. Tiene enlace en el pie y entrada en el sitemap cuando la indexación está habilitada explícitamente. Contacto continúa como «Próximamente». No hay pasarela, correo comercial ni modelo de IA conectado.

## Verificación y límites

**630 comprobaciones locales correctas**, 64 PHP con sintaxis válida, JavaScript válido, revisión de patrones sensibles y log sin avisos PHP. Se incorporan 75 comprobaciones específicas de gestión: alta, activación, edición, formatos, validaciones, historial, reversión por fallo SQL, formularios obsoletos y concurrencia real de edición/edición y edición/compra. La ayuda y la importación UTF-8 se comprueban en portada/descubrimiento; SEO comprueba que el sitemap corresponde a páginas indexables.

Revisión en navegador de la ficha pública, cambio de formato y página de ayuda. Revisión de las interfaces administrativas con HTML generado desde una base de pruebas y formularios desactivados. Las siete pantallas nuevas o renovadas no desbordan horizontalmente en marcos de 320, 390 y 768 px. Esto no sustituye pruebas en dispositivos físicos, una auditoría completa de accesibilidad ni pruebas de carga.

Quedan pendientes la subida de imágenes, gestión de marcas/categorías, estados operativos de pedidos, MFA/roles, pagos, correo y preparación de producción. La página continúa siendo un prototipo.
