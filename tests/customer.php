<?php
declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/../tienda/src/lib/customer.php';

$prefix = 'cuenta-' . bin2hex(random_bytes(5));
$emails = [$prefix . '@example.invalid', $prefix . '-other@example.invalid'];
$password = 'fictitious-test-password';
$customers = [];
$orders = [];
$variant = $pdo->query('SELECT * FROM producto_variante WHERE activo=1 AND stock>0 LIMIT 1')->fetch();
if (!$variant) { throw new RuntimeException('Faltan productos de prueba'); }
$add = ['action'=>'add','id_producto'=>$variant['id_producto'],'id_variante'=>$variant['id'],'cantidad'=>1];
$login = function (TestBrowser $browser, string $email, string $pass) {
    $browser->request('public/acceso.php');
    return $browser->request('public/acceso.php', ['email'=>$email,'password'=>$pass]);
};
$form = ['step'=>'form','tipo'=>'registrado','email'=>$emails[0],'nombre'=>'Cliente ficticio',
    'direccion'=>'Direccion de prueba','cp'=>'00000','poblacion'=>'Prueba','pais'=>'Prueba'];
try {
    $pdo->exec('DELETE FROM acceso_intento'); // Solo base desechable validada por support.php.
    foreach ($emails as $email) {
        $pdo->prepare('INSERT INTO cliente(email,nombre) VALUES (?,?)')->execute([$email,'<script>fixture</script>']);
        $id = (int)$pdo->lastInsertId(); $customers[] = $id;
        $pdo->prepare('INSERT INTO cliente_registrado(id_cliente,password_hash,direccion,cp,poblacion,pais) VALUES (?,?,?,?,?,?)')
            ->execute([$id,password_hash($password,PASSWORD_DEFAULT),'Direccion de prueba','00000','Prueba','Prueba']);
    }
    // 21 pedidos propios, uno ajeno y uno invitado con el mismo correo.
    for ($i=0; $i<23; ++$i) {
        $customer = $i<21 ? $customers[0] : ($i===21 ? $customers[1] : null);
        $pdo->prepare('INSERT INTO pedido(id_cliente,email,nombre_envio,direccion_envio,cp_envio,poblacion_envio,pais_envio,importe_productos,impuestos,total) VALUES (?,?,?,?,?,?,?,10,2.10,12.10)')
            ->execute([$customer,$emails[$i===21 ? 1 : 0],'Cliente de prueba','<script>direccion</script>','00000','Prueba','Prueba']);
        $orders[] = (int)$pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO linea_pedido(id_pedido,id_producto,nombre_producto,unidades,precio_unitario,precio_neto) VALUES (?,?,?,1,10,10)')
        ->execute([$orders[20],$variant['id_producto'],'<script>producto</script>']);

    $b = new TestBrowser();
    foreach (['cuenta.php','mi-pedido.php?id='.$orders[20]] as $path) {
        $b->request('public/'.$path);
        check($b->status===302 && ($b->headers['location']??'')==='acceso.php', 'pagina privada requiere acceso: '.$path);
    }
    $b->request('public/acceso.php');
    check(strpos($b->headers['cache-control']??'', 'no-store')!==false, 'acceso no se almacena en cache');
    $b->request('public/acceso.php',['email'=>$emails[0],'password'=>$password],false);
    check($b->status===403, 'acceso rechaza POST sin CSRF');
    $b->request('public/acceso.php',['email'=>['incorrecto'],'password'=>$password]);
    check($b->status===422, 'acceso rechaza datos no escalares');
    $wrong=$login($b,$emails[0],'incorrect-password');
    check($b->status===401 && strpos($wrong,'El correo o la contraseña no son correctos.')!==false, 'credenciales incorrectas devuelven error generico');
    $unknown=$login($b,$prefix.'-missing@example.invalid','incorrect-password');
    check($b->status===401 && strpos($unknown,'El correo o la contraseña no son correctos.')!==false, 'cuenta inexistente devuelve el mismo error');
    $b->request('public/carrito.php',$add);
    $beforeLogin = $b->copySession();
    $login($b,$emails[0],$password);
    check($b->status===303 && ($b->headers['location']??'')==='cuenta.php', 'acceso correcto redirige a la cuenta');
    $beforeLogin->request('public/cuenta.php');
    check($beforeLogin->status===302, 'la cookie anterior al acceso no autoriza la cuenta');
    $body=$b->request('public/cuenta.php');
    check($b->status===200 && substr_count($body,'href="mi-pedido.php?id=')===20 && strpos($body,'Siguiente')!==false, 'historial muestra 20 pedidos y paginacion');
    check(strpos($body,'&lt;script&gt;fixture&lt;/script&gt;')!==false && strpos($body,'<script>fixture')===false, 'nombre de cliente escapado');
    check(strpos($body,'mi-pedido.php?id='.$orders[21].'"')===false && strpos($body,'mi-pedido.php?id='.$orders[22].'"')===false, 'historial excluye pedidos ajenos e invitados con el mismo correo');
    check(strpos($b->headers['cache-control']??'', 'no-store')!==false, 'cuenta no se almacena en cache');
    $body=$b->request('public/cuenta.php?pagina=2');
    check($b->status===200 && substr_count($body,'href="mi-pedido.php?id=')===1, 'segunda pagina contiene el pedido restante');
    $b->request('public/cuenta.php?pagina[]=1');
    check($b->status===404, 'paginacion malformada rechazada');
    $body=$b->request('public/mi-pedido.php?id='.$orders[20]);
    check($b->status===200 && strpos($body,'12,10')!==false, 'detalle propio muestra el total guardado');
    check(strpos($body,'&lt;script&gt;producto&lt;/script&gt;')!==false && strpos($body,'&lt;script&gt;direccion&lt;/script&gt;')!==false, 'producto y direccion escapados');
    $notFound=[];
    foreach ([$orders[21],$orders[22],2147483647] as $id) {
        $notFound[]=$b->request('public/mi-pedido.php?id='.$id);
        check($b->status===404, 'detalle ajeno, invitado o inexistente no revela el pedido');
    }
    check(count(array_unique($notFound))===1, 'pedidos prohibidos e inexistentes tienen respuesta identica');
    $b->request('public/mi-pedido.php?id[]=1');
    check($b->status===404, 'identificador de pedido malformado rechazado');
    $body=$b->request('public/checkout.php');
    check(strpos($body,'value="'.$emails[0].'"')!==false, 'checkout recupera el correo de la cuenta');
    $body=$b->request('public/checkout.php',$form);
    check(strpos($body,'Confirmar pedido')!==false, 'cuenta autenticada prepara compra sin repetir password');
    $foreign=$form; $foreign['email']=$emails[1];
    $body=$b->request('public/checkout.php',$foreign);
    check(strpos($body,'Confirmar pedido')===false, 'sesion no autoriza compras a nombre de otra cuenta');
    $b->request('public/salir.php');
    check($b->status===405, 'GET no cierra la cuenta');
    $b->request('public/salir.php',[],false);
    check($b->status===403, 'cerrar cuenta exige CSRF');
    $b->request('public/cuenta.php');
    check($b->status===200, 'intentos invalidos de salida conservan autenticacion');
    $beforeLogout=$b->copySession();
    $b->request('public/salir.php',[]);
    check($b->status===303, 'salida mediante POST valido');
    $b->request('public/cuenta.php'); $beforeLogout->request('public/cuenta.php');
    check($b->status===302 && $beforeLogout->status===302, 'salida invalida la sesion y su cookie anterior');
    $body=$b->request('public/carrito.php');
    check(strpos($body,'name="remove"')!==false, 'salida conserva el carrito');
    $login($b,$emails[0],$password);
    $pdo->prepare('UPDATE cliente_registrado SET password_hash=? WHERE id_cliente=?')
        ->execute([password_hash('changed-fictitious-password',PASSWORD_DEFAULT),$customers[0]]);
    $b->request('public/cuenta.php');
    check($b->status===302, 'cambio de credenciales invalida una sesion ya abierta');

    $_SESSION=['customer'=>['id'=>$customers[1],'proof'=>hash('sha256',$pdo->query('SELECT password_hash FROM cliente_registrado WHERE id_cliente='.$customers[1])->fetchColumn()),'started'=>time(),'last_active'=>time()-1801],'checkout'=>['pending'=>true]];
    check(customer_current($pdo)===null && !isset($_SESSION['checkout']), '30 minutos de inactividad invalidan cuenta y resumen');
    $_SESSION=['customer'=>['id'=>$customers[1],'started'=>time()-28801,'last_active'=>time()]];
    check(customer_current($pdo)===null, 'sesion supera ocho horas aunque siga activa');

    $pdo->exec('DELETE FROM acceso_intento');
    for ($i=0;$i<5;++$i) { $probe=new TestBrowser(); $login($probe,$emails[1],'incorrect-password'); }
    $probe=new TestBrowser(); $login($probe,strtoupper($emails[1]),$password);
    check($probe->status===429 && ($probe->headers['retry-after']??'')==='900', 'limite por correo resiste nuevas cookies y cambios de mayusculas');
    $alias=new TestBrowser(); $login($alias,str_replace('cuenta','cuéntá',$emails[1]),$password);
    check($alias->status===401, 'alias Unicode de la colacion SQL no elude el limite de la cuenta');
    $probe->request('public/carrito.php',$add);
    $limited=$form; $limited['email']=$emails[1]; $limited['password']=$password;
    $probe->request('public/checkout.php',$limited);
    check($probe->status===429, 'checkout comparte el limite de intentos de acceso');
    $pdo->exec('UPDATE acceso_intento SET ventana=UTC_TIMESTAMP()-INTERVAL 16 MINUTE');
    $login($probe,$emails[1],$password);
    check($probe->status===303, 'ventana vencida permite autenticarse de nuevo');
    $emailKey=array_key_first(customer_attempt_keys($emails[1],''));
    $q=$pdo->prepare('SELECT COUNT(*) FROM acceso_intento WHERE clave=?'); $q->execute([$emailKey]);
    check((int)$q->fetchColumn()===0, 'acceso correcto elimina el contador del correo');
    $pdo->exec('DELETE FROM acceso_intento');
    $ipKey=array_keys(customer_attempt_keys('', '127.0.0.1'))[1];
    $pdo->prepare('INSERT INTO acceso_intento(clave,ventana,intentos) VALUES (?,UTC_TIMESTAMP(),30)')->execute([$ipKey]);
    $probe=new TestBrowser(); $login($probe,$emails[1],$password);
    check($probe->status===429, 'limite por IP bloquea aunque cambie el correo');
    $staleKey=str_repeat('0',64);
    $pdo->prepare('INSERT INTO acceso_intento(clave,ventana,intentos) VALUES (?,UTC_TIMESTAMP()-INTERVAL 2 DAY,1)')->execute([$staleKey]);
    $probe=new TestBrowser(); $login($probe,$emails[1],$password);
    $q->execute([$staleKey]);
    check((int)$q->fetchColumn()===0, 'limpieza elimina identificadores de intentos antiguos');
    check((int)$pdo->query("SELECT COUNT(*) FROM acceso_intento WHERE clave NOT REGEXP '^[a-f0-9]{64}$'")->fetchColumn()===0, 'contadores no contienen correos ni IP en claro');

    $pdo->exec('DELETE FROM acceso_intento');
    $weak=$form; $weak['email']=$prefix.'-new@example.invalid'; $weak['password']='short';
    $probe->request('public/carrito.php',$add);
    $body=$probe->request('public/checkout.php',$weak);
    check(strpos($body,'Confirmar pedido')===false && strpos($body,'15 caracteres')!==false, 'nueva cuenta exige una frase de al menos 15 caracteres');
    check(!customer_valid_new_password(str_repeat('a',73)) && !customer_valid_new_password(str_repeat('á',37)) && customer_valid_new_password(str_repeat('á',15)), 'limite de bcrypt contempla bytes y caracteres Unicode');
    check(!customer_valid_new_password("contraseña\0ficticia") && !customer_valid_new_password("invalid\xFFunicode"), 'nuevas contraseñas rechazan NUL y UTF-8 invalido');
    echo "PASS $checks comprobaciones de cuenta y privacidad\n";
} finally {
    if ($orders) {
        $ids=implode(',',array_map('intval',$orders));
        $pdo->exec('DELETE FROM linea_pedido WHERE id_pedido IN ('.$ids.')');
        $pdo->exec('DELETE FROM pedido WHERE id IN ('.$ids.')');
    }
    foreach ($customers as $id) {
        $pdo->prepare('DELETE FROM cliente_registrado WHERE id_cliente=?')->execute([$id]);
        $pdo->prepare('DELETE FROM cliente WHERE id=?')->execute([$id]);
    }
    $pdo->exec('DELETE FROM acceso_intento');
}
