<?php
declare(strict_types=1);

require_once __DIR__ . '/support.php';

$email = 'checkout-' . bin2hex(random_bytes(6)) . '@example.invalid';
$variant = $pdo->query('SELECT v.*, p.slug FROM producto_variante v JOIN producto p ON p.id=v.id_producto JOIN categoria c ON c.id=p.id_categoria WHERE p.activo=1 AND v.activo=1 AND c.activa=1 ORDER BY v.id LIMIT 1')->fetch();
if (!$variant) { throw new RuntimeException('Falta un producto de prueba activo.'); }
$vid = (int)$variant['id'];
$pid = (int)$variant['id_producto'];
$key = 'v' . $vid;
$add = ['action'=>'add', 'id_producto'=>$pid, 'id_variante'=>$vid, 'cantidad'=>1];
$data = ['step'=>'form', 'tipo'=>'invitado', 'email'=>$email, 'nombre'=>'Compra de prueba',
    'direccion'=>'Calle de ejemplo 1', 'cp'=>'28000', 'poblacion'=>'Madrid', 'provincia'=>'', 'pais'=>'España'];
$orderCount = function () use ($pdo, $email): int {
    $q=$pdo->prepare('SELECT COUNT(*) FROM pedido WHERE email=?'); $q->execute([$email]); return (int)$q->fetchColumn();
};
$invalid = function (TestBrowser $b, array $input, string $field, string $label) use ($orderCount): void {
    $body=$b->request('public/checkout.php', $input);
    check($b->status===422 && strpos($body, 'href="#checkout-'.$field.'"')!==false &&
        strpos($body, 'id="error-'.$field.'"')!==false && strpos($body, 'Confirmar pedido')===false && $orderCount()===0, $label);
};
try {
    $pdo->exec('UPDATE producto_variante SET stock=20,precio=19.99,precio_oferta=NULL WHERE id='.$vid);
    $b=new TestBrowser(); $b->request('public/producto.php?p='.urlencode($variant['slug'])); $b->request('public/carrito.php',$add);
    $b->request('public/carrito.php',['action'=>'update','cantidad'=>[$key=>3],'continuar'=>'1']);
    check($b->status===303 && ($b->headers['location']??'')==='checkout.php', 'continuar guarda cantidades y redirige al checkout');
    $body=$b->request('public/carrito.php');
    check(strpos($body,'value="3"')!==false && strpos($body,'59,97 €')!==false && strpos($body,'12,59 €')!==false && strpos($body,'72,56 €')!==false, 'carrito muestra productos, IVA redondeado y total de tres unidades');
    $body=$b->request('public/checkout.php');
    check(strpos($body,'3 unidades')!==false && strpos($body,'72,56 €')!==false, 'checkout conserva cantidades e importes del carrito');
    check(strpos($b->headers['cache-control']??'', 'no-store')!==false, 'formulario con datos personales no admite caché');
    foreach ([['cantidad'=>[$key=>21],'continuar'=>'1'], ['cantidad'=>[$key=>4],'continuar'=>['1']], ['cantidad'=>[$key=>['2']],'continuar'=>'1']] as $bad) {
        $body=$b->request('public/carrito.php',['action'=>'update']+$bad);
        check($b->status===422 && strpos($body,'role="alert"')!==false, 'continuar rechaza cantidades o acción inválidas');
    }
    $body=$b->request('public/carrito.php');
    check(strpos($body,'value="3"')!==false && strpos($body,'72,56 €')!==false, 'errores al continuar conservan el carrito anterior');
    foreach (['email','nombre','direccion','cp','poblacion','pais'] as $field) {
        $bad=$data; $bad[$field]='  ';
        $invalid($b,$bad,$field,'campo obligatorio vacío rechazado: '.$field);
    }
    foreach (['email'=>161,'nombre'=>121,'direccion'=>201,'cp'=>13,'poblacion'=>121,'provincia'=>121,'pais'=>121] as $field=>$length) {
        $bad=$data; $bad[$field]=str_repeat('á',$length);
        $invalid($b,$bad,$field,'límite de longitud Unicode aplicado: '.$field);
    }
    $bad=$data; $bad['email']='correo-invalido'; $invalid($b,$bad,'email','correo inválido asociado a su campo');
    $bad=$data; $bad['direccion']="Prueba\nSegunda línea"; $invalid($b,$bad,'direccion','controles en dirección rechazados');
    $bad=$data; $bad['nombre']="Prueba\xFF"; $invalid($b,$bad,'nombre','UTF-8 incorrecto rechazado sin errores PHP');
    $bad=$data; $bad['tipo']='otro'; $invalid($b,$bad,'tipo','tipo de compra desconocido rechazado');
    $bad=$data; $bad['tipo']='registrado'; $invalid($b,$bad,'password','contraseña requerida para comprar con una cuenta');
    $bad['password']='clave-corta'; $invalid($b,$bad,'password','cuenta nueva exige contraseña mínima');
    $body=$b->request('public/checkout.php',$data);
    check($b->status===200 && strpos($body,'Confirmar pedido')!==false && strpos($body,'72,56 €')!==false, 'provincia opcional permite revisar total y confirmar');
    $body=$b->request('public/checkout.php');
    check(strpos($body,'value="Compra de prueba"')!==false && strpos($body,'value="Calle de ejemplo 1"')!==false, 'volver a datos conserva el formulario revisado');
    $bad=$data; $bad['nombre']=''; $invalid($b,$bad,'nombre','corregir un resumen con datos inválidos lo anula');
    $b->request('public/checkout.php',['step'=>'confirm']);
    check($b->status===302 && $orderCount()===0, 'resumen anulado no puede crear un pedido');
    foreach ([['step'=>'inventado'], ['step'=>'form','nombre'=>['texto']], ['step'=>'form','password'=>str_repeat('x',4097)]] as $bad) {
        $b->request('public/checkout.php',$data);
        $b->request('public/checkout.php',$bad);
        check($b->status===422, 'forma malformada de compra rechazada');
        $b->request('public/checkout.php',['step'=>'confirm']);
        check($b->status===302 && $orderCount()===0, 'forma malformada invalida el resumen anterior');
    }
    $escaped=$data; $escaped['nombre']='<script>alert("qa")</script>'; $escaped['password']='only-fake-guest-secret';
    $body=$b->request('public/checkout.php',$escaped);
    check(strpos($body,'&lt;script&gt;')!==false && strpos($body,'<script>alert')===false && strpos($body,'only-fake-guest-secret')===false, 'resumen escapa texto y nunca refleja la contraseña');
    $maximum=$data;
    foreach (['nombre'=>120,'direccion'=>200,'cp'=>12,'poblacion'=>120,'pais'=>120] as $field=>$length) { $maximum[$field]=str_repeat('é',$length); }
    $body=$b->request('public/checkout.php',$maximum);
    check($b->status===200 && strpos($body,'Confirmar pedido')!==false, 'longitudes máximas válidas admiten texto multibyte');
    $body=$b->request('public/checkout.php',['step'=>'confirm']);
    check($b->status===200 && strpos($body,'Pedido realizado')!==false && strpos($body,'72,56 €')!==false && $orderCount()===1, 'confirmación registra el total mostrado');
    $q=$pdo->prepare('SELECT * FROM pedido WHERE email=?'); $q->execute([$email]); $order=$q->fetch();
    check($order['nombre_envio']===$maximum['nombre'] && $order['direccion_envio']===$maximum['direccion'] && $order['pais_envio']===$maximum['pais'] && $order['provincia_envio']===null, 'datos máximos se guardan completos sin truncamiento');
    check((float)$order['importe_productos']===59.97 && (float)$order['impuestos']===12.59 && (float)$order['total']===72.56 && (float)$order['gastos_envio']===0.0, 'importes de base de datos coinciden con carrito y resumen');
    check((int)$pdo->query('SELECT stock FROM producto_variante WHERE id='.$vid)->fetchColumn()===17, 'confirmar descuenta las cantidades guardadas al continuar');
    $b->request('public/checkout.php',['step'=>'confirm']);
    check($orderCount()===1, 'repetir confirmación no duplica la compra');
    $b->request('public/carrito.php',$add);
    $b->request('public/carrito.php',['action'=>'update','cantidad'=>[$key=>0],'continuar'=>'1']);
    check($b->status===303 && ($b->headers['location']??'')==='carrito.php', 'continuar con todas las cantidades a cero vuelve al carrito');
    $body=$b->request('public/carrito.php');
    check(strpos($body,'Tu carrito está vacío')!==false && strpos($body,'Ver perfumes')!==false, 'carrito vacío permite volver al catálogo');
    $b->request('public/carrito.php',$add);
    $pdo->exec('UPDATE producto_variante SET stock=0 WHERE id='.$vid);
    $body=$b->request('public/checkout.php');
    check($b->status===409 && strpos($body,'href="carrito.php"')!==false, 'producto agotado ofrece recuperar el carrito');
    echo "PASS $checks comprobaciones de checkout\n";
} finally {
    $pdo->prepare('UPDATE producto_variante SET stock=?,precio=?,precio_oferta=? WHERE id=?')->execute([$variant['stock'],$variant['precio'],$variant['precio_oferta'],$vid]);
    $pdo->prepare('DELETE FROM linea_pedido WHERE id_pedido IN (SELECT id FROM pedido WHERE email=?)')->execute([$email]);
    $pdo->prepare('DELETE FROM pedido WHERE email=?')->execute([$email]);
}
