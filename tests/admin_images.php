<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
$password = getenv('TIENDA_TEST_ADMIN_PASS') ?: '';
if ($password === '') { throw new RuntimeException('Define TIENDA_TEST_ADMIN_PASS'); }
if (!function_exists('imagecreatetruecolor')) { throw new RuntimeException('Las pruebas de imágenes necesitan la extensión GD'); }
$marker = 'imagenes-' . bin2hex(random_bytes(4));
$folder = realpath(__DIR__ . '/../tienda/public/img/products') . '/Subidas';
$listing = static fn(): array => is_dir($folder) ? array_values(array_diff(scandir($folder), ['.', '..', '.htaccess'])) : [];
$initial = $listing();
$work = sys_get_temp_dir() . '/' . $marker; mkdir($work, 0700);
$category = null; $brand = null; $product = null;
$make = static function (string $name, int $w, int $h, string $type) use ($work): string {
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 180, 120, 60));
    imagefilledrectangle($img, 0, 0, intdiv($w, 2), intdiv($h, 2), imagecolorallocate($img, 20, 40, 90));
    $path = $work . '/' . $name;
    $type === 'png' ? imagepng($img, $path) : ($type === 'gif' ? imagegif($img, $path) : imagejpeg($img, $path, 40));
    return $path;
};
$upload = static function (TestBrowser $b, array $fields, $file, string $key = 'imagen'): string {
    $curl = $b->prepare('admin/imagenes.php', ['x'=>'1']);
    $post = $fields + ['action'=>'upload', 'csrf_token'=>$b->token];
    if ($file !== null) { $post[$key] = new CURLFile($file, 'application/octet-stream', basename($file)); }
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    return $b->receive(curl_exec($curl));
};
$newFiles = static fn(array $before): array => array_values(array_diff($listing(), $before));
try {
    $pdo->exec('DELETE FROM acceso_intento');
    $anon = new TestBrowser();
    $anon->request('admin/imagenes.php');
    check($anon->status === 302 && ($anon->headers['location'] ?? '') === 'login.php', 'biblioteca requiere sesion administrativa');
    $b = new TestBrowser();
    $b->request('admin/login.php');
    $b->request('admin/login.php', ['user'=>'admin', 'pass'=>$password]);
    $html = $b->request('admin/imagenes.php');
    $projectImages = count(glob(realpath(__DIR__ . '/../tienda/public') . '/img/products/*/*.{jpg,jpeg,png,webp}', GLOB_BRACE));
    check($b->status === 200 && strpos($html, 'Biblioteca (' . $projectImages . ')') !== false && strpos($html, 'enctype="multipart/form-data"') !== false, 'biblioteca lista todas las imagenes y ofrece la subida');
    check(strpos($html, 'aria-current="page">Imágenes') !== false && strpos($b->headers['cache-control'] ?? '', 'no-store') !== false, 'seccion marcada y privada');

    // Subida válida: nombre limpio, recodificada y sin contenido añadido.
    $before = $listing();
    $payload = $make('payload.jpg', 400, 300, 'jpg');
    file_put_contents($payload, "<?php echo 'ejecutado'; ?> GPSLatitude", FILE_APPEND);
    $upload($b, ['nombre'=>'Noir Ábsolu!! Frasco'], $payload);
    $created = $newFiles($before);
    check($b->status === 303 && count($created) === 1 && preg_match('/\Anoir-absolu-frasco-[0-9a-f]{8}\.jpg\z/', $created[0]) === 1, 'subida valida crea un archivo con nombre limpio y aleatorio');
    $stored = file_get_contents($folder . '/' . $created[0]);
    check(strpos($stored, '<?php') === false && strpos($stored, 'GPSLatitude') === false && getimagesize($folder . '/' . $created[0])[0] === 400, 'imagen recodificada sin codigo ni metadatos anadidos');
    $html = $b->request('admin/imagenes.php');
    check(strpos($html, 'Imagen guardada. Ya puedes elegirla') !== false && strpos($html, 'is-new" data-image="img/products/Subidas/' . $created[0]) !== false, 'confirmacion y nueva imagen destacada en la biblioteca');
    $public = new TestBrowser(); $public->request('public/img/products/Subidas/' . $created[0]);
    check($public->status === 200 && strpos($public->headers['content-type'] ?? '', 'image/jpeg') === 0, 'imagen subida se sirve como imagen');

    $before = $listing();
    $upload($b, [], $make('grande.jpg', 3000, 2000, 'jpg'));
    $big = $newFiles($before);
    $size = getimagesize($folder . '/' . $big[0]);
    check($b->status === 303 && $size[0] === 1600 && $size[1] === 1067 && str_starts_with($big[0], 'imagen-'), 'imagen grande se reduce manteniendo proporcion');
    $before = $listing();
    $upload($b, [], $make('transparente.png', 300, 300, 'png'));
    $png = $newFiles($before);
    check($b->status === 303 && count($png) === 1 && str_ends_with($png[0], '.png'), 'PNG conserva su formato');

    // Archivos rechazados sin dejar nada en la carpeta.
    file_put_contents($work . '/texto.jpg', 'no soy una imagen');
    file_put_contents($work . '/vector.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"><script>alert(1)</script></svg>');
    file_put_contents($work . '/enorme.jpg', random_bytes(6 * 1024 * 1024));
    $before = $listing();
    foreach ([
        ['texto.jpg con extension falsa', [], $work . '/texto.jpg'],
        ['SVG', [], $work . '/vector.svg'],
        ['GIF', [], $make('animada.gif', 300, 300, 'gif')],
        ['imagen menor de 200 px', [], $make('mini.png', 199, 400, 'png')],
        ['lado mayor de 6000 px', [], $make('ancha.png', 6001, 200, 'png')],
        ['archivo de 6 MB', [], $work . '/enorme.jpg'],
        ['sin archivo', [], null],
        ['nombre de 81 caracteres', ['nombre'=>str_repeat('n', 81)], $payload],
        ['nombre no escalar', ['nombre[]'=>'x'], $payload],
        ['perfume inexistente', ['id_producto'=>'2147483646'], $payload],
        ['perfume no numerico', ['id_producto'=>'abc'], $payload],
    ] as [$label, $fields, $file]) {
        $html = $upload($b, $fields, $file);
        check($b->status === 422 && $listing() === $before && strpos($html, 'No se ha completado.') !== false, 'rechaza ' . $label . ' sin dejar archivos');
    }
    $upload($b, [], $payload, 'imagen[0]');
    check($b->status === 422 && $listing() === $before, 'rechaza varios archivos en un mismo campo');
    $curl = $b->prepare('admin/imagenes.php', ['x'=>'1']);
    curl_setopt($curl, CURLOPT_POSTFIELDS, ['action'=>'upload', 'imagen'=>new CURLFile($payload)]);
    $b->receive(curl_exec($curl));
    check($b->status === 403 && $listing() === $before, 'subida sin CSRF rechazada');
    file_put_contents($work . '/envio.bin', random_bytes(9 * 1024 * 1024));
    $curl = $b->prepare('admin/imagenes.php', ['x'=>'1']);
    curl_setopt($curl, CURLOPT_POSTFIELDS, ['action'=>'upload', 'csrf_token'=>$b->token, 'imagen'=>new CURLFile($work . '/envio.bin')]);
    $body = $b->receive(curl_exec($curl));
    check($b->status === 413 && strpos($body, 'tamaño máximo') !== false && $listing() === $before, 'envio mayor que post_max_size se explica con 413');
    $b->request('admin/imagenes.php');

    // Asignación a un perfume, con historial y visible en la tienda.
    $pdo->prepare('INSERT INTO categoria(nombre,slug,activa) VALUES (?,?,1)')->execute(['Colección imágenes', $marker]);
    $category = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute(['Marca imágenes', $marker]);
    $brand = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,precio,stock,activo) VALUES (?,?,?,?,?,?,1)')->execute([$category, $brand, $marker . ' perfume', $marker, 25, 3]);
    $product = (int)$pdo->lastInsertId();
    $before = $listing();
    $upload($b, ['nombre'=>'Frasco asignado', 'id_producto'=>(string)$product], $payload);
    $assigned = $newFiles($before);
    $path = 'img/products/Subidas/' . ($assigned[0] ?? 'x');
    $st = $pdo->prepare('SELECT imagen_principal FROM producto WHERE id=?'); $st->execute([$product]);
    check($b->status === 303 && $st->fetchColumn() === $path, 'subida asignada actualiza la imagen del perfume');
    $st = $pdo->prepare('SELECT accion,cambios FROM catalogo_cambio WHERE id_producto=? ORDER BY id DESC LIMIT 1'); $st->execute([$product]);
    $history = $st->fetch();
    check($history['accion'] === 'image' && json_decode($history['cambios'], true)[0]['despues'] === $path, 'asignacion queda en el historial del perfume');
    $html = $b->request('admin/producto.php?id=' . $product);
    check(strpos($html, 'Imagen asignada') !== false && strpos($html, 'value="' . $path . '" selected') !== false, 'ficha administrativa muestra la imagen y el historial');
    $html = (new TestBrowser())->request('public/producto.php?p=' . $marker);
    check(strpos($html, 'src="' . $path . '"') !== false, 'la tienda muestra la imagen asignada');
    check(strpos($b->request('admin/imagenes.php'), 'en 1 perfume') !== false, 'biblioteca indica cuantos perfumes usan la imagen');

    // Borrado: solo subidas sin uso; nunca imágenes del proyecto ni rutas fuera de la carpeta.
    $delete = static function (TestBrowser $b, $route): void { $b->request('admin/imagenes.php'); $b->request('admin/imagenes.php', ['action'=>'delete', 'ruta'=>$route]); };
    $delete($b, $path);
    check($b->status === 409 && is_file($folder . '/' . $assigned[0]), 'imagen en uso no se elimina');
    $seed = glob(realpath(__DIR__ . '/../tienda/public') . '/img/products/Hombre/*.jpg')[0];
    foreach (['img/products/Hombre/' . basename($seed), 'img/products/Subidas/../Hombre/' . basename($seed), '../config/config.php', 'img/products/Subidas/.htaccess', ['x']] as $route) {
        $delete($b, $route);
        check($b->status === 422 && is_file($seed) && is_file($folder . '/.htaccess'), 'ruta no eliminable rechazada: ' . json_encode($route));
    }
    $delete($b, 'img/products/Subidas/' . $png[0]);
    check($b->status === 303 && !is_file($folder . '/' . $png[0]), 'subida sin uso se elimina');
    $b->request('admin/imagenes.php');
    $b->request('admin/imagenes.php', ['action'=>'otra']);
    check($b->status === 422, 'accion desconocida rechazada');
    $curl = $b->prepare('admin/imagenes.php'); curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
    $b->receive(curl_exec($curl)); curl_setopt($curl, CURLOPT_CUSTOMREQUEST, null);
    check($b->status === 405, 'metodos no previstos rechazados');
    echo "PASS $checks comprobaciones de imagenes\n";
} finally {
    if ($product !== null) {
        $pdo->prepare('DELETE FROM catalogo_cambio WHERE id_producto=?')->execute([$product]);
        $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$product]);
    }
    if ($brand !== null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
    if ($category !== null) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$category]); }
    foreach (array_diff($listing(), $initial) as $file) { @unlink($folder . '/' . $file); }
    foreach (glob($work . '/*') as $file) { unlink($file); } rmdir($work);
    $pdo->exec('DELETE FROM acceso_intento');
}
