# Portada y guía de perfumes

La portada incorpora una presentación visual, colecciones leídas de la base de datos, acceso a la guía y las cuatro últimas incorporaciones disponibles. Los precios y el stock proceden de la misma consulta que el catálogo. «Contacto próximamente» sustituye cualquier apariencia de formulario operativo; no se publica un correo personal.

## Guía disponible

`tienda/public/descubrir.php` permite elegir colección, texto y presupuesto máximo. Los campos son opcionales. Muestra únicamente productos activos de categorías activas con stock, ordenados por precio, de doce en doce. El presupuesto se compara con la capacidad más económica disponible, incluidas sus ofertas vigentes según las reglas del prototipo.

La búsqueda encuentra fragmentos literales en nombre, marca, descripción corta y descripción larga. Esta ampliación también se aplica al catálogo. Por ejemplo, «floral» encuentra un perfume cuya ficha larga contiene ese término aunque su descripción corta solo diga «Fragancia femenina». No interpreta frases, sinónimos o preferencias implícitas.

La guía es un filtro real del catálogo, **no una integración de IA**. No genera características, puntuaciones de afinidad ni recomendaciones ficticias, y no envía consultas a terceros. Las preferencias viajan en la URL: el historial y los logs del servidor pueden conservarlas. No introducir datos personales en ellas.

## Diseño y tecnologías

- HTML semántico, formularios GET y consultas preparadas; el recorrido funciona sin JavaScript.
- CSS Grid, tipografía fluida con `clamp()`, relaciones de aspecto y adaptación de controles con consultas de contenedor. Los estilos base mantienen la funcionalidad sin soporte de estas consultas.
- Preferencia de movimiento reducido, controles etiquetados, foco visible, salto al contenido y foco en errores/resultados.
- Imagen de portada de 26 KB con dimensiones explícitas y prioridad de carga; imágenes secundarias con carga diferida. Se reutilizan las imágenes del proyecto.
- Fuentes del sistema y CSS local: no se añaden fuentes remotas, rastreadores, dependencias npm ni servicios externos.
- Etiquetas originales «Women Perfumes» y «Men Perfumes» presentadas en español sin modificar la base de datos ni los nombres personalizados.
- Matriz de CI ampliada a PHP 8.3, 8.4 y 8.5. El soporte de pruebas libera el objeto cURL sin llamar a `curl_close()`, obsoleto desde PHP 8.5.

## Validación

30 comprobaciones nuevas cubren portada, colecciones retiradas, disponibilidad, precio comprable de variantes, búsqueda por descripción larga, escape HTML, SQL literal, parámetros malformados, páginas inexistentes y paginación con filtros. Se conservan las 286 comprobaciones anteriores: **316 en total**.

Validación local: PHP 8.3.6 y MariaDB 10.11.14, 316 comprobaciones correctas, 42 archivos PHP sin errores de sintaxis y log PHP sin avisos, errores o deprecaciones. La revisión de patrones sensibles también pasa. La matriz remota y su estado se consultan en [GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions).

Revisión visual sobre HTML generado por PHP con la base desechable: escritorio y marcos de 320, 390 y 768 px, sin imágenes rotas ni desbordamiento horizontal del documento en los marcos comprobados. Las interacciones dinámicas se comprueban mediante HTTP contra PHP y MariaDB; las capturas estáticas usadas para revisar el diseño no sustituyen una prueba de interacción en un navegador conectado al servidor.

No constituye una auditoría completa de accesibilidad, compatibilidad con todos los navegadores, rendimiento bajo carga ni prueba en dispositivos físicos.

## Siguiente fase: asesor con IA

La propuesta de interfaces en Canva sirve como referencia de experiencia. Para una integración real, antes de habilitarla:

1. Completar y revisar las descripciones, familias y notas de cada producto. Los datos desconocidos deben permanecer desconocidos.
2. Definir proveedor, límites de uso y coste; mantener credenciales exclusivamente en el servidor.
3. Convertir preferencias en filtros validados y recuperar productos existentes. Precio y disponibilidad siempre se vuelven a consultar en el catálogo.
4. Pedir al modelo explicaciones basadas solo en esos datos y verificar los identificadores devueltos. Ninguna respuesta del modelo debe alterar precios, stock, pedidos o pagos.
5. Añadir límites de peticiones, tratamiento de errores, aviso de IA y una alternativa de búsqueda guiada cuando el proveedor no esté disponible.
6. Probar respuestas sin coincidencias, datos incompletos e intentos de introducir instrucciones dentro de descripciones o consultas.

No se requiere una migración para esta entrega. El proveedor y sus credenciales siguen pendientes; no se han creado suscripciones ni activado cobros.

## Referencias técnicas

- [Consultas de contenedor — Chrome for Developers](https://chrome.dev/container-queries/).
- [Media queries para accesibilidad — MDN](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Media_queries/Using_for_accessibility).
- [PHP 8.5](https://www.php.net/releases/8.5/en.php).
- [Estado de curl_close — manual PHP](https://www.php.net/manual/en/function.curl-close.php).
