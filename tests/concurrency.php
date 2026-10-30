<?php
declare(strict_types=1);

require_once __DIR__ . '/support.php';

// MariaDB: el coordinador necesita PROCESS para observar los bloqueos reales.
// La aplicación sigue utilizando su cuenta limitada, nunca esta conexión.
function raceConnection(): PDO {
    global $dsn;
    return new PDO($dsn, getenv('TIENDA_TEST_USER') ?: 'root', getenv('TIENDA_TEST_PASS') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function raceBrowser(array $cart, string $email): TestBrowser {
    global $pdo;
    $browser = new TestBrowser();
    $product = $pdo->prepare('SELECT slug FROM producto WHERE id=?');
    $product->execute([$cart[0]['id_producto']]);
    $browser->request('public/producto.php?p=' . urlencode((string)$product->fetchColumn()));
    foreach ($cart as $item) {
        $browser->request('public/carrito.php', $item + ['action' => 'add', 'cantidad' => 1]);
        if ($browser->status !== 303) {
            throw new RuntimeException('No se pudo preparar el carrito: HTTP ' . $browser->status);
        }
    }
    $body = $browser->request('public/checkout.php', [
        'step' => 'form', 'tipo' => 'invitado', 'email' => $email, 'nombre' => 'Prueba concurrente',
        'direccion' => 'Calle ficticia 1', 'cp' => '28000', 'poblacion' => 'Madrid', 'pais' => 'España',
    ]);
    if (strpos($body, 'Confirmar pedido') === false) {
        throw new RuntimeException('No se pudo preparar el resumen');
    }
    return $browser;
}

function raceConfirm(array $browsers, ?int $barrierProduct = null): array {
    $multi = curl_multi_init();
    $handles = [];
    $guard = null;
    $observer = null;
    $waiting = 0;
    try {
        if ($barrierProduct !== null) {
            $guard = raceConnection();
            $guardId = (int)$guard->query('SELECT CONNECTION_ID()')->fetchColumn();
            $guard->beginTransaction();
            $guard->query('SELECT id FROM producto WHERE id=' . $barrierProduct . ' FOR UPDATE')->fetch();
            $observer = raceConnection();
        }
        foreach ($browsers as $browser) {
            $handle = $browser->prepare('public/checkout.php', ['step' => 'confirm']);
            $handles[] = $handle;
            curl_multi_add_handle($multi, $handle);
        }
        $deadline = microtime(true) + 15;
        $barrierDeadline = microtime(true) + 8;
        do {
            $result = curl_multi_exec($multi, $running);
            if ($result !== CURLM_OK) { throw new RuntimeException(curl_multi_strerror($result)); }
            if ($guard !== null && $guard->inTransaction()) {
                // Dos transacciones diferentes deben estar bloqueadas por nuestra conexión.
                // Un servidor que atienda secuencialmente NO puede superar esta condición.
                $waiting = (int)$observer->query(
                    'SELECT COUNT(DISTINCT w.requesting_trx_id)
                     FROM information_schema.INNODB_LOCK_WAITS w
                     JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id
                     WHERE b.trx_mysql_thread_id=' . $guardId
                )->fetchColumn();
                if ($waiting >= count($browsers)) {
                    $guard->commit();
                } elseif (microtime(true) > $barrierDeadline) {
                    throw new RuntimeException('No hubo dos transacciones simultáneas. Usa al menos dos workers PHP y una cuenta de pruebas con PROCESS.');
                }
            }
            if (microtime(true) > $deadline) { throw new RuntimeException('Tiempo de espera de concurrencia agotado'); }
            if ($running && curl_multi_select($multi, 0.05) === -1) { usleep(10000); }
        } while ($running);
        if ($barrierProduct !== null) {
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
        foreach ($handles as $handle) { curl_multi_remove_handle($multi, $handle); }
        curl_multi_close($multi);
    }
}

$prefix = 'qa-race-' . bin2hex(random_bytes(6));
$variants = $pdo->query(
    'SELECT v.id, v.id_producto, v.stock FROM producto_variante v
     JOIN producto p ON p.id=v.id_producto WHERE p.activo=1 AND v.activo=1 ORDER BY p.id,v.id'
)->fetchAll();
$selected = [];
foreach ($variants as $variant) {
    $selected[(int)$variant['id_producto']] = $selected[(int)$variant['id_producto']] ?? $variant;
    if (count($selected) === 2) { break; }
}
if (count($selected) !== 2) { throw new RuntimeException('Se necesitan variantes de dos productos distintos'); }
$selected = array_values($selected);
$syntheticId = null;
$emails = [];
try {
    // Fixture propio para cubrir también el stock de productos sin variantes.
    $insert = $pdo->prepare(
        'INSERT INTO producto(id_categoria,id_marca,nombre,slug,precio,stock,activo)
         SELECT id_categoria,id_marca,?,?,10,1,1 FROM producto WHERE id=?'
    );
    $insert->execute(['Producto temporal de concurrencia', $prefix, $selected[0]['id_producto']]);
    $syntheticId = (int)$pdo->lastInsertId();
    $a = ['id_producto' => (int)$selected[0]['id_producto'], 'id_variante' => (int)$selected[0]['id']];
    $b = ['id_producto' => (int)$selected[1]['id_producto'], 'id_variante' => (int)$selected[1]['id']];
    $scenarios = [
        'ultima-variante' => [[$a], 1, 1],
        'ultimo-sin-variante' => [[['id_producto' => $syntheticId]], 1, 1],
        'carritos-invertidos-stock-uno' => [[$a, $b], 1, 1],
        'carritos-invertidos-stock-dos' => [[$a, $b], 2, 2],
    ];
    foreach ($scenarios as $name => [$cart, $available, $expected]) {
        echo "Escenario: $name\n";
        foreach ($cart as $item) {
            $table = isset($item['id_variante']) ? 'producto_variante' : 'producto';
            $id = $item['id_variante'] ?? $item['id_producto'];
            $pdo->exec("UPDATE $table SET stock=$available WHERE id=$id");
        }
        $caseEmails = [$prefix . '-' . $name . '-a@example.invalid', $prefix . '-' . $name . '-b@example.invalid'];
        $emails = array_merge($emails, $caseEmails);
        $browsers = [raceBrowser($cart, $caseEmails[0]), raceBrowser(array_reverse($cart), $caseEmails[1])];
        check($browsers[0]->token !== $browsers[1]->token, 'compradores con sesiones independientes');
        $bodies = raceConfirm($browsers, min(array_column($cart, 'id_producto')));
        $successes = count(array_filter($bodies, function (string $body): bool {
            return strpos($body, 'Pedido realizado') !== false;
        }));
        $statuses = array_map(function (TestBrowser $browser): int { return $browser->status; }, $browsers);
        sort($statuses);
        check($successes === $expected && $statuses === ($expected === 1 ? [200, 409] : [200, 200]),
            'resultado HTTP coherente: ' . $expected . ' pedido(s) y rechazo claro al agotar stock');
        $orders = $pdo->prepare('SELECT id FROM pedido WHERE email IN (?,?)');
        $orders->execute($caseEmails);
        $ids = $orders->fetchAll(PDO::FETCH_COLUMN);
        check(count($ids) === $expected, 'numero exacto de pedidos persistidos');
        $lines = $pdo->prepare(
            'SELECT l.* FROM linea_pedido l JOIN pedido p ON p.id=l.id_pedido WHERE p.email IN (?,?)'
        );
        $lines->execute($caseEmails);
        $rows = $lines->fetchAll();
        $complete = count($rows) === $expected * count($cart);
        foreach ($ids as $id) {
            $orderRows = array_values(array_filter($rows, function (array $row) use ($id): bool {
                return (int)$row['id_pedido'] === (int)$id;
            }));
            $complete = $complete && count($orderRows) === count($cart);
            foreach ($orderRows as $row) { $complete = $complete && (int)$row['unidades'] === 1; }
        }
        check($complete, 'pedidos completos sin lineas parciales ni unidades duplicadas');
        foreach ($cart as $item) {
            $table = isset($item['id_variante']) ? 'producto_variante' : 'producto';
            $id = $item['id_variante'] ?? $item['id_producto'];
            check((int)$pdo->query("SELECT stock FROM $table WHERE id=$id")->fetchColumn() === $available - $expected,
                'stock exacto y nunca negativo para ' . $table . ':' . $id);
        }
    }

    // Dos envíos del mismo resumen y sesión: debe existir un solo pedido.
    $pdo->exec('UPDATE producto_variante SET stock=2 WHERE id=' . $a['id_variante']);
    $duplicateEmail = $prefix . '-doble@example.invalid';
    $emails[] = $duplicateEmail;
    $browser = raceBrowser([$a], $duplicateEmail);
    $bodies = raceConfirm([$browser, $browser->copySession()]);
    check(count(array_filter($bodies, function (string $body): bool {
        return strpos($body, 'Pedido realizado') !== false;
    })) === 1, 'doble confirmacion simultanea de la misma sesion crea una sola confirmacion');
    $orders = $pdo->prepare('SELECT COUNT(*) FROM pedido WHERE email=?');
    $orders->execute([$duplicateEmail]);
    check((int)$orders->fetchColumn() === 1, 'doble confirmacion persiste un solo pedido');
    check((int)$pdo->query('SELECT stock FROM producto_variante WHERE id=' . $a['id_variante'])->fetchColumn() === 1,
        'doble confirmacion descuenta una sola unidad');
    echo "PASS $checks comprobaciones de concurrencia\n";
} finally {
    foreach ($emails as $email) {
        $pdo->prepare('DELETE FROM linea_pedido WHERE id_pedido IN (SELECT id FROM pedido WHERE email=?)')->execute([$email]);
        $pdo->prepare('DELETE FROM pedido WHERE email=?')->execute([$email]);
    }
    foreach ($selected as $variant) {
        $pdo->prepare('UPDATE producto_variante SET stock=? WHERE id=?')->execute([$variant['stock'], $variant['id']]);
    }
    if ($syntheticId !== null) { $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$syntheticId]); }
}
