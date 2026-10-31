<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';

function favoriteRaceConnection(): PDO {
    global $dsn;
    return new PDO($dsn, getenv('TIENDA_TEST_USER') ?: 'root', getenv('TIENDA_TEST_PASS') ?: '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
}

// Barrera observada en InnoDB: las dos peticiones deben esperar realmente la misma cuenta.
function favoriteRace(array $browsers, array $posts, int $customerId, ?callable $beforeRelease = null): void {
    $guard=favoriteRaceConnection(); $observer=favoriteRaceConnection();
    $guard->beginTransaction();
    $guard->query('SELECT id_cliente FROM cliente_registrado WHERE id_cliente=' . $customerId . ' FOR UPDATE')->fetch();
    $table='`' . $observer->query('SELECT DATABASE()')->fetchColumn() . '`.`cliente_registrado`';
    $multi=curl_multi_init(); $handles=[]; $active=[]; $waiting=0;
    try {
        foreach ($browsers as $i=>$browser) { $handles[]=$browser->prepare('public/favoritos.php',$posts[$i]+['scope'=>'account']); }
        curl_multi_add_handle($multi,$handles[0]); $active[]=$handles[0];
        $deadline=microtime(true)+12; $observe=0;
        do {
            if (curl_multi_exec($multi,$running)!==CURLM_OK) { throw new RuntimeException('Error de concurrencia cURL'); }
            if ($guard->inTransaction() && microtime(true)>=$observe) {
                $observe=microtime(true)+.25;
                $waiting=(int)$observer->query('SELECT COUNT(DISTINCT w.requesting_trx_id) FROM information_schema.INNODB_LOCK_WAITS w
                    JOIN information_schema.INNODB_LOCKS l ON l.lock_id=w.requested_lock_id WHERE l.lock_table=' . $observer->quote($table) .
                    " AND l.lock_index='PRIMARY' AND l.lock_data=" . $observer->quote((string)$customerId))->fetchColumn();
                if ($waiting>=count($active) && count($active)<count($handles)) {
                    $next=$handles[count($active)]; curl_multi_add_handle($multi,$next); $active[]=$next;
                } elseif ($waiting>=count($browsers)) {
                    if ($beforeRelease!==null) { $beforeRelease($guard); }
                    $guard->commit();
                }
            }
            if (microtime(true)>$deadline) { throw new RuntimeException('Falta concurrencia real o permiso PROCESS en la base de pruebas'); }
            if ($running && curl_multi_select($multi,.05)===-1) { usleep(10000); }
        } while ($running);
        check($waiting>=2,'dos sesiones esperan simultaneamente el bloqueo de favoritos');
        foreach ($browsers as $i=>$browser) { $browser->receive(curl_multi_getcontent($handles[$i])); }
    } finally {
        if ($guard->inTransaction()) { $guard->rollBack(); }
        $deadline=microtime(true)+21;
        do { curl_multi_exec($multi,$running); if ($running) { curl_multi_select($multi,.05); } } while ($running && microtime(true)<$deadline);
        foreach ($active as $handle) { curl_multi_remove_handle($multi,$handle); }
        curl_multi_close($multi);
    }
}

$prefix='account-favorites-' . bin2hex(random_bytes(5));
$password='fictitious-saved-account-password'; $hash=password_hash($password,PASSWORD_DEFAULT);
$customers=[]; $products=[]; $category=null; $brand=null; $failureTrigger=false;
$cards=static function(string $body): array {
    preg_match_all('/<article class="card catalog-product">.*?producto.php\?p=([^"&]+).*?<\/article>/s',$body,$m);
    return $m[1];
};
$login=static function(TestBrowser $browser,int $account) use ($prefix,$password): void {
    $browser->request('public/acceso.php');
    $browser->request('public/acceso.php',['email'=>$prefix.'-'.$account.'@example.invalid','password'=>$password]);
    if ($browser->status!==303) { throw new RuntimeException('No se pudo preparar el acceso de pruebas'); }
    $browser->request('public/cuenta.php');
};
$stored=static function(int $customer) use ($pdo): array {
    $q=$pdo->prepare('SELECT id_producto FROM cliente_favorito WHERE id_cliente=? ORDER BY posicion');$q->execute([$customer]);
    return array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
};
$fill=static function(int $customer,array $ids) use ($pdo): void {
    $pdo->prepare('DELETE FROM cliente_favorito WHERE id_cliente=?')->execute([$customer]);
    $q=$pdo->prepare('INSERT INTO cliente_favorito VALUES (?,?,?)');
    foreach ($ids as $position=>$id) { $q->execute([$customer,$id,$position]); }
};
$cookie=static function(TestBrowser $browser,array $ids): void {
    curl_setopt($browser->prepare('public/favoritos.php'),CURLOPT_COOKIELIST,"127.0.0.1\tFALSE\t/public/\tFALSE\t".(time()+3600)."\tperfume_favorites\t".implode(',',$ids));
};
try {
    $pdo->exec('DELETE FROM acceso_intento');
    foreach ([0,1] as $n) {
        $pdo->prepare('INSERT INTO cliente(email,nombre) VALUES (?,?)')->execute([$prefix.'-'.$n.'@example.invalid','Cliente ficticio']);
        $customers[]=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cliente_registrado(id_cliente,password_hash,direccion,cp,poblacion,pais) VALUES (?,?,?, ?,?,?)')
            ->execute([$customers[$n],$hash,'Direccion de prueba','00000','Prueba','Prueba']);
    }
    $pdo->prepare('INSERT INTO categoria(nombre,slug) VALUES (?,?)')->execute(['Favoritos de cuenta',$prefix]);$category=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute(['Marca ficticia',$prefix]);$brand=(int)$pdo->lastInsertId();
    for ($i=0;$i<26;++$i) {
        $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,precio,stock) VALUES (?,?,?,?,20,3)')
            ->execute([$category,$brand,'Perfume compartido '.$i,$prefix.'-'.$i]);$products[]=(int)$pdo->lastInsertId();
    }
    $a=new TestBrowser();$a2=new TestBrowser();$b=new TestBrowser();
    $a->request('public/catalogo.php');
    foreach (array_slice($products,0,3) as $id) { $a->request('public/favoritos.php',['action'=>'add','id_producto'=>(string)$id]); }
    $login($a,0);
    $body=$a->request('public/favoritos.php');
    check($cards($body)===[] && str_contains($body,'Unir favoritos a mi cuenta'),'iniciar sesion no importa favoritos sin elegirlo');
    check(str_contains($body,'Guardando en tu cuenta') && str_contains($body,'name="scope" value="account"'),'interfaz identifica destino cuenta');
    $a->request('public/favoritos.php',['action'=>'add','id_producto'=>(string)$products[3],'scope'=>'account']);
    check($a->status===303 && $stored($customers[0])===[$products[3]],'guarda en la cuenta autenticada');
    check(!str_contains($a->headers['set-cookie'] ?? '','perfume_favorites'),'no copia favoritos de cuenta a cookie');
    $login($a2,0);$body=$a2->request('public/favoritos.php');
    check($cards($body)===[$prefix.'-3'] && $a2->token!==$a->token,'otra sesion recupera la misma coleccion');
    $login($b,1);$body=$b->request('public/favoritos.php?id_cliente='.$customers[0]);
    check($cards($body)===[],'identificador ajeno en URL no revela su lista');
    $b->request('public/favoritos.php',['action'=>'add','id_producto'=>(string)$products[4],'scope'=>'account','id_cliente'=>$customers[0]]);
    check($stored($customers[0])===[$products[3]] && $stored($customers[1])===[$products[4]],'identificador ajeno en POST no altera el propietario');
    $a->request('public/favoritos.php',['action'=>'remove','id_producto'=>(string)$products[4],'scope'=>'account','id_cliente'=>$customers[1]]);
    check($stored($customers[1])===[$products[4]],'quitar un perfume ajeno no modifica otra cuenta');
    $a->request('public/favoritos.php',['action'=>'clear','scope'=>'account'],false);
    check($a->status===403 && $stored($customers[0])===[$products[3]],'escritura autenticada exige CSRF');
    $a->request('public/favoritos.php',['action'=>'clear','scope'=>'account','csrf_token'=>$b->token],false);
    check($a->status===403,'CSRF de otra sesion no autoriza favoritos');
    $a->request('public/favoritos.php',['action'=>'clear','scope'=>'browser']);
    check($a->status===409 && $stored($customers[0])===[$products[3]],'formulario de navegador no vacia cuenta tras cambiar sesion');
    $a->request('public/favoritos.php',['action'=>'clear','scope'=>['account']]);
    check($a->status===422,'ambito no escalar rechazado');
    $a->request('public/favoritos.php',['action'=>'add','id_producto'=>(string)$products[1],'scope'=>'account']);
    $pdo->prepare('UPDATE producto SET activo=0 WHERE id=?')->execute([$products[2]]);
    $a->request('public/favoritos.php',['action'=>'add','id_producto'=>(string)$products[2],'scope'=>'account']);
    check($a->status===404 && $stored($customers[0])===[$products[1],$products[3]],'alta directa rechaza producto retirado sin modificar la lista');
    $a->request('public/favoritos.php',['action'=>'merge','scope'=>'account']);
    check($a->status===303 && $stored($customers[0])===[$products[0],$products[1],$products[3]],'union importa activos sin duplicados y conserva orden previo');
    check(str_contains($a->headers['set-cookie'] ?? '','Max-Age=0'),'union confirmada vacia cookie invitada');
    $a->request('public/favoritos.php',['action'=>'merge','scope'=>'account']);
    check(count($stored($customers[0]))===3,'repetir union es idempotente');
    $body=$a2->request('public/favoritos.php');
    check($cards($body)===[$prefix.'-0',$prefix.'-1',$prefix.'-3'],'segunda sesion ve cambios al recargar');
    check(str_contains($b->headers['cache-control'] ?? '','no-store'),'colecciones de cuenta sin cache compartida');
    $body=$a2->request('public/producto.php?p='.$prefix.'-0');
    check(str_contains($body,'Guardado · quitar') && str_contains($body,'name="scope" value="account"'),'ficha muestra estado sincronizado');
    $body=$a2->request('public/cuenta.php');
    check(str_contains($body,'Ver mis favoritos (3)'),'cuenta enlaza la coleccion y su contador');
    $pdo->prepare('UPDATE producto SET precio=37.50,stock=0 WHERE id=?')->execute([$products[0]]);
    $body=$a2->request('public/favoritos.php');
    check(str_contains($body,'37,50 €') && str_contains($body,'>Agotado<'),'cuenta relee precios y stock');
    $pdo->prepare('UPDATE categoria SET activa=0 WHERE id=?')->execute([$category]);
    $body=$a2->request('public/favoritos.php');
    check($cards($body)===[],'cuenta oculta categorias retiradas');
    $pdo->prepare('UPDATE categoria SET activa=1 WHERE id=?')->execute([$category]);
    $pdo->prepare('UPDATE producto SET activo=1 WHERE id=?')->execute([$products[2]]);
    $a->request('public/salir.php',[]);
    $body=$a->request('public/favoritos.php');
    check($cards($body)===[] && str_contains($body,'Guardando en este navegador'),'cerrar sesion no revela favoritos de cuenta');
    $a->request('public/catalogo.php');
    $a->request('public/favoritos.php',['action'=>'clear','scope'=>'account']);
    check($a->status===403 && count($stored($customers[0]))===3,'sesion ausente no aplica formulario antiguo a cookie');
    $a->request('public/favoritos.php',['action'=>'merge','scope'=>'account']);
    check($a->status===403,'union exige cuenta autenticada');
    $login($a,1);$body=$a->request('public/favoritos.php');
    check($cards($body)===[$prefix.'-4'],'cambio de usuario en mismo navegador mantiene aislamiento');
    $a->request('public/salir.php',[]);$login($a,0);
    $fill($customers[0],[$products[0]]);
    $cookie($a,[$products[15],$products[16]]);
    $pdo->exec('CREATE TRIGGER favorite_failure_test BEFORE INSERT ON cliente_favorito FOR EACH ROW BEGIN IF NEW.id_cliente=' . $customers[0] . ' AND NEW.id_producto=' . $products[15] . " THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo ficticio'; END IF; END");
    $failureTrigger=true;
    $body=$a->request('public/favoritos.php',['action'=>'merge','scope'=>'account']);
    check($a->status===503 && $stored($customers[0])===[$products[0]] && !str_contains($a->headers['set-cookie'] ?? '','perfume_favorites'),'fallo SQL durante union revierte borrado y conserva cookie');
    check(!str_contains($body,'Fallo ficticio') && str_contains($body,'No pudimos confirmar'),'error de almacenamiento no expone detalles SQL');
    $pdo->exec('DROP TRIGGER favorite_failure_test');$failureTrigger=false;
    $a->request('public/favoritos.php',['action'=>'merge','scope'=>'account']);
    check($a->status===303 && $stored($customers[0])===[$products[15],$products[16],$products[0]],'reintento tras fallo recupera union completa');
    $fill($customers[0],array_slice($products,0,24));
    $cookie($a,[$products[24],$products[25]]);
    $before=$stored($customers[0]);
    $a->request('public/favoritos.php',['action'=>'merge','scope'=>'account']);
    check($a->status===409 && $stored($customers[0])===$before && !str_contains($a->headers['set-cookie'] ?? '','perfume_favorites'),'union excesiva conserva cuenta y cookie');
    $body=$a->request('public/favoritos.php');
    check(str_contains($body,'Unir favoritos a mi cuenta'),'union rechazada permite reintentarlo');
    $a->request('public/favoritos.php',['action'=>'remove','id_producto'=>(string)$products[23],'scope'=>'account']);
    $a->request('public/favoritos.php',['action'=>'remove','id_producto'=>(string)$products[22],'scope'=>'account']);
    $a->request('public/favoritos.php',['action'=>'merge','scope'=>'account']);
    check($a->status===303 && count($stored($customers[0]))===24 && array_slice($stored($customers[0]),0,2)===[$products[24],$products[25]],'union funciona al liberar espacio');
    $a->request('public/favoritos.php?action=clear');
    check(count($stored($customers[0]))===24,'GET no modifica cuenta');
    $pdo->exec(file_get_contents(__DIR__.'/../tienda/sql/migrations/003_customer_favorites.sql'));
    check(count($stored($customers[0]))===24 && $stored($customers[1])===[$products[4]],'repetir migracion conserva colecciones');
    $fill($customers[0],array_slice($products,0,23));
    favoriteRace([$a,$a2],[['action'=>'add','id_producto'=>(string)$products[23]],['action'=>'add','id_producto'=>(string)$products[24]]],$customers[0]);
    $statuses=[$a->status,$a2->status];sort($statuses);
    check($statuses===[303,409] && count($stored($customers[0]))===24,'dos dispositivos no superan el limite de 24');
    $fill($customers[0],array_slice($products,0,22));
    favoriteRace([$a,$a2],[['action'=>'add','id_producto'=>(string)$products[23]],['action'=>'add','id_producto'=>(string)$products[24]]],$customers[0]);
    check($a->status===303 && $a2->status===303 && count($stored($customers[0]))===24 && in_array($products[23],$stored($customers[0]),true) && in_array($products[24],$stored($customers[0]),true),'altas concurrentes conservan ambos cambios');
    $fill($customers[0],array_slice($products,0,23));
    favoriteRace([$a,$a2],[['action'=>'add','id_producto'=>(string)$products[23]],['action'=>'add','id_producto'=>(string)$products[23]]],$customers[0]);
    check($a->status===303 && $a2->status===303 && count($stored($customers[0]))===24,'alta duplicada concurrente no duplica ni produce conflicto');
    $before=$stored($customers[0]);
    favoriteRace([$a,$a2],[['action'=>'clear'],['action'=>'remove','id_producto'=>(string)$products[0]]],$customers[0],static function(PDO $guard) use($customers):void {
        $guard->prepare('UPDATE cliente_registrado SET password_hash=? WHERE id_cliente=?')->execute([password_hash('another-fictitious-password',PASSWORD_DEFAULT),$customers[0]]);
    });
    check($a->status===403 && $a2->status===403 && $stored($customers[0])===$before,'revocacion durante espera no autoriza cambios antiguos');
    $pdo->prepare('UPDATE cliente_registrado SET password_hash=? WHERE id_cliente=?')->execute([$hash,$customers[0]]);
    $login($a,0);
    $a->request('public/favoritos.php',['action'=>'clear','scope'=>'account']);
    check($a->status===303 && $stored($customers[0])===[] && $stored($customers[1])===[$products[4]],'vaciar solo borra la cuenta actual');
    $fill($customers[0],[$products[25]]);
    $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$products[25]]);
    check($stored($customers[0])===[],'borrar producto elimina referencia sin huerfanos');
    $pdo->prepare('DELETE FROM cliente_registrado WHERE id_cliente=?')->execute([$customers[1]]);
    check($stored($customers[1])===[],'borrar cuenta elimina sus favoritos');
    echo "PASS $checks comprobaciones de favoritos de cuenta\n";
} finally {
    if ($failureTrigger) { $pdo->exec('DROP TRIGGER IF EXISTS favorite_failure_test'); }
    foreach ($customers as $id) {
        $pdo->prepare('DELETE FROM cliente_registrado WHERE id_cliente=?')->execute([$id]);
        $pdo->prepare('DELETE FROM cliente WHERE id=?')->execute([$id]);
    }
    foreach ($products as $id) { $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$id]); }
    if ($category!==null) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$category]); }
    if ($brand!==null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
    $pdo->exec('DELETE FROM acceso_intento');
}
