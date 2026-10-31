# Favoritos y preparación SEO

## Decisión y objetivo

El SEO facilita que personas nuevas encuentren la tienda. La retención busca que quienes ya la visitaron tengan razones para volver. Esta entrega incorpora **una colección de favoritos sin registro** y prepara la indexación de las páginas públicas; no promete aumentos de ventas ni posiciones en Google.

La investigación de Baymard observa fricción cuando guardar un producto exige crear una cuenta. Se aplica esa conclusión al catálogo de perfumes: guardar desde portada, catálogo, guía o ficha, y retomar la selección desde el menú. Se conserva el diseño editorial cálido de la portada y el contacto como «Contacto próximamente».

## Qué funciona

- Guardar y quitar perfumes, sin cuenta y sin JavaScript. Tras guardar se vuelve a la misma página, conservando los filtros; un aviso confirma el resultado.
- «Mis favoritos» muestra hasta 24 perfumes, los más recientemente añadidos primero. La cookie dura 180 días desde el último cambio; no se prolonga por navegar.
- Precios, capacidades disponibles y stock se consultan de nuevo en la base de datos. Guardar no reserva existencias ni conserva un precio anterior.
- Los productos o categorías retirados se ocultan con un aviso. Los agotados siguen visibles para poder volver a consultarlos. La lista se puede vaciar.
- Sin iniciar sesión, la información se conserva en ese navegador. Con una cuenta abierta se guarda en su colección, recuperable desde otra sesión. La unión de la lista invitada requiere elegirla expresamente; borrar cookies solo afecta a la lista del navegador. [Funcionamiento y actualización](FAVORITOS_CUENTA.md). No hay campañas, seguimiento publicitario ni envíos automáticos.

La cookie `perfume_favorites` contiene únicamente identificadores públicos; no datos de cuenta, precios ni autorizaciones. Se trata como entrada no fiable: tipos, tamaño, cantidad y rango validados, consulta preparada y filtrado de activos. Se marca HttpOnly, SameSite=Lax y Secure cuando PHP recibe HTTPS. El alcance es la carpeta pública. Las escrituras requieren POST y CSRF; la redirección solo permite rutas públicas conocidas. La lista invitada no necesita tablas nuevas. La colección de cuenta requiere la migración aditiva 003.

## SEO preparado

Títulos y descripciones propios para producto y categoría, un h1 principal, navegación de ruta en las fichas, URL canónica y metadatos Open Graph cuando existe dominio configurado. La segunda página del catálogo tiene su propia canónica. Las búsquedas, filtros, órdenes alternativos, categorías vacías, errores y páginas personales quedan fuera de indexación.

`sitemap.php` genera XML con páginas públicas, categorías con productos, paginación y fichas activas. Las direcciones son absolutas, coherentes con las canónicas y parten de configuración, nunca de la cabecera Host. No se inventan fechas de actualización, reseñas, puntuaciones, beneficios de fidelización, ofertas ni políticas comerciales.

**Por defecto toda la tienda lleva noindex y el sitemap responde 503.** No se ha activado la indexación, enviado nada a Search Console ni desplegado públicamente.

Para el futuro lanzamiento, solo tras revisar el alojamiento, catálogo y operativa real:

1. Configurar `TIENDA_PUBLIC_URL` con la dirección HTTPS de la carpeta pública, sin barra final, usuario, contraseña, puerto, query ni fragmento. Ejemplo ficticio: `https://shop.example.invalid/tienda/public`.
2. Mantener `TIENDA_DEBUG=false`; activar explícitamente `TIENDA_INDEXABLE=true`. Si falta una condición, se conserva noindex.
3. Verificar que PHP reconoce HTTPS para emitir cookies Secure, el dominio canónico coincide con el servido y los controles de acceso protegen administración, configuración y datos. Noindex no es autenticación.
4. Revisar robots.txt en la raíz del dominio: no bloquear el rastreo de páginas donde Google debe leer noindex. Referenciar el sitemap o enviarlo a Search Console y comprobar URLs reales con sus herramientas.
5. Revisar catálogo y precios antes de incorporar marcado Product/variantes y políticas comerciales. Sigue pendiente; no se anuncian ofertas de este prototipo como si fueran una tienda operativa.

El sitemap actual está pensado para el catálogo pequeño. Rechaza tamaños excesivos en lugar de publicar una lista truncada. Para un catálogo grande hay que dividirlo en varios mapas e índice y medir sus consultas. Las respuestas con favoritos no se cachean de forma compartida. La optimización de caché pública requiere separar el estado personal.

## Siguiente sistema de fidelización

Estado y orden propuesto; los avisos y medidas comerciales requieren operativa real:

1. **Implementado:** recuperar selecciones entre dispositivos mediante una cuenta, uniendo favoritos invitados de forma explícita. El alta independiente de clientes sigue pendiente.
2. Avisos de reposición solicitados por cada persona, con preferencias y baja. Requieren proveedor de correo y datos reales; no se han conectado.
3. Guías originales por familias olfativas y ocasiones basadas en datos verificados. Medir qué búsquedas y fichas ayudan; evitar páginas automáticas casi idénticas.
4. Asesor con IA que solo recomiende productos del catálogo y confirme precio/stock en el servidor, con alternativa por filtros. Requiere proveedor y presupuesto; sigue pendiente.
5. Medir retorno, paso de favorito a carrito y recompra antes de financiar puntos o descuentos. No hay analítica instalada ni resultados de retención medidos.

## Validación

38 comprobaciones HTTP de favoritos y 39 de SEO, además de las 316 existentes: **393 comprobaciones en aquella entrega**. La colección de cuenta añade otras 45: 438 en total. Incluyen nueva sesión sin cuenta, cambios de precio/stock, productos retirados, límite de lista, cookie inválida, CSRF, redirecciones, prototipo no indexable, servidor local con configuración de lanzamiento y coherencia de todas las URLs del sitemap con sus páginas.

La revisión visual usa HTML renderizado por PHP con datos de prueba; no sustituye las pruebas HTTP funcionales. Quedan pendientes dispositivos reales, tecnologías de asistencia y la configuración del alojamiento final.

## Fuentes consultadas el 31 de octubre de 2026

- [Baymard: Product Page UX, guardar sin registro](https://baymard.com/research-articles/current-state-ecommerce-product-page-ux).
- [Google: estructura de URL para comercio electrónico](https://developers.google.com/search/docs/specialty/ecommerce/designing-a-url-structure-for-ecommerce-sites).
- [Google: paginación y filtros](https://developers.google.com/search/docs/specialty/ecommerce/pagination-and-incremental-page-loading).
- [Google: noindex](https://developers.google.com/search/docs/crawling-indexing/block-indexing).
- [Google: construir y enviar un sitemap](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).
- [Google: datos estructurados de producto](https://developers.google.com/search/docs/appearance/structured-data/product).
