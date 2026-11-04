<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
require_once __DIR__ . '/../tienda/src/lib/cart_account.php';

$marker = 'carrito-' . bin2hex(random_bytes(5));
$password = 'fictitious-cart-password';
$emails = [$marker . '@example.invalid', $marker . '-otra@example.invalid'];
$customers = []; $products = []; $category = null; $brand = null; $renamed = false;
$saved = static function (int $customer) use ($pdo): array {
    $st = $pdo->prepare('SELECT clave,cantidad FROM cliente_carrito WHERE id_cliente=? ORDER BY clave');
    $st->execute([$customer]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_KEY_PAIR));
};
$lines = static function (string $html): array {
    preg_match_all('/name="cantidad\[([pv][0-9]+)\]" value="([0-9]+)"/', $html, $m);
    return array_map('intval', array_combine($m[1], $m[2]));
};
$login = static function (TestBrowser $b, string $email) use ($password): void {
    $b->request('public/acceso.php');
    $b->request('public/acceso.php', ['email'=>$email, 'password'=>$password]);
};
$add = static function (TestBrowser $b, int $product, ?int $variant, $qty): void {
    $b->request('public/carrito.php');
    $b->request('public/carrito.php', ['action'=>'add', 'id_producto'=>$product, 'id_variante'=>$variant ?? '', 'cantidad'=>$qty]);
};
try {
    $pdo->exec('DELETE FROM acceso_intento');
    check(cart_account_merge(['p1'=>['id_producto'=>1,'id_variante'=>null,'cantidad'=>2]], ['p1'=>['id_producto'=>1,'id_variante'=>null,'cantidad'=>1], 'v9'=>['id_producto'=>2,'id_variante'=>9,'cantidad'=>3]]) ===
        ['p1'=>['id_producto'=>1,'id_variante'=>null,'cantidad'=>2], 'v9'=>['id_producto'=>2,'id_variante'=>9,'cantidad'=>3]], 'union conserva la mayor cantidad sin sumar el mismo carrito dos veces');
    check(cart_account_clean(['p1'=>['id_producto'=>'1','cantidad'=>'1000'], 'p2'=>['id_producto'=>3,'cantidad'=>1], 'x'=>'y', 'v5'=>['id_producto'=>1,'id_variante'=>5,'cantidad'=>2]]) ===
        ['v5'=>['id_producto'=>1,'id_variante'=>5,'cantidad'=>2]], 'entradas malformadas, claves incoherentes y cantidades fuera de rango se descartan');
    foreach ($emails as $email) {
        $pdo->prepare('INSERT INTO cliente(email,nombre) VALUES (?,?)')->execute([$email, 'Cliente de prueba']);
        $id = (int)$pdo->lastInsertId(); $customers[] = $id;
        $pdo->prepare('INSERT INTO cliente_registrado(id_cliente,password_hash,direccion,cp,poblacion,pais) VALUES (?,?,?,?,?,?)')
            ->execute([$id, password_hash($password, PASSWORD_DEFAULT), 'Direccion de prueba', '00000', 'Prueba', 'Prueba']);
    }
    $pdo->prepare('INSERT INTO categoria(nombre,slug,activa) VALUES (?,?,1)')->execute(['Categoría de prueba', $marker]);
    $category = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute([$marker . ' marca', $marker]);
    $brand = (int)$pdo->lastInsertId();
    $insert = $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,precio,stock,activo) VALUES (?,?,?,?,?,?,1)');
    foreach (['a'=>50, 'base'=>0, 'c'=>50] as $key=>$stock) {
        $insert->execute([$category, $brand, $marker . ' ' . $key, $marker . '-' . $key, 20, $stock]);
        $products[$key] = (int)$pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO producto_variante(id_producto,capacidad_ml,sku,precio,stock,activo) VALUES (?,?,?,?,?,1)')
        ->execute([$products['base'], 50, $marker . '-SKU', 30, 50]);
    $products['v'] = (int)$pdo->lastInsertId();
    $pa = 'p' . $products['a']; $pc = 'p' . $products['c']; $vv = 'v' . $products['v'];

    $a = new TestBrowser();
    $add($a, $products['a'], null, 2);
    check($saved($customers[0]) === [], 'carrito invitado no se guarda en ninguna cuenta');
    $html = $a->request('public/carrito.php');
    check(strpos($html, 'data-cart-saved') === false, 'invitado no ve el aviso de carrito guardado');
    check($lines($a->request('public/carrito.php')) === [$pa=>2], 'invitado tiene el producto en el carrito');
    $login($a, $emails[0]);
    if ($a->status !== 303 || $saved($customers[0]) !== [$pa=>2]) { echo 'DIAGNOSTICO acceso=' . $a->status . ' guardado=' . json_encode($saved($customers[0])) . "\n"; }
    check($a->status === 303 && $saved($customers[0]) === [$pa=>2], 'al acceder, el carrito del navegador se guarda en la cuenta');
    $html = $a->request('public/carrito.php');
    check($lines($html) === [$pa=>2] && strpos($html, 'data-cart-saved') !== false, 'cliente ve su carrito y el aviso de guardado');

    $b = new TestBrowser();
    $add($b, $products['a'], null, 1);
    $add($b, $products['base'], $products['v'], 3);
    $login($b, $emails[0]);
    check($saved($customers[0]) === [$pa=>2, $vv=>3], 'segundo dispositivo une carritos sin duplicar unidades');
    check($lines($b->request('public/carrito.php')) === [$pa=>2, $vv=>3], 'segundo dispositivo recupera la union');
    $b->request('public/carrito.php', ['remove'=>$pa]);
    check($b->status === 303 && $saved($customers[0]) === [$vv=>3], 'eliminar un producto actualiza la cuenta');
    check($lines($a->request('public/carrito.php')) === [$vv=>3], 'lo eliminado en otro dispositivo no reaparece');
    $a->request('public/carrito.php', ['action'=>'update', 'cantidad'=>[$vv=>'5']]);
    check($saved($customers[0]) === [$vv=>5] && $lines($b->request('public/carrito.php')) === [$vv=>5], 'cambios de cantidad se comparten entre dispositivos');
    $before = $saved($customers[0]);
    foreach ([['action'=>'add','id_producto'=>$products['a'],'cantidad'=>'0'], ['action'=>'add','id_producto'=>[$products['a']],'cantidad'=>1],
        ['action'=>'add','id_producto'=>$products['a'],'cantidad'=>'1000'], ['action'=>'update','cantidad'=>['p999999999'=>'1']],
        ['action'=>'add','id_producto'=>$products['a'],'cantidad'=>'51']] as $bad) {
        $a->request('public/carrito.php', $bad);
        check($a->status === 422 && $saved($customers[0]) === $before, 'entrada invalida no modifica el carrito guardado: ' . json_encode($bad));
    }
    $a->request('public/carrito.php', ['action'=>'add', 'id_producto'=>$products['a'], 'cantidad'=>1], false);
    check($a->status === 403 && $saved($customers[0]) === $before, 'sin CSRF no se modifica el carrito guardado');

    $other = new TestBrowser();
    $login($other, $emails[1]);
    check($lines($other->request('public/carrito.php')) === [] && $saved($customers[1]) === [], 'otra cuenta no ve el carrito ajeno');

    $a->request('public/salir.php', []);
    check($lines($a->request('public/carrito.php')) === [$vv=>5], 'cerrar sesion conserva el carrito en el navegador');
    $login($a, $emails[0]);
    check($saved($customers[0]) === [$vv=>5], 'volver a acceder no duplica las unidades');

    $pdo->prepare('UPDATE producto SET activo=0 WHERE id=?')->execute([$products['base']]);
    $html = $a->request('public/carrito.php');
    check(strpos($html, 'Hay productos que ya no están disponibles') !== false && $saved($customers[0]) === [$vv=>5], 'producto retirado se muestra como no disponible sin borrarse en silencio');
    $a->request('public/carrito.php', ['remove'=>$vv]);
    check($saved($customers[0]) === [] && $lines($a->request('public/carrito.php')) === [], 'producto retirado se puede eliminar del carrito guardado');
    $pdo->prepare('UPDATE producto SET activo=1 WHERE id=?')->execute([$products['base']]);

    // Sin la tabla (migración no aplicada o fallo), el navegador mantiene el carrito y se avisa.
    $pdo->exec('RENAME TABLE cliente_carrito TO cliente_carrito_pausa'); $renamed = true;
    $add($a, $products['c'], null, 1);
    $html = $a->request('public/carrito.php');
    check(strpos($html, 'no se ha podido guardar en tu cuenta') !== false || strpos($html, 'No podemos recuperar el carrito de tu cuenta') !== false, 'fallo al guardar se comunica');
    check($lines($html) === [$pc=>1], 'fallo de la base de datos conserva el carrito del navegador');
    $pdo->exec('RENAME TABLE cliente_carrito_pausa TO cliente_carrito'); $renamed = false;
    $lines($a->request('public/carrito.php'));
    $a->request('public/carrito.php', ['action'=>'update', 'cantidad'=>[$pc=>'2']]);
    check($saved($customers[0]) === [$pc=>2], 'tras recuperarse, el siguiente cambio se guarda');

    $pdo->prepare('UPDATE cliente_registrado SET password_hash=? WHERE id_cliente=?')->execute([password_hash('otra-clave-ficticia', PASSWORD_DEFAULT), $customers[1]]);
    $add($other, $products['a'], null, 1);
    check($saved($customers[1]) === [], 'credencial cambiada no permite escribir en el carrito de la cuenta');

    $checkout = ['step'=>'form', 'tipo'=>'registrado', 'email'=>$emails[0], 'nombre'=>'Cliente de prueba',
        'direccion'=>'Direccion de prueba', 'cp'=>'00000', 'poblacion'=>'Prueba', 'pais'=>'Prueba'];
    $b->request('public/carrito.php');
    $html = $b->request('public/checkout.php');
    check($b->status === 200 && strpos($html, 'value="' . $emails[0] . '"') !== false, 'checkout parte del carrito de la cuenta en otro dispositivo');
    $html = $b->request('public/checkout.php', $checkout);
    check(strpos($html, 'Confirmar pedido') !== false && strpos($html, '48,40') !== false, 'resumen usa las cantidades guardadas en la cuenta');
    $html = $b->request('public/checkout.php', ['step'=>'confirm']);
    check(strpos($html, 'Pedido realizado') !== false, 'pedido confirmado');
    check($saved($customers[0]) === [] && $lines($a->request('public/carrito.php')) === [], 'comprar vacia el carrito guardado en todos los dispositivos');
    $st = $pdo->prepare('SELECT stock FROM producto WHERE id=?'); $st->execute([$products['c']]);
    check((int)$st->fetchColumn() === 48, 'stock descontado una sola vez');
    check((int)$pdo->query("SELECT COUNT(*) FROM cliente_carrito WHERE clave NOT REGEXP '^[pv][0-9]+$'")->fetchColumn() === 0, 'claves guardadas sin datos personales');
    echo "PASS $checks comprobaciones de carrito guardado\n";
} finally {
    if ($renamed) { $pdo->exec('RENAME TABLE cliente_carrito_pausa TO cliente_carrito'); }
    if ($customers) {
        $ids = implode(',', array_map('intval', $customers));
        $orders = $pdo->query('SELECT id FROM pedido WHERE id_cliente IN (' . $ids . ')')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($orders as $order) { $pdo->prepare('DELETE FROM pedido WHERE id=?')->execute([$order]); }
        foreach ($customers as $id) {
            $pdo->prepare('DELETE FROM cliente_registrado WHERE id_cliente=?')->execute([$id]);
            $pdo->prepare('DELETE FROM cliente WHERE id=?')->execute([$id]);
        }
    }
    if (isset($products['v'])) { $pdo->prepare('DELETE FROM producto_variante WHERE id=?')->execute([$products['v']]); }
    foreach (['a', 'base', 'c'] as $key) { if (isset($products[$key])) { $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$products[$key]]); } }
    if ($brand !== null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
    if ($category !== null) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$category]); }
    $pdo->exec('DELETE FROM acceso_intento');
}
