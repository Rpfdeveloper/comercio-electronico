<?php
declare(strict_types=1);

// Biblioteca de imágenes del catálogo. Las subidas se recodifican siempre con GD:
// se descartan metadatos (EXIF, ubicación, comentarios) y cualquier contenido añadido al archivo.
require_once __DIR__ . '/admin_catalog.php';

const ADMIN_IMAGE_FOLDER = 'Subidas';
const ADMIN_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
const ADMIN_IMAGE_MIN_SIDE = 200;
const ADMIN_IMAGE_MAX_SIDE = 6000;
const ADMIN_IMAGE_MAX_PIXELS = 12000000; // Cabe en el memory_limit de 128 MB habitual.
const ADMIN_IMAGE_OUTPUT_SIDE = 1600;

class ImageUploadError extends DomainException {}

function admin_images_dir(): string
{
    return __DIR__ . '/../../public/img/products/' . ADMIN_IMAGE_FOLDER;
}

// Formatos que esta instalación puede leer y escribir.
function admin_images_formats(): array
{
    $formats = [];
    if (!function_exists('imagecreatetruecolor')) { return $formats; }
    if (function_exists('imagecreatefromjpeg') && (imagetypes() & IMG_JPG)) { $formats[IMAGETYPE_JPEG] = 'jpg'; }
    if (function_exists('imagecreatefrompng') && (imagetypes() & IMG_PNG)) { $formats[IMAGETYPE_PNG] = 'png'; }
    if (function_exists('imagecreatefromwebp') && defined('IMG_WEBP') && (imagetypes() & IMG_WEBP)) { $formats[IMAGETYPE_WEBP] = 'webp'; }
    return $formats;
}

function admin_images_library(PDO $pdo): array
{
    $usage = [];
    foreach ($pdo->query('SELECT imagen_principal, COUNT(*) AS total FROM producto WHERE imagen_principal IS NOT NULL GROUP BY imagen_principal')->fetchAll() as $row) {
        $usage[$row['imagen_principal']] = (int)$row['total'];
    }
    $library = [];
    foreach (admin_catalog_images() as $path) {
        $library[] = ['path'=>$path, 'folder'=>basename(dirname($path)), 'name'=>basename($path),
            'uploaded'=>basename(dirname($path)) === ADMIN_IMAGE_FOLDER, 'uses'=>$usage[$path] ?? 0];
    }
    usort($library, static fn($a, $b) => [$b['uploaded'], $a['folder'], $a['name']] <=> [$a['uploaded'], $b['folder'], $b['name']]);
    return $library;
}

function admin_images_basename(string $label): string
{
    $ascii = strtr(admin_images_lower($label), ['á'=>'a','à'=>'a','ä'=>'a','â'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
        'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','ñ'=>'n','ç'=>'c']);
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? '', '-');
    $slug = substr($slug, 0, 60);
    return rtrim($slug, '-') ?: 'imagen';
}

function admin_images_lower(string $text): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') :
        strtr(strtolower($text), ['Á'=>'á','É'=>'é','Í'=>'í','Ó'=>'ó','Ú'=>'ú','Ü'=>'ü','Ñ'=>'ñ','Ç'=>'ç']);
}

