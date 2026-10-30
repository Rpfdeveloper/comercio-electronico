<?php
declare(strict_types=1);

require_once __DIR__ . '/support.php';

$emailPrefix = 'qa-' . bin2hex(random_bytes(5));
$emails = [$emailPrefix . '@example.invalid', $emailPrefix . '-new@example.invalid'];
$variant = $pdo->query('SELECT v.*, p.slug FROM producto_variante v JOIN producto p ON p.id=v.id_producto WHERE p.activo=1 AND v.activo=1 ORDER BY v.id LIMIT 1')->fetch();
if (!$variant) { fwrite(STDERR, "Importa primero el esquema de ejemplo en la BD de pruebas.\n"); exit(2); }
$vid = (int)$variant['id'];
$pid = (int)$variant['id_producto'];
$originalStock = (int)$variant['stock'];
$originalPrice = $variant['precio'];
$orderCount = function () use ($pdo, $emails): int {
    $q = $pdo->prepare('SELECT COUNT(*) FROM pedido WHERE email IN (?,?)'); $q->execute($emails); return (int)$q->fetchColumn();
};
$stock = function () use ($pdo, $vid): int { return (int)$pdo->query('SELECT stock FROM producto_variante WHERE id=' . $vid)->fetchColumn(); };
$add = ['action'=>'add', 'id_producto'=>$pid, 'id_variante'=>$vid, 'cantidad'=>1];
$data = ['step'=>'form','tipo'=>'invitado','email'=>$emails[0],'nombre'=>'QA local',
    'direccion'=>'Calle de prueba 1','cp'=>'28000','poblacion'=>'Madrid','pais'=>'Espana'];
