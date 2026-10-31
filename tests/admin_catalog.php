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

function catalogRace(array $browsers, array $requests, int $barrierProduct): array {
    $multi = curl_multi_init();
    $handles = [];
    $activeHandles = [];
    $guard = null;
    $observer = null;
    $waiting = 0;
    try {
        if ($barrierProduct !== null) {
            $guard = raceConnection();
            $guard->beginTransaction();
            $guard->query('SELECT id FROM producto WHERE id=' . $barrierProduct . ' FOR UPDATE')->fetch();
            $observer = raceConnection();
            $lockedTable = '`' . $observer->query('SELECT DATABASE()')->fetchColumn() . '`.`producto`';
        }
        foreach ($browsers as $i => $browser) {
            $handle = $browser->prepare($requests[$i][0], $requests[$i][1]);
            $handles[] = $handle;
            if ($barrierProduct === null || count($handles) === 1) {
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
                     " AND l.lock_index='PRIMARY' AND l.lock_data=" . $observer->quote((string)$barrierProduct)
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
        foreach ($activeHandles as $handle) { curl_multi_remove_handle($multi, $handle); }
        curl_multi_close($multi);
    }
}

$prefix = 'edit-catalog-' . bin2hex(random_bytes(5));
$password = getenv('TIENDA_TEST_ADMIN_PASS') ?: '';
if (!$password) { throw new RuntimeException('Define TIENDA_TEST_ADMIN_PASS'); }
$ids=[]; $category=null; $brand=null; $trigger=false;
$email=$prefix.'@example.invalid';
$login=static function() use ($password): TestBrowser {
    $b=new TestBrowser(); $b->request('admin/login.php'); $b->request('admin/login.php',['user'=>'admin','pass'=>$password]);
    if ($b->status!==302) { throw new RuntimeException('No se pudo autenticar el administrador de pruebas'); }
    $b->request('admin/catalogo.php');
    return $b;
};
$row=static function(int $id) use ($pdo): array { $q=$pdo->prepare('SELECT * FROM producto WHERE id=?');$q->execute([$id]);return $q->fetch(); };
$count=static function(int $id) use ($pdo): int { return (int)$pdo->query('SELECT COUNT(*) FROM catalogo_cambio WHERE id_producto='.$id)->fetchColumn(); };
$version=static function(string $html): string { preg_match('/name="version" value="([a-f0-9]+)"/',$html,$m);return $m[1] ?? ''; };
$form=static function(TestBrowser $b,int $id) use ($row,$pdo,$version): array {
    $data=$row($id); $data=array_map(static fn($v)=>(string)($v ?? ''),$data);
    $html=$b->request('admin/producto.php?id='.$id);
    if ($b->status!==200) { throw new RuntimeException('Editor no disponible: '.$b->status); }
    $data['version']=$version($html); $data['action']='save'; $data['variants']=[];
    foreach ($pdo->query('SELECT * FROM producto_variante WHERE id_producto='.$id.' ORDER BY id') as $v) { $data['variants'][$v['id']]=array_map(static fn($x)=>(string)($x ?? ''),$v); }
    return $data;
};
try {
    $pdo->exec('DELETE FROM acceso_intento');
    $pdo->prepare('INSERT INTO categoria(nombre,slug) VALUES (?,?)')->execute(['Colección editable',$prefix]);$category=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute(['Marca editable',$prefix]);$brand=(int)$pdo->lastInsertId();
    $draft=['action'=>'create','nombre'=>'Perfume de prueba','slug'=>$prefix,'id_categoria'=>(string)$category,'id_marca'=>(string)$brand,'descripcion_corta'=>'Ficha breve','descripcion_larga'=>"Texto floral\nSegunda línea",'imagen_principal'=>'','precio'=>'20,50','precio_oferta'=>'','stock'=>'5','activo'=>'1'];
    $guest=new TestBrowser();
    foreach (['admin/index.php','admin/catalogo.php','admin/producto.php','admin/formato.php?id=1'] as $path) { $guest->request($path);check($guest->status===302 && ($guest->headers['location'] ?? '')==='login.php','protege '.$path); }
    $guest->request('admin/login.php');$guest->request('admin/producto.php',$draft);check($guest->status===302,'una sesión sin autenticar no crea productos');
    $a=$login();$b=$login();
    $a->request('admin/producto.php',$draft,false);check($a->status===403,'alta exige CSRF');
    $a->request('admin/producto.php',$draft);check($a->status===303,'crea perfume y redirige sin reenviar POST');
    preg_match('/id=(\d+)/',$a->headers['location'] ?? '',$m);$id=(int)($m[1] ?? 0);$ids[]=$id;
    check($id>0 && (int)$row($id)['activo']===0 && $row($id)['precio']==='20.50','alta queda inactiva y normaliza coma decimal');
    check($count($id)===1,'alta registra historial');
    $body=$a->request('admin/producto.php?id='.$id);
    check(str_contains($body,'Perfume creado como inactivo') && str_contains($body,'Historial de cambios'),'confirma alta y ofrece historial');
    check(str_contains($a->headers['cache-control'] ?? '','no-store') && str_contains($a->headers['x-robots-tag'] ?? '','noindex') && ($a->headers['referrer-policy'] ?? '')==='no-referrer','editor privado sin caché ni Referer');
    $guest->request('public/producto.php?p='.$prefix);check($guest->status===404,'borrador no aparece en tienda');
    $a->request('admin/producto.php',$draft);check($a->status===422 && $count($id)===1,'reenviar alta no duplica producto ni historial');
    $payload=$form($a,$id);$payload['activo']='1';$payload['precio_oferta']='18,75';
    $a->request('admin/producto.php?id='.$id,$payload);check($a->status===303 && $row($id)['precio_oferta']==='18.75','activa y establece oferta');
    $body=$guest->request('public/producto.php?p='.$prefix);check($guest->status===200 && str_contains($body,'18,75 €') && str_contains($body,'Texto floral'),'ficha pública refleja datos guardados');
    $stale=$payload;$stale['nombre']='Nombre obsoleto';$a->request('admin/producto.php?id='.$id,$stale);
    check($a->status===409 && $row($id)['nombre']==='Perfume de prueba','una pestaña antigua no sobrescribe edición posterior');
    $payload=$form($a,$id);$a->request('admin/producto.php?id='.$id,$payload,false);check($a->status===403,'edición exige CSRF');
    $bad=$payload;$bad['version']=str_repeat('0',64);$a->request('admin/producto.php?id='.$id,$bad);check($a->status===409,'huella inventada rechazada');
    $before=$row($id);$beforeCount=$count($id);
    foreach ([['nombre'=>[]],['nombre'=>''],['nombre'=>str_repeat('é',181)],['nombre'=>"x\0y"],['nombre'=>"\xFF"],['precio'=>'0'],['precio'=>'-2'],['precio'=>'1e2'],['precio'=>'1.234'],['precio'=>'100000000'],['precio_oferta'=>'90'],['stock'=>'-1'],['stock'=>'1.2'],['stock'=>'1000000'],['activo'=>'2'],['id_categoria'=>'9999999'],['id_marca'=>[]],['imagen_principal'=>'https://example.com/track.jpg'],['imagen_principal'=>'../config/config.php'],['variants'=>['999'=>['stock'=>'3']]],['action'=>['save']]] as $invalid) {
        $a->request('admin/producto.php?id='.$id,array_replace($payload,$invalid));
        check($a->status===422,'valida campo '.array_key_first($invalid));
    }
    check($before===$row($id) && $count($id)===$beforeCount,'datos inválidos no modifican ficha ni historial');
    $payload=$form($a,$id);$payload['nombre']='<script>solo texto</script>';$payload['descripcion_larga']="Floral\n<script>sin ejecución</script>";$payload['precio_oferta']='';
    $a->request('admin/producto.php?id='.$id,$payload);check($a->status===303 && $row($id)['precio_oferta']===null,'retira oferta y acepta descripciones como texto');
    $body=$a->request('admin/producto.php?id='.$id);check(!str_contains($body,'<script>solo texto') && str_contains($body,'&lt;script&gt;solo texto'),'editor e historial escapan HTML');
    $body=$guest->request('public/producto.php?p='.$prefix);check(!str_contains($body,'<script>sin ejecución') && str_contains($body,'&lt;script&gt;sin ejecución'),'ficha pública escapa descripción editada');
    $payload=$form($a,$id);$history=$count($id);$a->request('admin/producto.php?id='.$id,$payload);check($a->status===303 && $count($id)===$history,'guardar sin cambios no añade ruido al historial');
    // Un pedido confirmado descuenta stock; el formulario anterior debe quedar obsoleto.
    $payload=$form($a,$id);$buyer=raceBrowser([['id_producto'=>$id]],$email);
    $buyer->request('public/checkout.php',['step'=>'confirm']);check($buyer->status===200 && (int)$row($id)['stock']===4,'compra real descuenta stock del formato único');
    $a->request('admin/producto.php?id='.$id,$payload);check($a->status===409 && (int)$row($id)['stock']===4,'edición antigua no repone una unidad ya comprada');
    $oldLine=$pdo->query('SELECT nombre_producto,precio_unitario FROM linea_pedido WHERE id_producto='.$id)->fetch();
    $payload=$form($a,$id);$payload['nombre']='Perfume renovado';$payload['precio']='30';$payload['stock']='0';$a->request('admin/producto.php?id='.$id,$payload);
    check($a->status===303 && $oldLine===$pdo->query('SELECT nombre_producto,precio_unitario FROM linea_pedido WHERE id_producto='.$id)->fetch(),'editar catálogo conserva datos históricos del pedido');
    $variant=['action'=>'add_variant','capacidad_ml'=>'50','sku'=>$prefix.'-50','precio'=>'35','precio_oferta'=>'','stock'=>'6','activo'=>'1'];
    $variant['version']=$version($a->request('admin/formato.php?id='.$id));
    $a->request('admin/formato.php?id='.$id,$variant);check($a->status===303,'añade primer formato con base a cero');
    $vid=(int)$pdo->query('SELECT id FROM producto_variante WHERE id_producto='.$id)->fetchColumn();
    $a->request('admin/formato.php?id='.$id,$variant);check($a->status===409,'reenviar formato no duplica referencia');
    $variant['version']=$version($a->request('admin/formato.php?id='.$id));$a->request('admin/formato.php?id='.$id,$variant);check($a->status===422,'capacidad repetida rechazada');
    $variant['capacidad_ml']='100';$a->request('admin/formato.php?id='.$id,$variant);check($a->status===422,'SKU repetido revierte el nuevo formato');
    $variant['sku']=$prefix.'-100';$a->request('admin/formato.php?id='.$id,$variant);check($a->status===303,'añade segundo formato independiente');
    $payload=$form($a,$id);$payload['variants'][$vid]['stock']='8';$payload['variants'][$vid]['precio_oferta']='29,90';$payload['precio']='0';$payload['stock']='999';
    $a->request('admin/producto.php?id='.$id,$payload);check($a->status===303 && (int)$row($id)['stock']===0 && $row($id)['precio']==='30.00','editar variantes no altera precios ni stock base');
    check((int)$pdo->query('SELECT stock FROM producto_variante WHERE id='.$vid)->fetchColumn()===8,'actualiza stock propio del formato');
    $body=$guest->request('public/producto.php?p='.$prefix);check(str_contains($body,'29,90 €'),'oferta del formato llega a la ficha pública');
    $payload=$form($a,$id);$bad=$payload;$bad['nombre']='No guardar';$bad['variants'][$vid]['stock']='-2';
    $a->request('admin/producto.php?id='.$id,$bad);check($a->status===422 && $row($id)['nombre']==='Perfume renovado','un formato inválido revierte también los metadatos');
    foreach (['variants'=>'x','version'=>[]] as $field=>$value) { $bad=$payload;$bad[$field]=$value;$a->request('admin/producto.php?id='.$id,$bad);check($a->status===($field==='version'?409:422),'rechaza forma malformada '.$field); }
    $bad=$payload;$bad['variants'][$vid]['id_producto']='999';$bad['variants'][$vid]['capacidad_ml']='80';$a->request('admin/producto.php?id='.$id,$bad);check($a->status===422,'no permite cambiar identidad del formato');
    $payload=$form($a,$id);$history=$count($id);$snapshot=$row($id);
    $pdo->exec("CREATE TRIGGER qa_catalog_failure BEFORE INSERT ON catalogo_cambio FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture failure'");$trigger=true;
    $bad=$payload;$bad['nombre']='No guardar sin historial';$bad['variants'][$vid]['stock']='11';$a->request('admin/producto.php?id='.$id,$bad);
    check($a->status===503 && $row($id)===$snapshot && $count($id)===$history && (int)$pdo->query('SELECT stock FROM producto_variante WHERE id='.$vid)->fetchColumn()===8,'fallo del historial revierte la transacción completa');
    $pdo->exec('DROP TRIGGER qa_catalog_failure');$trigger=false;
    // Otra ficha evita que una huella válida se pueda usar sobre otro perfume.
    $second=$draft;$second['slug']=$prefix.'-second';$a->request('admin/producto.php',$second);preg_match('/id=(\d+)/',$a->headers['location'] ?? '',$m);$other=(int)$m[1];$ids[]=$other;
    $bad=$form($a,$other);$bad['version']=$payload['version'];$a->request('admin/producto.php?id='.$other,$bad);check($a->status===409,'huella de otro producto no autoriza sobrescritura');
    $v2=$variant;$v2['version']=$version($a->request('admin/formato.php?id='.$other));$v2['sku']=$prefix.'-other';$a->request('admin/formato.php?id='.$other,$v2);check($a->status===422 && (int)$row($other)['stock']===5,'primer formato no oculta stock base existente');
    // Dos administradores realmente simultáneos: solo uno conserva la versión vigente.
    $pa=$form($a,$id);$pb=$form($b,$id);$pa['nombre']='Editor A';$pb['nombre']='Editor B';$history=$count($id);
    catalogRace([$a,$b],[['admin/producto.php?id='.$id,$pa],['admin/producto.php?id='.$id,$pb]],$id);
    $statuses=[$a->status,$b->status];sort($statuses);check($statuses===[303,409] && $count($id)===$history+1,'dos ediciones simultáneas producen un guardado y un conflicto');
    // Compra y edición simultáneas: jamás se pierde la venta ni se cobra el precio antiguo tras el cambio.
    $pa=$form($a,$other);$pa['activo']='1';$pa['precio']='30';$a->request('admin/producto.php?id='.$other,$pa);
    $pa=$form($a,$other);$pa['precio']='31';$pa['stock']='8';$buyer=raceBrowser([['id_producto'=>$other]],$prefix.'-race@example.invalid');
    catalogRace([$a,$buyer],[['admin/producto.php?id='.$other,$pa],['public/checkout.php',['step'=>'confirm']]],$other);
    $state=$row($other);
    check(($a->status===303 && $buyer->status===409 && (int)$state['stock']===8 && $state['precio']==='31.00') || ($a->status===409 && $buyer->status===200 && (int)$state['stock']===4 && $state['precio']==='30.00'),'compra y edición en paralelo conservan precio y stock coherentes');
    $body=$a->request('admin/catalogo.php?q='.rawurlencode($prefix));check($a->status===200 && str_contains($body,'data-product="'.$id.'"') && str_contains($body,'data-product="'.$other.'"'),'listado encuentra ambos perfumes por enlace');
    $a->request('admin/catalogo.php?q[]=x');check($a->status===422,'listado valida búsqueda no escalar');
    $body=$a->request('admin/catalogo.php?q='.rawurlencode("' OR 1=1 --"));check($a->status===200 && !str_contains($body,'data-product='),'búsqueda SQL literal no amplía resultados');
    foreach (['id[]=1','id=0','id=-1','id=999999999'] as $query) { $a->request('admin/producto.php?'.$query);check($a->status===404,'identificador inválido o ausente devuelve 404'); }
    $dashboard=$a->request('admin/index.php');check($a->status===200 && str_contains($dashboard,'Tu tienda, de un vistazo.') && str_contains($dashboard,'inventario.php?stock=bajo'),'resumen privado dirige a la gestión y reposición');
    $payload=$form($a,$id);$payload['activo']='0';$a->request('admin/producto.php?id='.$id,$payload);$guest->request('public/producto.php?p='.$prefix);
    check($guest->status===404 && $count($id)>0,'desactivar oculta producto sin borrar historial');
    echo "PASS $checks comprobaciones de gestión del catálogo\n";
} finally {
    if ($trigger) { $pdo->exec('DROP TRIGGER IF EXISTS qa_catalog_failure'); }
    foreach ($ids as $id) {
        $pdo->prepare('DELETE FROM linea_pedido WHERE id_producto=?')->execute([$id]);
        $pdo->prepare('DELETE FROM producto_variante WHERE id_producto=?')->execute([$id]);
        $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$id]);
    }
    $pdo->prepare('DELETE FROM pedido WHERE email LIKE ?')->execute([$prefix.'%']);
    if ($category!==null) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$category]); }
    if ($brand!==null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
    $pdo->exec('DELETE FROM acceso_intento');
}
