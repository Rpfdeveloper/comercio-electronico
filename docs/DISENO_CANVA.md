# Diseño de Canva integrado en la tienda

La referencia visual es **Perfume Shop — Interfaces web v2** (`DAHZjpm14-4`), revisada en Canva el 31 de octubre de 2026. Se adapta su interfaz a las páginas PHP existentes: portada, navegación, catálogo, guía de preferencias y pie de contacto. Las futuras modificaciones del documento de Canva deben trasladarse al código; no existe sincronización automática.

Se conservan el fondo marfil `#F8F5F0`, la tinta `#111827`, el terracota `#A63E00`, bordes discretos, titulares serif y botones rectos. La portada recupera «Encuentra una fragancia que hable de ti», la composición de texto/fotografía y los accesos al catálogo y asesor. Se utiliza Georgia con alternativas del sistema en lugar de depender de la fuente Fraunces del editor; la tienda no carga fuentes ni scripts de Canva.

`tienda/public/css/canva-theme.css` contiene la adaptación y se carga después de los estilos existentes. El menú ofrece Inicio, Catálogo, Asesor y Contacto, además de los accesos existentes a favoritos, cuenta y carrito. Las categorías siguen procediendo de la base de datos. Se mantienen catálogo, precios, stock, formularios, favoritos y acceso administrativo; los botones no son controles de demostración.

La fotografía de portada es la misma que utiliza la propuesta de Canva: [recurso de Pexels](https://images.pexels.com/photos/965989/pexels-photo-965989.jpeg?auto=compress&cs=tinysrgb&w=1280). Se carga desde Pexels con `referrerpolicy="no-referrer"`; el proveedor recibe la petición de imagen del navegador, pero no la URL de la tienda. Se declaran dimensiones para reservar espacio. Si el recurso no está disponible, el JavaScript usa una fotografía ya incluida en el proyecto. No se incorporan enlaces privados, tokens, código de arranque de Canva ni sesiones al repositorio.

El enlace **Asesor** lleva a la guía real por colección, descripción y presupuesto. No se presenta como una IA conectada: la explicación existente conserva su alcance y las preferencias no se envían a un modelo externo. **Contacto próximamente** permanece visible, sin inventar un correo o un formulario operativo. La tienda sigue siendo un prototipo sin pagos reales.

La actualización no necesita migraciones. Deben desplegarse conjuntamente las vistas PHP, la nueva hoja de estilo y `main.js`. Se mantienen foco visible, navegación por teclado, etiquetas, preferencias de movimiento reducido, metadatos SEO y contenido utilizable sin JavaScript.

## Asesor por pasos — 31 de octubre de 2026

Se ha actualizado el propio Canva Code de `DAHZjpm14-4`, conservando su portada y catálogo. La copia previa de Canva es `DAHZktu58po`. El asesor pasa a colección, palabra y presupuesto, con progreso, ejemplos, vuelta atrás y modo de formulario completo. Se ha eliminado «IA» de su navegación y se identifica como guía por filtros. Canva muestra un estado final de diseño; la tienda PHP ejecuta la búsqueda real. No hay publicación de un nuevo sitio en Canva ni sincronización automática con GitHub.

La adaptación funcional está en `descubrir.php`, `js/discovery.js` y `css/canva-theme.css`. Mantiene las categorías de la base de datos y los filtros/validaciones del servidor. Al volver de una búsqueda muestra todas las preferencias; al entrar sin parámetros abre el primer paso. El resumen se muestra al final o en modo completo. Sin JavaScript quedan visibles todos los campos y el envío GET. Cambiar de modo o retroceder conserva los valores durante esa visita; no se añade almacenamiento local.

Verificados los tres pasos, ejemplos, navegación con Intro, foco de títulos y resultados, resumen, vuelta atrás, cambio de modo, coma decimal, cero, rechazo de negativos y búsqueda real. El formulario sin JavaScript devolvió diez coincidencias de prueba. Revisado en escritorio y marcos de 320, 390 y 768 px sin desbordamiento horizontal; no equivale a una auditoría completa de accesibilidad.

Se conservan «Contacto próximamente», las restricciones del prototipo y las preferencias de privacidad. Es necesario desplegar el nuevo `discovery.js` junto al PHP y los estilos; no requiere migraciones.

Evidencia visual: [asesor funcionando en la tienda](capturas/asesor-web-20261031.jpg).
