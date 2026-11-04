# Imágenes del catálogo

**Administración → Imágenes** permite subir fotografías, asignarlas a un perfume y eliminar las subidas que no se usan. Las imágenes subidas aparecen también en el desplegable «Imagen del proyecto» de cada ficha.

## Uso

1. Elige un archivo **JPG, PNG o WebP** (WebP solo si el GD de la instalación lo admite) de hasta **5 MB** y al menos **200 × 200 píxeles**.
2. Opcional: escribe un nombre descriptivo (se usa para el nombre del archivo) y elige el perfume al que asignarla.
3. Pulsa **Subir imagen**. Si eliges un perfume, su imagen cambia al momento y queda en el historial de su ficha como «Imagen asignada».

Las imágenes de más de 1600 píxeles se reducen manteniendo la proporción. Se guardan en `tienda/public/img/products/Subidas/` con un nombre propio y un sufijo aleatorio. Solo se pueden eliminar subidas que ningún perfume utilice; las imágenes originales del proyecto no se borran desde el panel.

## Seguridad

- El tipo se comprueba por el contenido del archivo, no por su nombre ni por lo que declara el navegador. SVG, GIF y archivos que no son imágenes se rechazan.
- Toda imagen se **recodifica con GD**: se eliminan metadatos (ubicación, cámara, comentarios) y cualquier contenido añadido al archivo, como código escondido tras la imagen.
- Límites de tamaño y de píxeles (hasta 6000 por lado y 12 megapíxeles) para no agotar la memoria del servidor.
- El nombre final lo genera la tienda; no se aceptan rutas ni extensiones del usuario. La carpeta incluye un `.htaccess` que impide ejecutar o listar archivos en Apache/XAMPP.
- CSRF, sesión administrativa, no-store y noindex como el resto del panel. Si la asignación falla, el archivo recién subido se elimina.
- Un envío mayor que `post_max_size` devuelve **413** con un mensaje claro en vez de un error de sesión.
- Las subidas están excluidas de Git: son datos de cada instalación.

## Requisitos en XAMPP

La subida necesita la extensión **GD**. En `C:\xampp\php\php.ini`, quita el `;` de la línea `;extension=gd` y reinicia el servidor. Sin GD, la página lo indica y no muestra el formulario. Para archivos de hasta 5 MB, comprueba también en `php.ini` que `upload_max_filesize` sea al menos `5M` y `post_max_size` al menos `8M`.

## Validación

`tests/admin_images.php`: 37 comprobaciones HTTP con imágenes generadas en la prueba (recodificación sin código añadido, nombre limpio, reducción, PNG, rechazo de texto, SVG, GIF, tamaños, varios archivos, CSRF, 413, asignación con historial y visible en la tienda, borrado protegido frente a rutas ajenas). **827 comprobaciones por entorno** en PHP 8.3, 8.4 y 8.5. La revisión del log admite solo el aviso que PHP emite a propósito en la prueba de 413.

## Límites

Una imagen por perfume (la principal); sin galería, recorte ni texto alternativo editable. En un despliegue con varios servidores, la carpeta de subidas debe ser compartida o sustituirse por un almacenamiento externo.
