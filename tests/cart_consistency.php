<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';

function cartConsistencyConnection(): PDO {
    global $dsn;
    return new PDO($dsn, getenv('TIENDA_TEST_USER') ?: 'root', getenv('TIENDA_TEST_PASS') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function cartConsistencyRace(array $browsers, array $requests, int $barrierCustomer): array {
    $multi = curl_multi_init();
    $handles = [];
    $activeHandles = [];
    $guard = null;
    $observer = null;
    $waiting = 0;
    try {
        if ($barrierCustomer !== null) {
            $guard = cartConsistencyConnection();
            $guard->beginTransaction();
            $guard->query('SELECT id_cliente FROM cliente_registrado WHERE id_cliente=' . $barrierCustomer . ' FOR UPDATE')->fetch();
            $observer = cartConsistencyConnection();
            $lockedTable = '`' . $observer->query('SELECT DATABASE()')->fetchColumn() . '`.`cliente_registrado`';
        }
        foreach ($browsers as $i => $browser) {
            $handle = $browser->prepare($requests[$i][0], $requests[$i][1]);
            $handles[] = $handle;
            if ($barrierCustomer === null || count($handles) === 1) {
                curl_multi_add_handle($multi, $handle);
                $activeHandles[] = $handle;
            }
        }
        $deadline = microtime(true) + 15;
        $barrierDeadline = microtime(true) + 8;
        $nextObservation = 0.0;
        do {
            $result = curl_multi_exec($multi, $running);
            if ($result !== CURLM_OK) { throw new RuntimeException(curl_multi_strerror($result)); }
            if ($guard !== null && $guard->inTransaction() && microtime(true) >= $nextObservation) {
                // InnoDB conserva el snapshot si lo leemos cada menos de 100 ms.
                // Separar las observaciones permite que publique los nuevos bloqueos.
                $nextObservation = microtime(true) + 0.25;
                // Dos transacciones deben esperar el mismo registro, incluso si el motor
                // encadena el segundo comprador detrás del primero en la cola de bloqueo.
                // Un servidor que atienda secuencialmente NO puede superar esta condición.
                $waiting = (int)$observer->query(
                    'SELECT COUNT(DISTINCT w.requesting_trx_id)
                     FROM information_schema.INNODB_LOCK_WAITS w
                     JOIN information_schema.INNODB_LOCKS l ON l.lock_id=w.requested_lock_id
                     WHERE l.lock_table=' . $observer->quote($lockedTable) .
                     " AND l.lock_index='PRIMARY' AND l.lock_data=" . $observer->quote((string)$barrierCustomer)
                )->fetchColumn();
                if ($waiting >= count($activeHandles) && count($activeHandles) < count($handles)) {
                    // Arrancar la segunda petición cuando el primer worker ya está
                    // bloqueado evita que acepte y encole ambas conexiones él solo.
                    $next = $handles[count($activeHandles)];
                    curl_multi_add_handle($multi, $next);
                    $activeHandles[] = $next;
                } elseif ($waiting >= count($browsers)) {
                    $guard->commit();
                } elseif (microtime(true) > $barrierDeadline) {
                    throw new RuntimeException('No hubo dos transacciones simultáneas (observadas: ' . $waiting . '). Usa al menos dos workers PHP y una cuenta de pruebas con PROCESS.');
                }
            }
            if (microtime(true) > $deadline) { throw new RuntimeException('Tiempo de espera de concurrencia agotado'); }
            if ($running && curl_multi_select($multi, 0.05) === -1) { usleep(10000); }
        } while ($running);
        if ($barrierCustomer !== null) {
            check($waiting >= count($browsers), 'dos transacciones HTTP realmente solapadas');
        }
        $bodies = [];
        foreach ($browsers as $index => $browser) {
            $bodies[] = $browser->receive(curl_multi_getcontent($handles[$index]));
        }
        return $bodies;
    } finally {
        if ($guard !== null && $guard->inTransaction()) { $guard->rollBack(); }
        // Drenar las peticiones antes de limpiar datos, incluso si falla la barrera.
        $drainUntil = microtime(true) + 21;
        do {
            curl_multi_exec($multi, $running);
            if ($running && curl_multi_select($multi, 0.05) === -1) { usleep(10000); }
        } while ($running && microtime(true) < $drainUntil);
        foreach ($activeHandles as $handle) { curl_multi_remove_handle($multi, $handle); }
        curl_multi_close($multi);
    }
}

$marker = 'consistency-' . bin2hex(random_bytes(5));
$email = $marker . '@example.invalid';
$password = 'frase-ficticia-para-consistencia';
$customer = $category = $brand = null; $products = []; $trigger = false;
$login = static function () use ($email, $password): TestBrowser {
    $b = new TestBrowser(); $b->request('public/acceso.php');
    $b->request('public/acceso.php', ['email'=>$email, 'password'=>$password]);
    if ($b->status !== 303) { throw new RuntimeException('No se pudo preparar la cuenta'); }
    $b->request('public/catalogo.php');
    return $b;
};
$add = static function (TestBrowser $b, int $id): void {
    $b->request('public/carrito.php', ['action'=>'add', 'id_producto'=>$id, 'cantidad'=>1]);
};
$form = ['step'=>'form', 'tipo'=>'registrado', 'email'=>$email, 'nombre'=>'Cliente de prueba',
    'direccion'=>'Calle ficticia 1', 'cp'=>'28000', 'poblacion'=>'Madrid', 'pais'=>'España'];
$version = static function (string $html): string {
    preg_match('/name="cart_version" value="([a-f0-9]{64})"/', $html, $m); return $m[1] ?? '';
};
try {
    $pdo->exec('DELETE FROM acceso_intento');
    $pdo->prepare('INSERT INTO cliente(email,nombre) VALUES (?,?)')->execute([$email, 'Cliente de prueba']);
    $customer = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO cliente_registrado(id_cliente,password_hash,direccion,cp,poblacion,pais) VALUES (?,?,?,?,?,?)')
        ->execute([$customer, password_hash($password, PASSWORD_DEFAULT), 'Calle ficticia 1', '28000', 'Madrid', 'España']);
    $pdo->prepare('INSERT INTO categoria(nombre,slug,activa) VALUES (?,?,1)')->execute(['Consistencia', $marker]);
    $category = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute(['Marca de prueba', $marker]);
    $brand = (int)$pdo->lastInsertId();
    foreach (['a', 'b'] as $suffix) {
        $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,precio,stock,activo) VALUES (?,?,?,?,20,50,1)')
            ->execute([$category, $brand, $marker . '-' . $suffix, $marker . '-' . $suffix]);
        $products[] = (int)$pdo->lastInsertId();
    }
    $saved = static function () use ($pdo, $customer): array {
        $st = $pdo->prepare('SELECT clave,cantidad FROM cliente_carrito WHERE id_cliente=? ORDER BY clave');
        $st->execute([$customer]); return array_map('intval', $st->fetchAll(PDO::FETCH_KEY_PAIR));
    };
    $orders = static function () use ($pdo, $email): int {
        $st = $pdo->prepare('SELECT COUNT(*) FROM pedido WHERE email=?'); $st->execute([$email]); return (int)$st->fetchColumn();
    };
    $stock = static fn(): int => (int)$pdo->query('SELECT stock FROM producto WHERE id=' . $products[0])->fetchColumn();
    $a = $login(); $b = $login();
    $add($a, $products[0]);
    $html = $b->request('public/checkout.php');
    check($b->status === 200 && str_contains($html, $marker . '-a') && str_contains($html, 'Continuar al resumen'), 'checkout directo recupera la cuenta aunque el navegador tuviera carrito vacio');
    $html = $a->request('public/carrito.php'); $oldVersion = $version($html);
    $key = 'p' . $products[0];
    $b->request('public/carrito.php', ['action'=>'update', 'cantidad'=>[$key=>2]]);
    $a->request('public/carrito.php', ['action'=>'update', 'cantidad'=>[$key=>3], 'cart_version'=>$oldVersion]);
    check($a->status === 409 && $saved() === [$key=>2], 'un formulario antiguo no sobrescribe cantidades de otro dispositivo');
    $a->request('public/checkout.php', $form);
    $b->request('public/carrito.php', ['action'=>'update', 'cantidad'=>[$key=>3]]);
    $before = $orders(); $stockBefore = $stock();
    $a->request('public/checkout.php', ['step'=>'confirm']);
    check($a->status === 409 && $orders() === $before && $stock() === $stockBefore && $saved() === [$key=>3], 'resumen antiguo no compra ni borra un carrito modificado');

    $a->request('public/checkout.php', $form); $b->request('public/checkout.php', $form);
    $html = $a->request('public/checkout.php', ['step'=>'confirm']);
    check($a->status === 200 && str_contains($html, 'Pedido realizado') && $saved() === [], 'la compra vacia el carrito dentro de su transaccion');
    $b->request('public/checkout.php', ['step'=>'confirm']);
    check($b->status === 409 && $orders() === $before + 1 && $stock() === $stockBefore - 3, 'otro dispositivo no repite un pedido ya confirmado');

    $add($a, $products[0]); $a->request('public/checkout.php', $form);
    $before = $orders(); $stockBefore = $stock();
    $pdo->exec("CREATE TRIGGER qa_cart_clear_failure BEFORE DELETE ON cliente_carrito FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture cart failure'"); $trigger = true;
    $a->request('public/checkout.php', ['step'=>'confirm']);
    check($a->status === 503 && $orders() === $before && $stock() === $stockBefore && $saved() === [$key=>1], 'si falla el vaciado se revierten pedido y stock y se conserva el carrito');
    $pdo->exec('DROP TRIGGER qa_cart_clear_failure'); $trigger = false;

    // Dos escritores parten de la misma lectura, bloqueados de verdad a la vez.
    $a->request('public/carrito.php'); $b->request('public/carrito.php');
    cartConsistencyRace([$a,$b], [['public/carrito.php', ['action'=>'update','cantidad'=>[$key=>2]]],
        ['public/carrito.php', ['action'=>'add','id_producto'=>$products[1],'cantidad'=>1]]], $customer);
    $statuses = [$a->status,$b->status]; sort($statuses);
    check($statuses === [303,409], 'dos cambios solapados producen un guardado y un conflicto visible');
    check($saved() === [$key=>2] || $saved() === [$key=>1,'p' . $products[1]=>1], 'el cambio rechazado no sobrescribe el carrito confirmado');

    // Dos confirmaciones de la misma cuenta, con sesiones independientes.
    $a->request('public/checkout.php', $form); $b->request('public/checkout.php', $form);
    $before = $orders();
    cartConsistencyRace([$a,$b], [['public/checkout.php',['step'=>'confirm']], ['public/checkout.php',['step'=>'confirm']]], $customer);
    $statuses = [$a->status,$b->status]; sort($statuses);
    check($statuses === [200,409] && $orders() === $before + 1 && $saved() === [], 'confirmaciones simultaneas registran un unico pedido');
    echo "PASS $checks comprobaciones de consistencia de carrito y compra\n";
} finally {
    if ($trigger) { $pdo->exec('DROP TRIGGER qa_cart_clear_failure'); }
    $pdo->prepare('DELETE FROM correo_saliente WHERE destinatario=?')->execute([$email]);
    $pdo->prepare('DELETE FROM pedido WHERE email=?')->execute([$email]);
    if ($customer !== null) {
        $pdo->prepare('DELETE FROM cliente_registrado WHERE id_cliente=?')->execute([$customer]);
        $pdo->prepare('DELETE FROM cliente WHERE id=?')->execute([$customer]);
    }
    foreach ($products as $id) { $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$id]); }
    if ($brand !== null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
    if ($category !== null) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$category]); }
    $pdo->exec('DELETE FROM acceso_intento');
}