try {
    $pdo->exec('UPDATE producto_variante SET stock=10 WHERE id=' . $vid);
    $b = new TestBrowser();
    $b->request('public/producto.php?p=' . urlencode($variant['slug']));
    check($b->status === 200 && strlen($b->token) === 64, 'producto y token de formulario');
    $b->request('public/carrito.php', $add, false);
    check($b->status === 403, 'POST sin token rechazado');
    $b->request('public/carrito.php', $add + ['csrf_token'=>'invalido'], false);
    check($b->status === 403, 'token incorrecto rechazado');
    $b->request('public/checkout.php', ['step'=>'confirm'], false);
    check($b->status === 403, 'confirmar pedido exige token');
    $other = new TestBrowser(); $other->request('admin/login.php');
    $other->request('public/carrito.php', $add + ['csrf_token'=>$b->token], false);
    check($other->status === 403, 'token de otra sesion rechazado');
    foreach ([0,-1,'1.5','abc',1000,['1']] as $quantity) {
        $bad = $add; $bad['cantidad'] = $quantity; $b->request('public/carrito.php', $bad);
        check($b->status === 422, 'cantidad invalida rechazada: ' . json_encode($quantity));
    }
    $bad=$add; $bad['id_producto']=999999; $b->request('public/carrito.php',$bad);
    check($b->status === 422, 'producto inexistente rechazado');
    $bad=$add; $bad['id_producto']=$pid+1; $b->request('public/carrito.php',$bad);
    check($b->status === 422, 'variante ajena rechazada');
    $bad=$add; unset($bad['id_variante']); $b->request('public/carrito.php',$bad);
    check($b->status === 422, 'capacidad obligatoria en productos con variantes');
    $bad=$add; $bad['cantidad']=11; $b->request('public/carrito.php',$bad);
    check($b->status === 422, 'cantidad superior al stock rechazada');
    $pdo->exec('UPDATE producto SET activo=0 WHERE id=' . $pid);
    $b->request('public/carrito.php',$add); check($b->status === 422, 'producto inactivo rechazado');
    $pdo->exec('UPDATE producto SET activo=1 WHERE id=' . $pid);
    $pdo->exec('UPDATE producto_variante SET activo=0 WHERE id=' . $vid);
    $b->request('public/carrito.php',$add); check($b->status === 422, 'variante inactiva rechazada');
    $pdo->exec('UPDATE producto_variante SET activo=1 WHERE id=' . $vid);
    $b->request('public/carrito.php',$add); check($b->status === 303, 'alta valida y redireccion antes de HTML');
    $b->request('public/carrito.php?remove=v' . $vid); check($b->status === 405, 'GET no elimina productos');
    $b->request('public/carrito.php',['action'=>'update','cantidad'=>['v'.$vid=>2]]);
    check($b->status === 303, 'actualizacion valida del carrito');
    $b->request('public/checkout.php'); $body=$b->request('public/checkout.php',$data);
    check(strpos($body,'Confirmar pedido')!==false, 'resumen de compra invitada');
    $body=$b->request('public/checkout.php',['step'=>'confirm']);
    check(strpos($body,'Pedido realizado')!==false && $orderCount()===1 && $stock()===8, 'pedido y descuento exacto de stock');
    $b->request('public/checkout.php',['step'=>'confirm']);
    check($orderCount()===1 && $stock()===8, 'repetir confirmacion no duplica pedido ni descuento');
    $b->request('public/carrito.php',$add); $b->request('public/checkout.php',$data);
    $pdo->exec('UPDATE producto_variante SET stock=0 WHERE id=' . $vid);
    $b->request('public/checkout.php',['step'=>'confirm']);
    check($b->status===409 && $orderCount()===1 && $stock()===0, 'stock agotado tras resumen bloquea pedido');
    $body=$b->request('public/carrito.php'); check(strpos($body,'name="remove"')!==false, 'producto agotado sigue siendo eliminable');
    $b->request('public/carrito.php',['remove'=>'v'.$vid]); check($b->status===303, 'eliminar producto agotado');
    $pdo->exec('UPDATE producto_variante SET stock=10 WHERE id=' . $vid);
    $b->request('public/carrito.php',$add); $b->request('public/checkout.php',$data);
    $pdo->exec('UPDATE producto_variante SET precio=precio+1 WHERE id=' . $vid);
    // El producto de ejemplo puede estar en oferta; alterar ambos precios permite comprobar el cambio efectivo.
    $offer=$pdo->query('SELECT precio_oferta FROM producto_variante WHERE id=' . $vid)->fetchColumn();
    if ((float)$offer>0) { $pdo->exec('UPDATE producto_variante SET precio_oferta=precio_oferta+1 WHERE id=' . $vid); }
    $body=$b->request('public/checkout.php',['step'=>'confirm']);
    check(strpos($body,'Pedido realizado')===false && $orderCount()===1 && $stock()===10, 'precio cambiado requiere nuevo resumen y revierte stock');
    $st=$pdo->prepare('INSERT INTO cliente(email,nombre) VALUES (?,?)'); $st->execute([$emails[0],'QA']); $customer=(int)$pdo->lastInsertId();
    $hash=password_hash('correct-password',PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO cliente_registrado(id_cliente,password_hash,direccion,cp,poblacion,pais) VALUES (?,?,?,?,?,?)')->execute([$customer,$hash,'QA','28000','Madrid','Espana']);
    $registered=$data; $registered['tipo']='registrado'; $registered['password']='wrong-password';
    $body=$b->request('public/checkout.php',$registered);
    check(strpos($body,'Confirmar pedido')===false, 'cuenta existente no admite password incorrecto');
    $registered['password']='correct-password'; $body=$b->request('public/checkout.php',$registered);
    check(strpos($body,'Confirmar pedido')!==false, 'cuenta existente acepta password correcto');
    $pdo->prepare('UPDATE cliente_registrado SET password_hash=? WHERE id_cliente=?')->execute([password_hash('changed-after-summary',PASSWORD_DEFAULT),$customer]);
    $body=$b->request('public/checkout.php',['step'=>'confirm']);
    check(strpos($body,'Pedido realizado')===false && $orderCount()===1 && $stock()===10, 'credenciales cambiadas invalidan resumen y revierten descuento de stock');
    $pdo->prepare('UPDATE cliente_registrado SET password_hash=? WHERE id_cliente=?')->execute([$hash,$customer]);
    $b->request('public/checkout.php',$registered);
    $body=$b->request('public/checkout.php',['step'=>'confirm']);
    $actual=$pdo->query('SELECT password_hash FROM cliente_registrado WHERE id_cliente=' . $customer)->fetchColumn();
    check(strpos($body,'Pedido realizado')!==false && $actual===$hash && $stock()===9, 'cuenta existente compra conservando hash');
    $b->request('public/carrito.php',$add); $registered['email']=$emails[1];
    $b->request('public/checkout.php',$registered); $body=$b->request('public/checkout.php',['step'=>'confirm']);
    check(strpos($body,'Pedido realizado')!==false && $orderCount()===3 && $stock()===8, 'alta nueva y compra registrada');
    $b->request('public/carrito.php',$add); $b->request('public/carrito.php',['action'=>'update','cantidad'=>['v'.$vid=>0]]);
    check($b->status===303, 'cantidad cero elimina linea en actualizacion');
    $b->request('public/producto.php?p=qa-no-existe'); check($b->status===404, 'producto inexistente devuelve 404');
    $b->request('admin/login.php'); $b->request('admin/login.php',['user'=>'admin','pass'=>'x'],false);
    check($b->status===403, 'login admin exige token');
    $adminPass=getenv('TIENDA_TEST_ADMIN_PASS');
    if ($adminPass) {
        $b->request('admin/login.php',['user'=>'admin','pass'=>$adminPass]); check($b->status===302, 'login administrativo valido');
        $body=$b->request('admin/pedidos.php'); check($b->status===200 && strpos($body,'action="logout.php"')!==false, 'panel y formulario de salida');
        $b->request('admin/logout.php'); check($b->status===405, 'GET no cierra sesion');
        $b->request('admin/pedidos.php'); check($b->status===200, 'GET de salida no elimina autenticacion');
        $b->request('admin/logout.php',[]); check($b->status===302, 'salida administrativa mediante POST valido');
        $b->request('admin/pedidos.php'); check($b->status===302, 'panel restringido tras salir');
    }
    echo "PASS $checks comprobaciones\n";
} finally {
    $pdo->exec('UPDATE producto SET activo=1 WHERE id=' . $pid);
    $pdo->prepare('UPDATE producto_variante SET stock=?,precio=?,precio_oferta=?,activo=? WHERE id=?')->execute([$originalStock,$originalPrice,$variant['precio_oferta'],$variant['activo'],$vid]);
    $st=$pdo->prepare('DELETE FROM linea_pedido WHERE id_pedido IN (SELECT id FROM pedido WHERE email IN (?,?))'); $st->execute($emails);
    $st=$pdo->prepare('DELETE FROM pedido WHERE email IN (?,?)'); $st->execute($emails);
    $st=$pdo->prepare('DELETE FROM cliente_registrado WHERE id_cliente IN (SELECT id FROM cliente WHERE email IN (?,?))'); $st->execute($emails);
    $st=$pdo->prepare('DELETE FROM cliente WHERE email IN (?,?)'); $st->execute($emails);
}