// Valida y guarda la imagen. Devuelve la ruta pública relativa (img/products/Subidas/...).
function admin_images_store($file, string $label): string
{
    $formats = admin_images_formats();
    if (!isset($formats[IMAGETYPE_JPEG], $formats[IMAGETYPE_PNG])) {
        throw new ImageUploadError('La subida necesita la extensión GD de PHP con JPEG y PNG. Actívala en php.ini y reinicia el servidor.');
    }
    if (!is_array($file) || !is_string($file['tmp_name'] ?? null) || !is_int($file['error'] ?? null) || !is_int($file['size'] ?? null)) {
        throw new ImageUploadError('Selecciona un único archivo de imagen.');
    }
    $messages = [UPLOAD_ERR_INI_SIZE=>'El archivo supera el tamaño que admite el servidor.', UPLOAD_ERR_FORM_SIZE=>'El archivo supera 5 MB.',
        UPLOAD_ERR_PARTIAL=>'El archivo no llegó completo. Vuelve a intentarlo.', UPLOAD_ERR_NO_FILE=>'Selecciona una imagen.'];
    if ($file['error'] !== UPLOAD_ERR_OK) { throw new ImageUploadError($messages[$file['error']] ?? 'No se ha podido recibir el archivo.'); }
    if (!is_uploaded_file($file['tmp_name'])) { throw new ImageUploadError('No se ha podido recibir el archivo.'); }
    if ($file['size'] > ADMIN_IMAGE_MAX_BYTES || filesize($file['tmp_name']) > ADMIN_IMAGE_MAX_BYTES) { throw new ImageUploadError('El archivo supera 5 MB.'); }
    // El tipo se decide por el contenido, nunca por el nombre ni por el tipo que declara el navegador.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($formats[$info[2]])) { throw new ImageUploadError('Usa una imagen JPG, PNG' . (isset($formats[IMAGETYPE_WEBP]) ? ' o WebP' : '') . '.'); }
    [$width, $height] = $info;
    if ($width < ADMIN_IMAGE_MIN_SIDE || $height < ADMIN_IMAGE_MIN_SIDE) { throw new ImageUploadError('La imagen debe medir al menos ' . ADMIN_IMAGE_MIN_SIDE . ' × ' . ADMIN_IMAGE_MIN_SIDE . ' píxeles.'); }
    if ($width > ADMIN_IMAGE_MAX_SIDE || $height > ADMIN_IMAGE_MAX_SIDE || $width * $height > ADMIN_IMAGE_MAX_PIXELS) {
        throw new ImageUploadError('La imagen es demasiado grande: hasta ' . ADMIN_IMAGE_MAX_SIDE . ' píxeles por lado.');
    }
    $type = $info[2];
    $source = @($type === IMAGETYPE_JPEG ? imagecreatefromjpeg($file['tmp_name']) : ($type === IMAGETYPE_PNG ? imagecreatefrompng($file['tmp_name']) : imagecreatefromwebp($file['tmp_name'])));
    if (!$source) { throw new ImageUploadError('La imagen está dañada o no se puede leer.'); }
    $scale = min(1, ADMIN_IMAGE_OUTPUT_SIDE / max($width, $height));
    $outW = max(1, (int)round($width * $scale)); $outH = max(1, (int)round($height * $scale));
    $canvas = imagecreatetruecolor($outW, $outH);
    if ($type === IMAGETYPE_JPEG) { imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255)); }
    else { imagealphablending($canvas, false); imagesavealpha($canvas, true); imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127)); }
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $outW, $outH, $width, $height);
    $dir = admin_images_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) { throw new ImageUploadError('No se puede crear la carpeta de imágenes. Revisa los permisos de img/products.'); }
    $extension = $formats[$type];
    $name = admin_images_basename($label) . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target = $dir . '/' . $name;
    $temp = $dir . '/.subida-' . bin2hex(random_bytes(8));
    $ok = $type === IMAGETYPE_JPEG ? imagejpeg($canvas, $temp, 86) : ($type === IMAGETYPE_PNG ? imagepng($canvas, $temp, 6) : imagewebp($canvas, $temp, 86));
    if (!$ok || !@rename($temp, $target)) {
        @unlink($temp);
        throw new ImageUploadError('No se ha podido guardar la imagen. Revisa los permisos de img/products.');
    }
    @chmod($target, 0644);
    return 'img/products/' . ADMIN_IMAGE_FOLDER . '/' . $name;
}

// Asigna la imagen a un perfume dentro de una transacción y lo anota en su historial.
function admin_images_assign(PDO $pdo, int $productId, string $path): void
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT imagen_principal FROM producto WHERE id=? FOR UPDATE');
        $st->execute([$productId]);
        $before = $st->fetch();
        if (!$before) { throw new ImageUploadError('Elige un perfume existente.'); }
        // Comprobar después de tomar el bloqueo: la imagen pudo borrarse mientras
        // esperábamos. El borrado de la biblioteca consulta estas mismas filas.
        if (!in_array($path, admin_catalog_images(), true)) { throw new ImageUploadError('La imagen no está en la biblioteca.'); }
        if ($before['imagen_principal'] !== $path) {
            $pdo->prepare('UPDATE producto SET imagen_principal=? WHERE id=?')->execute([$path, $productId]);
            $pdo->prepare('INSERT INTO catalogo_cambio(id_producto,accion,cambios) VALUES (?,?,?)')->execute([$productId, 'image',
                json_encode([['campo'=>'Perfume · Imagen', 'antes'=>$before['imagen_principal'], 'despues'=>$path]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

// Solo se borran subidas que ningún perfume utiliza; las imágenes del proyecto no se tocan.
function admin_images_delete(PDO $pdo, $path): void
{
    if (!is_string($path) || !in_array($path, admin_catalog_images(), true) || basename(dirname($path)) !== ADMIN_IMAGE_FOLDER) {
        throw new ImageUploadError('Solo se pueden eliminar imágenes subidas desde el panel.');
    }
    $pdo->beginTransaction();
    try {
        // El bloqueo de lectura evita asignarla a un perfume mientras se elimina.
        $st = $pdo->prepare('SELECT COUNT(*) FROM producto WHERE imagen_principal=? LOCK IN SHARE MODE');
        $st->execute([$path]);
        if ((int)$st->fetchColumn() > 0) { throw new ImageUploadError('La imagen está asignada a un perfume. Cámbiala en su ficha antes de eliminarla.'); }
        if (!@unlink(__DIR__ . '/../../public/' . $path)) { throw new ImageUploadError('No se ha podido eliminar el archivo.'); }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
