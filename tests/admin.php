<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
require_once __DIR__ . '/../tienda/src/lib/admin.php';
require_once __DIR__ . '/../tienda/src/lib/customer.php';

$adminPassword = getenv('TIENDA_TEST_ADMIN_PASS') ?: '';
if ($adminPassword === '') { throw new RuntimeException('Define TIENDA_TEST_ADMIN_PASS para probar el panel'); }
// Configuración del proceso de pruebas para las comprobaciones del validador.
putenv('TIENDA_ADMIN_PASS='.$adminPassword);
$email = 'admin-suite-' . bin2hex(random_bytes(5)) . '@example.invalid';
$customerPassword = 'fictitious-customer-password';
$customerId = null;
$orderId = null;
$keys = admin_attempt_keys('127.0.0.1');
$accountKey = array_key_first($keys);
$ipKey = array_keys($keys)[1];
$login = function (TestBrowser $b, string $user, string $pass): string {
    $b->request('admin/login.php');
    return $b->request('admin/login.php',['user'=>$user,'pass'=>$pass]);
};
try {
    $pdo->exec('DELETE FROM acceso_intento');
    $pdo->prepare('INSERT INTO cliente(email,nombre) VALUES (?,?)')->execute([$email,'Cliente ficticio']);
    $customerId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO cliente_registrado(id_cliente,password_hash,direccion,cp,poblacion,pais) VALUES (?,?,?,?,?,?)')
        ->execute([$customerId,password_hash($customerPassword,PASSWORD_DEFAULT),'Prueba','00000','Prueba','Prueba']);
    $pdo->prepare('INSERT INTO pedido(id_cliente,email,nombre_envio,direccion_envio,cp_envio,poblacion_envio,pais_envio) VALUES (?,?,?,?,?,?,?)')
        ->execute([$customerId,$email,'<script>cliente</script>','<script>direccion</script>','00000','Prueba','Prueba']);
    $orderId=(int)$pdo->lastInsertId();
    $b=new TestBrowser();
    foreach (['pedidos.php','pedido.php?id='.$orderId] as $path) {
        $b->request('admin/'.$path);
        check($b->status===302 && ($b->headers['location']??'')==='login.php', 'panel privado exige acceso: '.$path);
    }
    $b->request('admin/login.php');
    check(strpos($b->headers['cache-control']??'', 'no-store')!==false, 'login administrativo sin cache');
    $b->request('admin/login.php',['user'=>'admin','pass'=>$adminPassword],false);
    check($b->status===403, 'login administrativo rechaza CSRF ausente');
    foreach ([['user'=>['admin'],'pass'=>'x'],['user'=>'admin','pass'=>['x']],['user'=>'admin','pass'=>"x\0y"]] as $invalid) {
        $b->request('admin/login.php',$invalid);
        check($b->status===422, 'formulario administrativo malformado rechazado');
    }
    $wrong=$login($b,'admin','incorrect-password');
    check($b->status===401 && strpos($wrong,'Credenciales incorrectas.')!==false, 'password incorrecto devuelve error generico');
    $unknown=$login($b,'unknown',$adminPassword);
    check($b->status===401 && strpos($unknown,'Credenciales incorrectas.')!==false, 'usuario incorrecto devuelve el mismo error');

    $b->request('public/acceso.php');
    $b->request('public/acceso.php',['email'=>$email,'password'=>$customerPassword]);
    check($b->status===303, 'cliente ficticio accede antes de abrir el panel');
    $variant=$pdo->query('SELECT * FROM producto_variante WHERE activo=1 AND stock>0 LIMIT 1')->fetch();
    $b->request('public/cuenta.php');
    $b->request('public/carrito.php',['action'=>'add','id_producto'=>$variant['id_producto'],'id_variante'=>$variant['id'],'cantidad'=>1]);
    $publicOnly=new TestBrowser();
    $publicOnly->request('public/acceso.php');
    $publicOnly->request('public/acceso.php',['email'=>$email,'password'=>$customerPassword]);
    $publicOnly->request('admin/pedidos.php');
    check($publicOnly->status===302, 'autenticacion de cliente no concede acceso administrativo');
    $b->request('admin/login.php');
    $oldToken=$b->token;
    $before=$b->copySession();
    $b->request('admin/login.php',['user'=>'admin','pass'=>$adminPassword]);
    check($b->status===302 && ($b->headers['location']??'')==='index.php', 'administrador accede con credenciales validas');
    $cookie=$b->headers['set-cookie']??'';
    $expectedPath=rtrim((string)parse_url($base,PHP_URL_PATH),'/').'/admin/';
    check(strpos($cookie,'TIENDAADMIN=')===0 && strpos($cookie,'path='.$expectedPath)!==false && stripos($cookie,'HttpOnly')!==false && stripos($cookie,'SameSite=Lax')!==false, 'cookie administrativa distinta, acotada a admin y protegida');
    $before->request('admin/pedidos.php');
    check($before->status===302, 'cookie anterior al acceso no autoriza el panel');
    $body=$b->request('admin/pedidos.php');
    check($b->status===200 && strpos($body,'&lt;script&gt;cliente&lt;/script&gt;')!==false, 'listado autorizado escapa datos de clientes');
    check(strpos($b->headers['cache-control']??'', 'no-store')!==false, 'listado de pedidos sin cache');
    check($b->token!==$oldToken, 'acceso administrativo renueva el token CSRF');
    $b->request('admin/logout.php',['csrf_token'=>$oldToken],false);
    check($b->status===403, 'token anterior al acceso no permite cerrar la nueva sesion');
    $body=$b->request('admin/pedido.php?id='.$orderId);
    check($b->status===200 && strpos($body,'&lt;script&gt;direccion&lt;/script&gt;')!==false, 'detalle autorizado escapa la direccion');
    check(strpos($b->headers['cache-control']??'', 'no-store')!==false, 'detalle de pedido sin cache');
    foreach (['id[]=1','id=-1','id=2147483647','id=abc'] as $query) {
        $b->request('admin/pedido.php?'.$query);
        check($b->status===404, 'detalle administrativo invalido o inexistente devuelve 404');
    }
    $b->request('public/cuenta.php');
    check($b->status===200, 'acceso administrativo conserva sesion de cliente');
    $b->request('public/salir.php',[]);
    $b->request('admin/pedidos.php');
    check($b->status===200, 'cerrar sesion de cliente no cierra la administracion');
    $b->request('admin/logout.php');
    check($b->status===405, 'GET no cierra sesion administrativa');
    $b->request('admin/logout.php',[],false);
    check($b->status===403, 'salida administrativa requiere CSRF');
    $b->request('admin/pedidos.php');
    check($b->status===200, 'salida invalida conserva la autorizacion');
    $b->request('public/acceso.php');
    $b->request('public/acceso.php',['email'=>$email,'password'=>$customerPassword]);
    $b->request('admin/pedidos.php');
    $beforeLogout=$b->copySession();
    $b->request('admin/logout.php',[]);
    check($b->status===302, 'salida administrativa mediante POST valido');
    $b->request('admin/pedidos.php'); $beforeLogout->request('admin/pedidos.php');
    check($b->status===302 && $beforeLogout->status===302, 'salida invalida cookie administrativa actual y anterior');
    $b->request('public/cuenta.php');
    check($b->status===200, 'cerrar administracion conserva la cuenta de cliente');
    $body=$b->request('public/carrito.php');
    check(strpos($body,'name="remove"')!==false, 'cerrar administracion conserva el carrito');

    $state=['proof'=>admin_proof(),'started'=>time(),'last_active'=>time()];
    $_SESSION=['admin_logged'=>true];
    check(!admin_current(), 'indicador de sesion antigua no autoriza');
    $_SESSION=['admin'=>array_replace($state,['last_active'=>time()-901])];
    check(!admin_current() && !isset($_SESSION['admin']), '15 minutos de inactividad invalidan administracion');
    $_SESSION=['admin'=>array_replace($state,['started'=>time()-14401])];
    check(!admin_current(), 'sesion administrativa supera cuatro horas');
    $originalPassword=getenv('TIENDA_ADMIN_PASS');
    $originalSecret=getenv('TIENDA_AUTH_SECRET');
    try {
        $_SESSION=['admin'=>$state];
        putenv('TIENDA_ADMIN_PASS=changed-fictitious-admin');
        check(!admin_current(), 'rotar password invalida sesion administrativa');
        putenv('TIENDA_ADMIN_PASS='.$originalPassword);
        $_SESSION=['admin'=>$state];
        putenv('TIENDA_AUTH_SECRET=changed-fictitious-key-with-at-least-32-bytes');
        check(!admin_current(), 'rotar secreto invalida sesion administrativa');
        $_SESSION=['admin'=>$state];
        putenv('TIENDA_AUTH_SECRET');
        check(!admin_current(), 'configuracion incompleta no autoriza sesion previa');
    } finally {
        putenv('TIENDA_ADMIN_PASS='.$originalPassword);
        putenv('TIENDA_AUTH_SECRET='.$originalSecret);
    }

    $pdo->exec('DELETE FROM acceso_intento');
    for ($i=0;$i<5;++$i) { $probe=new TestBrowser(); $login($probe,'user-'.$i,'incorrect-password'); }
    $probe=new TestBrowser(); $login($probe,'admin',$adminPassword);
    check($probe->status===429 && ($probe->headers['retry-after']??'')==='900', 'limite administrativo resiste nuevas cookies y usuarios distintos');
    $client=new TestBrowser(); $client->request('public/acceso.php');
    $client->request('public/acceso.php',['email'=>$email,'password'=>$customerPassword]);
    check($client->status===303, 'bloqueo administrativo no bloquea cuenta de cliente');
    $pdo->exec('UPDATE acceso_intento SET ventana=UTC_TIMESTAMP()-INTERVAL 16 MINUTE');
    $login($probe,'admin',$adminPassword);
    check($probe->status===302, 'ventana vencida permite acceso administrativo');
    $q=$pdo->prepare('SELECT intentos FROM acceso_intento WHERE clave=?');
    $q->execute([$accountKey]);
    check($q->fetchColumn()===false, 'acceso correcto borra contador administrativo de cuenta');
    $q->execute([$ipKey]);
    check((int)$q->fetchColumn()===1, 'acceso correcto conserva contador administrativo por IP');
    $pdo->prepare('UPDATE acceso_intento SET intentos=30 WHERE clave=?')->execute([$ipKey]);
    $probe=new TestBrowser(); $login($probe,'admin',$adminPassword);
    check($probe->status===429, 'limite por IP rechaza acceso administrativo');
    $pdo->exec('DELETE FROM acceso_intento');
    foreach (customer_attempt_keys($email,'127.0.0.1') as $key=>$limit) {
        $pdo->prepare('INSERT INTO acceso_intento(clave,ventana,intentos) VALUES (?,UTC_TIMESTAMP(),?)')->execute([$key,$limit]);
    }
    $probe=new TestBrowser(); $login($probe,'admin',$adminPassword);
    check($probe->status===302, 'bloqueo de clientes no bloquea administracion');

    $pdo->exec('DELETE FROM acceso_intento');
    $pdo->prepare('INSERT INTO acceso_intento(clave,ventana,intentos) VALUES (?,UTC_TIMESTAMP(),4)')->execute([$accountKey]);
    $pairs=[new TestBrowser(),new TestBrowser()];
    $multi=curl_multi_init(); $handles=[];
    try {
        foreach ($pairs as $browser) {
            $browser->request('admin/login.php');
            $handle=$browser->prepare('admin/login.php',['user'=>'admin','pass'=>'incorrect-password']);
            $handles[]=$handle; curl_multi_add_handle($multi,$handle);
        }
        do { curl_multi_exec($multi,$running); if ($running) { curl_multi_select($multi,1.0); } } while ($running);
        foreach ($handles as $i=>$handle) { $pairs[$i]->receive(curl_multi_getcontent($handle)); }
        $statuses=[$pairs[0]->status,$pairs[1]->status]; sort($statuses);
        $q->execute([$accountKey]);
        $attempts=(int)$q->fetchColumn();
        check($statuses===[401,429] && $attempts===5, 'dos peticiones no consumen dos plazas cuando solo queda una: HTTP '.implode(',', $statuses).'; contador '.$attempts);
    } finally {
        foreach ($handles as $handle) { curl_multi_remove_handle($multi,$handle); }
        curl_multi_close($multi);
    }
    check((int)$pdo->query("SELECT COUNT(*) FROM acceso_intento WHERE clave NOT REGEXP '^[a-f0-9]{64}$'")->fetchColumn()===0, 'contadores administrativos no contienen usuario ni IP en claro');
    echo "PASS $checks comprobaciones administrativas\n";
} finally {
    if ($orderId) { $pdo->prepare('DELETE FROM pedido WHERE id=?')->execute([$orderId]); }
    if ($customerId) {
        $pdo->prepare('DELETE FROM cliente_registrado WHERE id_cliente=?')->execute([$customerId]);
        $pdo->prepare('DELETE FROM cliente WHERE id=?')->execute([$customerId]);
    }
    $pdo->exec('DELETE FROM acceso_intento');
}
