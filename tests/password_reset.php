<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
require_once __DIR__ . '/../tienda/src/lib/helpers.php';
require_once __DIR__ . '/../tienda/src/lib/mail.php';
$adminPassword = getenv('TIENDA_TEST_ADMIN_PASS') ?: '';
if ($adminPassword === '') { throw new RuntimeException('Define TIENDA_TEST_ADMIN_PASS'); }
$marker = 'recupera-' . bin2hex(random_bytes(4));
$email = $marker . '@example.invalid';
$oldPassword = 'frase-antigua-de-prueba-ficticia';
$newPassword = 'frase-nueva-de-prueba-ficticia-2026';
$customer = null; $category = null; $brand = null; $product = null;
$outbox = static function (string $to, string $type) use ($pdo): array {
    $st = $pdo->prepare('SELECT * FROM correo_saliente WHERE destinatario=? AND tipo=? ORDER BY id'); $st->execute([$to, $type]); return $st->fetchAll();
};
$tokenFrom = static function (array $mail): string { preg_match('/restablecer\.php\?token=([0-9a-f]{64})/', $mail['cuerpo'], $m); return $m[1] ?? ''; };
$requestReset = static function (TestBrowser $b, $address): string { $b->request('public/recuperar.php'); return $b->request('public/recuperar.php', ['email'=>$address]); };
$login = static function (TestBrowser $b, string $address, string $pass): void { $b->request('public/acceso.php'); $b->request('public/acceso.php', ['email'=>$address, 'password'=>$pass]); };
try {
    $pdo->exec('DELETE FROM acceso_intento');
    $pdo->prepare('INSERT INTO cliente(email,nombre) VALUES (?,?)')->execute([$email, 'Lucía <b>prueba</b>']);
    $customer = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO cliente_registrado(id_cliente,password_hash,direccion,cp,poblacion,pais) VALUES (?,?,?,?,?,?)')
        ->execute([$customer, password_hash($oldPassword, PASSWORD_DEFAULT), 'Calle de prueba', '00000', 'Prueba', 'Prueba']);

    // Bandeja: sin cabeceras inyectadas e idempotente por referencia.
    check(mail_queue($pdo, 'prueba', $marker, $email, "Asunto\r\nBcc: otro@example.invalid", 'Cuerpo') && !mail_queue($pdo, 'prueba', $marker, $email, 'Repetido', 'Cuerpo'), 'correo con referencia se registra una sola vez');
    check($outbox($email, 'prueba')[0]['asunto'] === 'Asunto Bcc: otro@example.invalid' && !mail_queue($pdo, 'prueba', null, "x@example.invalid\r\nBcc: y@example.invalid", 'A', 'B'), 'asunto sin saltos de linea y destinatario validado');

    $b = new TestBrowser();
    $html = $b->request('public/acceso.php');
    check(strpos($html, 'href="recuperar.php"') !== false, 'el acceso enlaza con la recuperacion');
    $b->request('public/recuperar.php', ['email'=>$email], false);
    check($b->status === 403 && !$outbox($email, 'recuperacion'), 'solicitud sin CSRF rechazada');
    foreach (['no-es-un-correo', ['x'], "a@example.invalid\n", str_repeat('a', 150) . '@example.invalid'] as $bad) {
        $requestReset($b, $bad);
        check($b->status === 422, 'correo invalido rechazado: ' . json_encode($bad));
    }
    $unknown = $requestReset($b, $marker . '-nadie@example.invalid');
    $known = $requestReset($b, $email);
    $sentText = static function (string $html): string { preg_match('~<p class="account-notice" role="status" data-reset-sent>(.*?)</p>~s', $html, $m); return $m[1] ?? ''; };
    check($b->status === 200 && $sentText($known) !== '' && $sentText($known) === $sentText($unknown) && !$outbox($marker . '-nadie@example.invalid', 'recuperacion'), 'misma respuesta exista o no la cuenta');
    $mails = $outbox($email, 'recuperacion');
    $first = $tokenFrom($mails[0] ?? ['cuerpo'=>'']);
    check(count($mails) === 1 && $first !== '' && strpos($mails[0]['cuerpo'], 'Lucía <b>prueba</b>') !== false, 'correo de recuperacion registrado con enlace y nombre');
    $st = $pdo->prepare('SELECT COUNT(*) FROM cliente_recuperacion WHERE token_hash=? AND expira BETWEEN UTC_TIMESTAMP() + INTERVAL 55 MINUTE AND UTC_TIMESTAMP() + INTERVAL 61 MINUTE');
    $st->execute([hash('sha256', $first)]);
    check((int)$st->fetchColumn() === 1 && (int)$pdo->query("SELECT COUNT(*) FROM cliente_recuperacion WHERE token_hash='" . $first . "'")->fetchColumn() === 0, 'solo se guarda el hash del enlace, con caducidad de 60 minutos');

    $requestReset($b, $email);
    $mails = $outbox($email, 'recuperacion');
    $token = $tokenFrom(end($mails));
    $b->request('public/restablecer.php?token=' . $first);
    check($b->status === 410 && $token !== $first, 'un enlace nuevo invalida el anterior');
    $html = $b->request('public/restablecer.php?token=' . $token);
    check($b->status === 200 && strpos($html, 'name="password2"') !== false && ($b->headers['referrer-policy'] ?? '') === 'no-referrer' && strpos($b->headers['cache-control'] ?? '', 'no-store') !== false, 'enlace valido muestra el formulario sin cache ni Referer');
    foreach (['abc', str_repeat('g', 64), bin2hex(random_bytes(32)), strtoupper($token)] as $bad) {
        $html = $b->request('public/restablecer.php?token=' . $bad);
        check($b->status === 410 && strpos($html, 'data-reset-invalid') !== false, 'enlace no valido responde 410');
    }
    $b->request('public/restablecer.php?token[]=' . $token);
    check($b->status === 410, 'token no escalar rechazado');
    $pdo->prepare('INSERT INTO cliente_recuperacion(token_hash,id_cliente,expira) VALUES (?,?,UTC_TIMESTAMP() - INTERVAL 1 MINUTE)')->execute([hash('sha256', str_repeat('a', 64)), $customer]);
    $b->request('public/restablecer.php?token=' . str_repeat('a', 64));
    check($b->status === 410, 'enlace caducado rechazado');

    // Sesión abierta con la contraseña antigua: deja de valer tras el cambio.
    $session = new TestBrowser();
    $login($session, $email, $oldPassword);
    check($session->status === 303, 'cliente accede con la contrasena antigua');
    $b->request('public/restablecer.php?token=' . $token);
    foreach ([['password'=>$newPassword, 'password2'=>$newPassword . 'x'], ['password'=>'corta', 'password2'=>'corta'], ['password'=>[$newPassword], 'password2'=>$newPassword], ['password'=>str_repeat('á', 37), 'password2'=>str_repeat('á', 37)]] as $bad) {
        $html = $b->request('public/restablecer.php', ['token'=>$token] + $bad);
        check($b->status === 422 && strpos($html, 'name="password2"') !== false, 'contrasena nueva no valida rechazada');
    }
    $b->request('public/restablecer.php', ['token'=>$token, 'password'=>$newPassword, 'password2'=>$newPassword], false);
    check($b->status === 403, 'cambio sin CSRF rechazado');
    $b->request('public/restablecer.php?token=' . $token);
    $b->request('public/restablecer.php', ['token'=>$token, 'password'=>$newPassword, 'password2'=>$newPassword]);
    check($b->status === 303 && ($b->headers['location'] ?? '') === 'acceso.php', 'contrasena cambiada redirige al acceso');
    check(strpos($b->request('public/acceso.php'), 'Contraseña actualizada') !== false, 'el acceso confirma el cambio');
    $session->request('public/cuenta.php');
    check($session->status === 302, 'el cambio cierra las sesiones abiertas con la contrasena antigua');
    $b->request('public/restablecer.php?token=' . $token);
    check($b->status === 410, 'el enlace no se puede reutilizar');
    $pdo->exec('DELETE FROM acceso_intento');
    $check = new TestBrowser(); $login($check, $email, $oldPassword);
    check($check->status === 401, 'la contrasena antigua deja de funcionar');
    $login($check, $email, $newPassword);
    check($check->status === 303, 'la contrasena nueva permite acceder');
    $st = $pdo->prepare('SELECT COUNT(*) FROM cliente_recuperacion WHERE id_cliente=? AND usado IS NULL'); $st->execute([$customer]);
    check((int)$st->fetchColumn() === 0, 'no quedan enlaces pendientes para la cuenta');

    $limited = new TestBrowser();
    for ($i = 0; $i < 4; ++$i) { $requestReset($limited, strtoupper($email)); }
    check($limited->status === 429 && ($limited->headers['retry-after'] ?? '') === '900', 'limite de solicitudes por correo');

    // Confirmación de pedido registrada en la bandeja.
    $pdo->prepare('INSERT INTO categoria(nombre,slug,activa) VALUES (?,?,1)')->execute(['Colección correo', $marker]);
    $category = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute(['Marca correo', $marker]);
    $brand = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,precio,stock,activo) VALUES (?,?,?,?,?,?,1)')->execute([$category, $brand, $marker . ' perfume', $marker, 20, 5]);
    $product = (int)$pdo->lastInsertId();
    $guest = $marker . '-invitado@example.invalid';
    $c = new TestBrowser();
    $c->request('public/catalogo.php');
    $c->request('public/carrito.php', ['action'=>'add', 'id_producto'=>$product, 'id_variante'=>'', 'cantidad'=>2]);
    $c->request('public/checkout.php', ['step'=>'form', 'tipo'=>'invitado', 'email'=>$guest, 'nombre'=>'Invitado <script>x</script>',
        'direccion'=>'Calle ficticia 1', 'cp'=>'28000', 'poblacion'=>'Madrid', 'pais'=>'España']);
    $html = $c->request('public/checkout.php', ['step'=>'confirm']);
    $order = $pdo->prepare('SELECT id FROM pedido WHERE email=?'); $order->execute([$guest]); $orderId = (int)$order->fetchColumn();
    $mails = $outbox($guest, 'pedido');
    check(strpos($html, 'Pedido realizado') !== false && count($mails) === 1 && $mails[0]['referencia'] === (string)$orderId && $mails[0]['asunto'] === 'Pedido #' . $orderId . ' registrado', 'confirmacion de pedido registrada una vez con su numero');
    check(strpos($mails[0]['cuerpo'], '× 2: 40,00 €') !== false && strpos($mails[0]['cuerpo'], 'Total: 48,40 €') !== false && strpos($mails[0]['cuerpo'], 'no supone ningún cobro') !== false, 'el correo resume lineas, total y aclara que no hay cobro');

    // Panel: bandeja privada con contenido escapado.
    $admin = new TestBrowser();
    $admin->request('admin/correos.php');
    check($admin->status === 302, 'bandeja requiere sesion administrativa');
    $admin->request('admin/login.php');
    $admin->request('admin/login.php', ['user'=>'admin', 'pass'=>$adminPassword]);
    $html = $admin->request('admin/correos.php?tipo=pedido');
    check($admin->status === 200 && strpos($html, 'data-mail="' . $mails[0]['id'] . '"') !== false && strpos($html, 'Recuperación de contraseña</td>') === false && strpos($html, 'aria-current="page">Correos') !== false, 'bandeja filtra por tipo');
    $html = $admin->request('admin/correos.php?id=' . $mails[0]['id']);
    check(strpos($html, 'Invitado &lt;script&gt;x&lt;/script&gt;') !== false && strpos($html, '<script>x') === false && strpos($html, 'sin enviar') !== false, 'detalle escapa el contenido e indica que no se envio');
    $admin->request('admin/correos.php?tipo=otro');
    check($admin->status === 422, 'tipo desconocido rechazado');
    foreach (['0', 'x', '999999999'] as $bad) { $admin->request('admin/correos.php?id=' . $bad); check($admin->status === 404, 'correo inexistente: ' . $bad); }
    echo "PASS $checks comprobaciones de correo y recuperacion\n";
} finally {
    $pdo->prepare("DELETE FROM correo_saliente WHERE destinatario LIKE ?")->execute([$marker . '%']);
    $ids = $pdo->prepare('SELECT id FROM pedido WHERE email LIKE ?'); $ids->execute([$marker . '%']);
    foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $id) { $pdo->prepare('DELETE FROM pedido WHERE id=?')->execute([$id]); }
    if ($product !== null) { $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$product]); }
    if ($brand !== null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
    if ($category !== null) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$category]); }
    if ($customer !== null) {
        $pdo->prepare('DELETE FROM cliente_registrado WHERE id_cliente=?')->execute([$customer]);
        $pdo->prepare('DELETE FROM cliente WHERE id=?')->execute([$customer]);
    }
    $pdo->exec('DELETE FROM acceso_intento');
}
