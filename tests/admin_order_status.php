<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
require_once __DIR__ . '/../tienda/src/lib/order_status.php';
$password = getenv('TIENDA_TEST_ADMIN_PASS') ?: '';
if ($password === '') { throw new RuntimeException('Define TIENDA_TEST_ADMIN_PASS'); }
$marker = 'estados-' . bin2hex(random_bytes(5));
$orders = []; $products = []; $category = null; $brand = null; $customState = 121; $stateCreated = false;
$stateOf = static function (int $id) use ($pdo): string {
    $st = $pdo->prepare('SELECT e.descripcion FROM pedido p JOIN estado_pedido e ON e.id=p.estado WHERE p.id=?');
    $st->execute([$id]); return (string)$st->fetchColumn();
};
$historyCount = static function (int $id) use ($pdo): int {
    $st = $pdo->prepare('SELECT COUNT(*) FROM pedido_estado_cambio WHERE id_pedido=?'); $st->execute([$id]); return (int)$st->fetchColumn();
};
$stocks = static function () use ($pdo, &$products): array {
    $a = $pdo->prepare('SELECT stock FROM producto WHERE id=?'); $a->execute([$products['simple']]);
    $v = $pdo->prepare('SELECT stock FROM producto_variante WHERE id=?'); $v->execute([$products['variant']]);
    return [(int)$a->fetchColumn(), (int)$v->fetchColumn()];
};
$stateId = static function (string $name) use ($pdo): int {
    $st = $pdo->prepare('SELECT id FROM estado_pedido WHERE descripcion=?'); $st->execute([$name]); return (int)$st->fetchColumn();
};
$newOrder = static function (string $state, array $lines) use ($pdo, $marker, $stateId, &$orders): int {
    $pdo->prepare('INSERT INTO pedido(email,nombre_envio,direccion_envio,cp_envio,poblacion_envio,pais_envio,total,estado) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$marker . '@example.invalid', $marker . ' Cliente', 'Calle de prueba', '00000', 'Prueba', 'Prueba', 100, $stateId($state)]);
    $id = (int)$pdo->lastInsertId(); $orders[] = $id;
    $line = $pdo->prepare('INSERT INTO linea_pedido(id_pedido,id_producto,id_variante,nombre_producto,capacidad_ml,unidades,precio_unitario,precio_neto) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($lines as [$product, $variant, $units]) { $line->execute([$id, $product, $variant, $marker . ' perfume', $variant ? 50 : null, $units, 10, 10 * $units]); }
    return $id;
};
try {
    $pdo->exec('DELETE FROM acceso_intento');
    check(order_status_label('pending') === 'Pendiente' && order_status_label('canceled') === 'Cancelado' && order_status_label('x') === 'En revisión', 'etiquetas en español sin exponer códigos internos');
    check(order_status_transitions('shipped') === [] && order_status_transitions('canceled') === [] && order_status_transitions('desconocido') === [], 'estados finales y desconocidos sin salidas');
    $pdo->prepare('INSERT INTO categoria(nombre,slug,activa) VALUES (?,?,1)')->execute(['Categoría de prueba', $marker]);
    $category = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute([$marker . ' marca', $marker]);
    $brand = (int)$pdo->lastInsertId();
    $insert = $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,precio,stock,activo) VALUES (?,?,?,?,?,?,1)');
    $insert->execute([$category, $brand, $marker . ' simple', $marker . '-simple', 10, 5]); $products['simple'] = (int)$pdo->lastInsertId();
    $insert->execute([$category, $brand, $marker . ' formatos', $marker . '-formatos', 10, 0]); $products['base'] = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO producto_variante(id_producto,capacidad_ml,sku,precio,stock,activo) VALUES (?,?,?,?,?,0)')
        ->execute([$products['base'], 50, $marker . '-SKU', 10, 7]);
    $products['variant'] = (int)$pdo->lastInsertId();
    // Dos líneas del mismo formato se reponen juntas; el formato inactivo también recupera sus unidades.
    $main = $newOrder('pending', [[$products['simple'], null, 2], [$products['base'], $products['variant'], 1], [$products['base'], $products['variant'], 2]]);
    $url = 'admin/pedido.php?id=' . $main;
    $anon = new TestBrowser();
    $anon->request($url);
    check($anon->status === 302 && ($anon->headers['location'] ?? '') === 'login.php', 'detalle y cambios exigen sesion administrativa');
    $anon->request($url, ['estado_actual'=>'pending', 'estado_nuevo'=>'paid'], false);
    check(in_array($anon->status, [302, 403], true) && $stateOf($main) === 'pending', 'peticion anonima no cambia el estado');
    $b = new TestBrowser();
    $b->request('admin/login.php');
    $b->request('admin/login.php', ['user'=>'admin', 'pass'=>$password]);
    check($b->status === 302, 'administrador accede');
    $html = $b->request($url);
    check($b->status === 200 && strpos($html, 'value="paid"') !== false && strpos($html, 'value="canceled"') !== false && strpos($html, 'value="shipped"') === false, 'pendiente ofrece solo pago registrado o cancelacion');
    check(strpos($html, '<li aria-current="step">Pendiente</li>') !== false && strpos($html, 'Las 5 unidades del pedido volverán al inventario') !== false, 'recorrido marca el paso actual y anuncia unidades a reponer');
    check(strpos($html, 'este prototipo no cobra ni verifica pagos') !== false, 'registro de pago aclara que no se cobra');
    check(strpos($b->headers['cache-control'] ?? '', 'no-store') !== false && ($b->headers['referrer-policy'] ?? '') === 'no-referrer', 'detalle privado sin cache ni Referer');
    $b->request($url, ['estado_actual'=>'pending', 'estado_nuevo'=>'paid'], false);
    check($b->status === 403 && $stateOf($main) === 'pending', 'sin token CSRF no se cambia');
    $invalid = [
        [['estado_actual'=>'pending', 'estado_nuevo'=>'shipped'], 422, 'salto de pendiente a enviado rechazado'],
        [['estado_actual'=>'pending', 'estado_nuevo'=>'processing'], 422, 'preparacion antes del pago rechazada'],
        [['estado_actual'=>'pending', 'estado_nuevo'=>['paid']], 422, 'estado no escalar rechazado'],
        [['estado_actual'=>'pending'], 422, 'estado ausente rechazado'],
        [['estado_actual'=>'pending', 'estado_nuevo'=>'paid', 'nota'=>['x']], 422, 'nota no escalar rechazada'],
        [['estado_actual'=>'pending', 'estado_nuevo'=>'paid', 'nota'=>str_repeat('a', 501)], 422, 'nota de 501 caracteres rechazada'],
        [['estado_actual'=>'pending', 'estado_nuevo'=>'paid', 'nota'=>"x\0y"], 422, 'nota con caracteres de control rechazada'],
        [['estado_actual'=>'pending', 'estado_nuevo'=>'paid', 'nota'=>"\xFF"], 422, 'nota con UTF-8 invalido rechazada'],
        [['estado_actual'=>'pending', 'estado_nuevo'=>'canceled'], 422, 'cancelacion sin confirmacion rechazada'],
        [['estado_actual'=>'pending', 'estado_nuevo'=>'canceled', 'confirmar'=>'si'], 422, 'confirmacion distinta de 1 rechazada'],
        [['estado_actual'=>'paid', 'estado_nuevo'=>'processing'], 409, 'formulario con estado antiguo detecta conflicto'],
        [['estado_actual'=>"pending' OR 1=1", 'estado_nuevo'=>'paid'], 422, 'estado actual con SQL rechazado'],
    ];
    foreach ($invalid as [$post, $status, $label]) {
        $html = $b->request($url, $post);
        check($b->status === $status && $stateOf($main) === 'pending' && $historyCount($main) === 0 && $stocks() === [5, 7], $label);
    }
    check(strpos($html, 'No se ha cambiado el estado.') !== false, 'error explicado junto al formulario');
    $html = $b->request($url, ['estado_actual'=>'pending', 'estado_nuevo'=>'paid', 'nota'=>str_repeat('a', 501)]);
    check(strpos($html, '>' . str_repeat('a', 501) . '</textarea>') !== false, 'nota rechazada se conserva para corregirla');
    $note = "Transferencia comprobada <script>alert(1)</script>\nlínea 2";
    $b->request($url . '&q=' . $marker . '&orden=antiguos', ['estado_actual'=>'pending', 'estado_nuevo'=>'paid', 'nota'=>$note]);
    $location = $b->headers['location'] ?? '';
    check($b->status === 303 && strpos($location, 'id=' . $main) !== false && strpos($location, 'q=' . $marker) !== false && strpos($location, 'orden=antiguos') !== false, 'cambio valido redirige conservando filtros del listado');
    check($stateOf($main) === 'paid' && $historyCount($main) === 1 && $stocks() === [5, 7], 'pago registrado sin tocar existencias');
    $html = $b->request('admin/' . $location);
    check(strpos($html, 'Estado actualizado: Pago registrado.') !== false && strpos($html, 'role="status"') !== false, 'confirmacion visible tras guardar');
    check(strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false && strpos($html, '<script>alert') === false, 'nota del historial escapada');
    check(strpos($html, 'Pendiente → Pago registrado') !== false && strpos($html, 'value="processing"') !== false, 'historial y siguiente paso en preparacion');
    $html = $b->request($url);
    check(strpos($html, 'Estado actualizado') === false, 'confirmacion se muestra una sola vez');
    $b->request($url, ['estado_actual'=>'pending', 'estado_nuevo'=>'paid', 'nota'=>$note]);
    check($b->status === 409 && $historyCount($main) === 1, 'reenvio del mismo formulario no duplica el cambio');
    $b->request($url, ['estado_actual'=>'paid', 'estado_nuevo'=>'processing', 'nota'=>str_repeat('ñ', 500)]);
    check($b->status === 303 && $stateOf($main) === 'processing', 'nota de 500 caracteres multibyte admitida');
    $st = $pdo->prepare('SELECT nota FROM pedido_estado_cambio WHERE id_pedido=? ORDER BY id DESC LIMIT 1'); $st->execute([$main]);
    check($st->fetchColumn() === str_repeat('ñ', 500), 'nota multibyte guardada sin recortes');
    // Dos administradores cancelan a la vez: el bloqueo del pedido deja pasar solo una cancelación.
    // Sesiones distintas: una sesión compartida se serializaría antes de llegar a la base de datos.
    $html = $b->request($url);
    $second = new TestBrowser();
    $second->request('admin/login.php');
    $second->request('admin/login.php', ['user'=>'admin', 'pass'=>$password]);
    $second->request($url);
    check($second->status === 200 && $second->token !== $b->token, 'segundo administrador con sesion independiente');
    $guard = new PDO(getenv('TIENDA_TEST_DSN'), getenv('TIENDA_TEST_USER') ?: 'root', getenv('TIENDA_TEST_PASS') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $observer = new PDO(getenv('TIENDA_TEST_DSN'), getenv('TIENDA_TEST_USER') ?: 'root', getenv('TIENDA_TEST_PASS') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $guard->beginTransaction();
    $guard->query('SELECT id FROM pedido WHERE id=' . $main . ' FOR UPDATE')->fetch();
    $multi = curl_multi_init(); $handles = [];
    foreach ([$b, $second] as $browser) {
        $handle = $browser->prepare($url, ['estado_actual'=>'processing', 'estado_nuevo'=>'canceled', 'confirmar'=>'1']);
        curl_multi_add_handle($multi, $handle); $handles[] = [$browser, $handle];
    }
    $deadline = microtime(true) + 10; $waiting = 0;
    do {
        curl_multi_exec($multi, $running); curl_multi_select($multi, 0.05);
        $waiting = (int)$observer->query("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE INFO LIKE 'SELECT p.id,p.estado,e.descripcion FROM pedido p%FOR UPDATE'")->fetchColumn();
    } while ($waiting < 2 && microtime(true) < $deadline);
    check($waiting === 2, 'dos cancelaciones esperan realmente el mismo bloqueo');
    $guard->commit();
    do { curl_multi_exec($multi, $running); curl_multi_select($multi, 0.05); } while ($running > 0);
    $statuses = [];
    foreach ($handles as [$browser, $handle]) { $browser->receive(curl_multi_getcontent($handle)); $statuses[] = $browser->status; curl_multi_remove_handle($multi, $handle); }
    sort($statuses);
    check($statuses === [303, 409], 'una cancelacion se aplica y la simultanea recibe conflicto');
    check($stateOf($main) === 'canceled' && $stocks() === [7, 10], 'existencias repuestas una sola vez, agrupando lineas del mismo formato');
    $st = $pdo->prepare('SELECT unidades_repuestas FROM pedido_estado_cambio WHERE id_pedido=? ORDER BY id DESC LIMIT 1'); $st->execute([$main]);
    check((int)$st->fetchColumn() === 5 && $historyCount($main) === 3, 'historial registra la cancelacion y sus unidades');
    $html = $b->request($url);
    check(strpos($html, 'Este pedido está cerrado') !== false && strpos($html, 'name="estado_nuevo"') === false && strpos($html, '5 unidades repuestas al inventario.') !== false, 'pedido cancelado cerrado y con historial visible');
    foreach (['paid', 'pending', 'canceled'] as $target) {
        $b->request($url, ['estado_actual'=>'canceled', 'estado_nuevo'=>$target, 'confirmar'=>'1']);
        check($b->status === 422 && $stateOf($main) === 'canceled' && $stocks() === [7, 10], 'pedido cancelado no se reabre ni repone de nuevo: ' . $target);
    }
    $shipped = $newOrder('processing', [[$products['simple'], null, 1]]);
    $b->request('admin/pedido.php?id=' . $shipped);
    $b->request('admin/pedido.php?id=' . $shipped, ['estado_actual'=>'processing', 'estado_nuevo'=>'shipped']);
    check($b->status === 303 && $stateOf($shipped) === 'shipped' && $stocks() === [7, 10], 'envio cierra el pedido sin modificar stock');
    $b->request('admin/pedido.php?id=' . $shipped, ['estado_actual'=>'shipped', 'estado_nuevo'=>'canceled', 'confirmar'=>'1']);
    check($b->status === 422 && $stateOf($shipped) === 'shipped' && $stocks() === [7, 10], 'pedido enviado no se cancela ni repone');
    $pdo->prepare('INSERT INTO estado_pedido(id,descripcion) VALUES (?,?)')->execute([$customState, 'custom-estados']);
    $stateCreated = true;
    $custom = $newOrder('custom-estados', [[$products['simple'], null, 1]]);
    $html = $b->request('admin/pedido.php?id=' . $custom);
    check(strpos($html, 'no forma parte del flujo conocido') !== false && strpos($html, 'name="estado_nuevo"') === false, 'estado desconocido sin acciones inventadas');
    $b->request('admin/pedido.php?id=' . $custom, ['estado_actual'=>'custom', 'estado_nuevo'=>'canceled', 'confirmar'=>'1']);
    check($b->status === 409 && $stateOf($custom) === 'custom-estados' && $stocks() === [7, 10], 'estado desconocido no se modifica');
    $b->request('admin/pedido.php?id=999999999', ['estado_actual'=>'pending', 'estado_nuevo'=>'paid']);
    check($b->status === 404, 'pedido inexistente responde 404');
    foreach (['0', '-1', '1.5', 'x', '2147483648'] as $bad) {
        $b->request('admin/pedido.php?id=' . rawurlencode($bad));
        check($b->status === 404, 'identificador invalido responde 404: ' . $bad);
    }
    $curl = $b->prepare($url); curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT'); $b->receive(curl_exec($curl)); curl_setopt($curl, CURLOPT_CUSTOMREQUEST, null);
    check($b->status === 405 && ($b->headers['allow'] ?? '') === 'GET, POST', 'metodos no previstos rechazados');
    $count = $pdo->prepare('SELECT COUNT(*) FROM pedido WHERE email=?'); $count->execute([$marker . '@example.invalid']);
    check((int)$count->fetchColumn() === 3, 'entradas hostiles no crean ni borran pedidos');
    echo "PASS $checks comprobaciones de estados de pedido\n";
} finally {
    foreach ($orders as $id) { $pdo->prepare('DELETE FROM pedido WHERE id=?')->execute([$id]); }
    if (isset($products['variant'])) { $pdo->prepare('DELETE FROM producto_variante WHERE id=?')->execute([$products['variant']]); }
    foreach (['simple', 'base'] as $key) { if (isset($products[$key])) { $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$products[$key]]); } }
    if ($brand !== null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
    if ($category !== null) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$category]); }
    if ($stateCreated) { $pdo->prepare('DELETE FROM estado_pedido WHERE id=?')->execute([$customState]); }
    $pdo->exec('DELETE FROM acceso_intento');
}
