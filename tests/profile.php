<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
require_once __DIR__ . '/../tienda/src/lib/profile.php';
$prefix='perfil-'.bin2hex(random_bytes(5));
$emails=[$prefix.'@example.invalid',$prefix.'-other@example.invalid'];
$password='fictitious-profile-password'; $nextPassword='new-fictitious-profile-password';
$customers=[]; $orders=[];
$variant=$pdo->query('SELECT * FROM producto_variante WHERE activo=1 AND stock>2 LIMIT 1')->fetch();
$add=['action'=>'add','id_producto'=>$variant['id_producto'],'id_variante'=>$variant['id'],'cantidad'=>1];
$fields=['nombre'=>'Cliente de prueba','direccion'=>'Calle de prueba 1','cp'=>'00000','poblacion'=>'Prueba','provincia'=>'','pais'=>'España'];
$login=static function(TestBrowser $b,string $email,string $pass): void {
    $b->request('public/acceso.php'); $b->request('public/acceso.php',['email'=>$email,'password'=>$pass]);
    check($b->status===303,'acceso de prueba correcto');
    $b->request('public/cuenta.php');
};
$version=static function(string $html): string {
    if (!preg_match('/name="profile_version" value="([a-f0-9]{64})"/',$html,$m)) { throw new RuntimeException('Falta la versión del perfil'); }
    return $m[1];
};
$stored=static function(int $id) use ($pdo): array {
    $q=$pdo->prepare('SELECT c.id,c.email,c.nombre,cr.* FROM cliente c JOIN cliente_registrado cr ON cr.id_cliente=c.id WHERE c.id=?');
    $q->execute([$id]); return $q->fetch();
};
try {
    $pdo->exec('DELETE FROM acceso_intento');
    foreach($emails as $email) {
        $pdo->prepare('INSERT INTO cliente(email,nombre) VALUES (?,?)')->execute([$email,$fields['nombre']]);
        $id=(int)$pdo->lastInsertId(); $customers[]=$id;
        $pdo->prepare('INSERT INTO cliente_registrado(id_cliente,password_hash,direccion,cp,poblacion,provincia,pais) VALUES (?,?,?,?,?,NULL,?)')
            ->execute([$id,password_hash($password,PASSWORD_DEFAULT),$fields['direccion'],$fields['cp'],$fields['poblacion'],$fields['pais']]);
    }
    $b=new TestBrowser(); $other=new TestBrowser(); $device=new TestBrowser();
    $b->request('public/perfil.php');
    check($b->status===302 && ($b->headers['location']??'')==='acceso.php','perfil requiere una cuenta');
    $login($b,$emails[0],$password); $login($device,$emails[0],$password); $login($other,$emails[1],$password);
    $original=$stored($customers[0]); $foreign=$stored($customers[1]);
    $body=$b->request('public/perfil.php'); $v=$version($body);
    check($b->status===200 && strpos($body,'value="Calle de prueba 1"')!==false,'perfil recupera dirección habitual');
    check(strpos($b->headers['cache-control']??'','no-store')!==false && strpos($b->headers['x-robots-tag']??'','noindex')!==false,'perfil privado sin caché ni indexación');
    check(strpos($body,$original['password_hash'])===false && strpos($body,$password)===false,'HTML no contiene contraseña ni hash');
    $payload=$fields+['action'=>'profile','profile_version'=>$v];
    $b->request('public/perfil.php',$payload,false);
    check($b->status===403 && $stored($customers[0])===$original,'rechaza edición sin CSRF y conserva datos');
    $forged=$payload; $forged['csrf_token']=$other->token;
    $b->request('public/perfil.php',$forged,false);
    check($b->status===403,'rechaza CSRF de otra sesión');
    foreach (['action'=>['profile'],'profile_version'=>['x'],'nombre'=>['x'],'direccion'=>"Calle\nDos",'cp'=>"bad\xFF",'pais'=>'','poblacion'=>str_repeat('é',121)] as $key=>$value) {
        $bad=$payload; $bad[$key]=$value; $body=$b->request('public/perfil.php',$bad);
        check($b->status===422 && $stored($customers[0])===$original,'rechaza campo inválido sin guardar: '.$key);
    }
    $new=$fields; $new['nombre']='Lucía de prueba'; $new['direccion']='  Avenida ficticia 2  ';
    $good=$new+['action'=>'profile','profile_version'=>$v,'id_cliente'=>$customers[1],'email'=>$emails[1]];
    $b->request('public/perfil.php',$good);
    check($b->status===303 && ($b->headers['location']??'')==='perfil.php','guardar usa redirección para evitar reenvío');
    $saved=$stored($customers[0]);
    check($saved['nombre']===$new['nombre'] && $saved['direccion']==='Avenida ficticia 2' && $saved['provincia']===null,'guarda Unicode, recorta espacios y admite provincia vacía');
    check($saved['email']===$emails[0] && $stored($customers[1])===$foreign,'identidad y correo salen de la sesión, nunca del formulario');
    $body=$b->request('public/perfil.php'); $fresh=$version($body);
    check(strpos($body,'Datos guardados.')!==false,'muestra confirmación después de guardar');
    check(strpos($b->request('public/perfil.php'),'Datos guardados.')===false,'confirmación se consume una vez');
    $device->request('public/perfil.php',$payload);
    check($device->status===409 && $stored($customers[0])===$saved,'otro dispositivo con versión antigua no sobrescribe');
    $foreignVersion=$version($other->request('public/perfil.php'));
    $other->request('public/perfil.php',array_replace($payload,['profile_version'=>$fresh]));
    check($other->status===409 && $stored($customers[1])===$foreign,'versión de una cuenta no autoriza otra');

    // Un fallo entre ambas tablas debe revertir también el nombre.
    $pdo->exec("CREATE TRIGGER profile_test_fail BEFORE UPDATE ON cliente_registrado FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture profile failure'");
    $body=$b->request('public/perfil.php',array_replace($payload,['profile_version'=>$fresh,'nombre'=>'No debe guardarse']));
    check($b->status===503 && $stored($customers[0])===$saved && strpos($body,'fixture profile failure')===false,'fallo SQL revierte ambas tablas sin revelar detalles');
    $pdo->exec('DROP TRIGGER profile_test_fail');

    $b->request('public/carrito.php',$add);
    $body=$b->request('public/checkout.php');
    check(strpos($body,'value="Avenida ficticia 2"')!==false,'checkout precarga el perfil actualizado');
    $checkout=$fields+['step'=>'form','tipo'=>'registrado','email'=>$emails[0]];
    $checkout['nombre']='Destinatario de prueba'; $checkout['direccion']='Destino puntual 3';
    $body=$b->request('public/checkout.php',$checkout);
    check(strpos($body,'Confirmar pedido')!==false,'permite elegir dirección diferente para un pedido');
    $b->request('public/perfil.php',array_replace($payload,['profile_version'=>$fresh,'direccion'=>'Habitual nueva 4']));
    $saved=$stored($customers[0]);
    $b->request('public/checkout.php',['step'=>'confirm']);
    check($b->status===302 && (int)$pdo->query('SELECT COUNT(*) FROM pedido WHERE id_cliente='.$customers[0])->fetchColumn()===0,'editar perfil invalida el resumen pendiente de esta sesión');
    $b->request('public/checkout.php',$checkout); $body=$b->request('public/checkout.php',['step'=>'confirm']);
    $q=$pdo->prepare('SELECT * FROM pedido WHERE id_cliente=?'); $q->execute([$customers[0]]); $order=$q->fetch();
    if ($order) { $orders[]=(int)$order['id']; }
    check($b->status===200 && $order && $order['direccion_envio']==='Destino puntual 3','pedido guarda su propia dirección');
    check($stored($customers[0])===$saved,'comprar no sobrescribe la dirección habitual ni el nombre');
    $body=$b->request('public/perfil.php'); $fresh=$version($body);
    $unsafe='"><script>fixture</script>';
    $b->request('public/perfil.php',array_replace($payload,['profile_version'=>$fresh,'nombre'=>$unsafe]));
    $body=$b->request('public/perfil.php');
    check(strpos($body,'&lt;script&gt;fixture&lt;/script&gt;')!==false && strpos($body,'<script>fixture')===false,'escapa datos al mostrarlos en el perfil');
    $q->execute([$customers[0]]);
    check($q->fetch()===$order,'editar perfil conserva íntegros los pedidos anteriores');

    $change=['action'=>'password','current_password'=>$password,'password'=>$nextPassword,'password2'=>$nextPassword];
    $hash=$stored($customers[0])['password_hash'];
    foreach ([['current_password',['x']],['password','corta'],['password2','otra'],['password',str_repeat('é',37)]] as [$key,$value]) {
        $bad=$change; $bad[$key]=$value;
        $body=$b->request('public/perfil.php',$bad);
        check($b->status===422 && $stored($customers[0])['password_hash']===$hash,'rechaza contraseña malformada: '.$key);
        check(strpos($body,'value="'.$password.'"')===false && strpos($body,$nextPassword)===false,'errores no devuelven las contraseñas en HTML');
    }
    $b->request('public/perfil.php',array_replace($change,['current_password'=>'wrong-fictitious-password']));
    check($b->status===422 && $stored($customers[0])['password_hash']===$hash,'exige contraseña actual correcta');
    $b->request('public/perfil.php',array_replace($change,['password'=>$password,'password2'=>$password]));
    check($b->status===422 && $stored($customers[0])['password_hash']===$hash,'no admite reutilizar la contraseña actual');

    $token=bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO cliente_recuperacion(token_hash,id_cliente,expira) VALUES (?,?,UTC_TIMESTAMP()+INTERVAL 1 HOUR)')->execute([hash('sha256',$token),$customers[0]]);
    $pdo->exec("CREATE TRIGGER profile_reset_fail BEFORE DELETE ON cliente_recuperacion FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture reset failure'");
    $b->request('public/perfil.php',$change);
    check($b->status===503 && $stored($customers[0])['password_hash']===$hash,'si no se invalidan enlaces se revierte la contraseña');
    $pdo->exec('DROP TRIGGER profile_reset_fail');
    $pdo->exec('DELETE FROM acceso_intento');
    $b->request('public/carrito.php',$add);
    $b->request('public/checkout.php',$checkout);
    $device->request('public/checkout.php',$checkout);
    $pdo->prepare('INSERT INTO cliente_favorito(id_cliente,id_producto,posicion) VALUES (?,?,1)')->execute([$customers[0],$variant['id_producto']]);
    $oldCookie=$b->copySession(); $oldToken=$b->token;
    $b->request('public/perfil.php',$change);
    check($b->status===303 && password_verify($nextPassword,$stored($customers[0])['password_hash']),'cambia contraseña correctamente');
    $body=$b->request('public/perfil.php');
    check($b->status===200 && $b->token!==$oldToken && strpos($body,'Contraseña actualizada.')!==false,'renueva sesión y CSRF manteniendo acceso actual');
    $oldCookie->request('public/cuenta.php'); $device->request('public/cuenta.php');
    check($oldCookie->status===302 && $device->status===302,'cookie antigua y otro dispositivo dejan de autorizar');
    $other->request('public/cuenta.php'); check($other->status===200,'otra cuenta mantiene su sesión');
    $b->request('public/checkout.php',['step'=>'confirm']);
    check($b->status===302 && (int)$pdo->query('SELECT COUNT(*) FROM pedido WHERE id_cliente='.$customers[0])->fetchColumn()===1,'cambiar contraseña invalida resumen sin crear pedidos');
    $body=$b->request('public/carrito.php');
    check(strpos($body,'name="remove"')!==false && (int)$pdo->query('SELECT COUNT(*) FROM cliente_favorito WHERE id_cliente='.$customers[0])->fetchColumn()===1,'conserva carrito y favoritos');
    $probe=new TestBrowser(); $probe->request('public/restablecer.php?token='.$token);
    check($probe->status===410,'enlace de recuperación anterior queda invalidado');
    $probe->request('public/acceso.php'); $probe->request('public/acceso.php',['email'=>$emails[0],'password'=>$password]);
    check($probe->status===401,'contraseña anterior ya no permite acceder');
    $login($probe,$emails[0],$nextPassword);

    $pdo->exec('DELETE FROM acceso_intento');
    $bad=array_replace($change,['current_password'=>'wrong-fictitious-password','password'=>'another-fictitious-password','password2'=>'another-fictitious-password']);
    for($i=0;$i<5;++$i) { $b->request('public/perfil.php',$bad); check($b->status===422,'intento incorrecto contabilizado'); }
    $probe->request('public/perfil.php'); $probe->request('public/perfil.php',$bad);
    check($probe->status===429 && ($probe->headers['retry-after']??'')==='900','límite de cambio de contraseña compartido entre sesiones');
    $login($device,$emails[0],$nextPassword); $device->request('public/perfil.php',$bad);
    check($device->status===429,'volver a acceder no reinicia el límite de cambio');
    $pdo->exec('UPDATE acceso_intento SET ventana=UTC_TIMESTAMP()-INTERVAL 16 MINUTE');
    $device->request('public/perfil.php',$bad);
    check($device->status===422,'ventana vencida permite otro intento');
    $pdo->exec('DELETE FROM acceso_intento');
    $ipKey=array_keys(profile_password_keys(0,'127.0.0.1'))[1];
    $pdo->prepare('INSERT INTO acceso_intento(clave,ventana,intentos) VALUES (?,UTC_TIMESTAMP(),30)')->execute([$ipKey]);
    $other->request('public/perfil.php',$change);
    check($other->status===429,'límite por IP protege también otras cuentas');
    echo "PASS $checks comprobaciones de perfil y seguridad\n";
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS profile_test_fail'); $pdo->exec('DROP TRIGGER IF EXISTS profile_reset_fail');
    foreach($customers as $id) {
        $pdo->exec('DELETE FROM linea_pedido WHERE id_pedido IN (SELECT id FROM pedido WHERE id_cliente='.(int)$id.')');
        $pdo->exec('DELETE FROM pedido WHERE id_cliente='.(int)$id);
        $pdo->exec('DELETE FROM cliente_registrado WHERE id_cliente='.(int)$id);
        $pdo->exec('DELETE FROM cliente WHERE id='.(int)$id);
    }
    foreach($emails as $email) { $pdo->prepare('DELETE FROM correo_saliente WHERE destinatario=?')->execute([$email]); }
    $pdo->prepare('UPDATE producto_variante SET stock=? WHERE id=?')->execute([$variant['stock'],$variant['id']]);
    $pdo->exec('DELETE FROM acceso_intento');
}
