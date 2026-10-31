# Diseño de Canva integrado en la tienda

La referencia visual es **Perfume Shop — Interfaces web v2** (`DAHZjpm14-4`), revisada en Canva el 31 de octubre de 2026. Se adapta su interfaz a las páginas PHP existentes: portada, navegación, catálogo, guía de preferencias y pie de contacto. Las futuras modificaciones del documento de Canva deben trasladarse al código; no existe sincronización automática.

Se conservan el fondo marfil `#F8F5F0`, la tinta `#111827`, el terracota `#A63E00`, bordes discretos, titulares serif y botones rectos. La portada recupera «Encuentra una fragancia que hable de ti», la composición de texto/fotografía y los accesos al catálogo y asesor. Se utiliza Georgia con alternativas del sistema en lugar de depender de la fuente Fraunces del editor; la tienda no carga fuentes ni scripts de Canva.

`tienda/public/css/canva-theme.css` contiene la adaptación y se carga después de los estilos existentes. El menú ofrece Inicio, Catálogo, Asesor y Contacto, además de los accesos existentes a favoritos, cuenta y carrito. Las categorías siguen procediendo de la base de datos. Se mantienen catálogo, precios, stock, formularios, favoritos y acceso administrativo; los botones no son controles de demostración.

La fotografía de portada es la misma que utiliza la propuesta de Canva: [recurso de Pexels](https://images.pexels.com/photos/965989/pexels-photo-965989.jpeg?auto=compress&cs=tinysrgb&w=1280). Se carga desde Pexels con `referrerpolicy="no-referrer"`; el proveedor recibe la petición de imagen del navegador, pero no la URL de la tienda. Se declaran dimensiones para reservar espacio. Si el recurso no está disponible, el JavaScript usa una fotografía ya incluida en el proyecto. No se incorporan enlaces privados, tokens, código de arranque de Canva ni sesiones al repositorio.

El enlace **Asesor** lleva a la guía real por colección, descripción y presupuesto. No se presenta como una IA conectada: la explicación existente conserva su alcance y las preferencias no se envían a un modelo externo. **Contacto próximamente** permanece visible, sin inventar un correo o un formulario operativo. La tienda sigue siendo un prototipo sin pagos reales.

La actualización no necesita migraciones. Deben desplegarse conjuntamente las vistas PHP, la nueva hoja de estilo y `main.js`. Se mantienen foco visible, navegación por teclado, etiquetas, preferencias de movimiento reducido, metadatos SEO y contenido utilizable sin JavaScript.
