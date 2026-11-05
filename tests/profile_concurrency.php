<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';

function profileRaceConnection(): PDO {
    global $dsn;
    return new PDO($dsn, getenv('TIENDA_TEST_USER') ?: 'root', getenv('TIENDA_TEST_PASS') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function profileRaceRequests(array $browsers, array $requests, int $barrierCustomer): array {
    $multi = curl_multi_init();
    $handles = [];
    $activeHandles = [];
    $guard = null;
    $observer = null;
    $waiting = 0;
    try {
        if ($barrierCustomer !== null) {
            $guard = profileRaceConnection();
            $guard->beginTransaction();
            $guard->query('SELECT id_cliente FROM cliente_registrado WHERE id_cliente=' . $barrierCustomer . ' FOR UPDATE')->fetch();
            $observer = profileRaceConnection();
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

$email='profile-race-'.bin2hex(random_bytes(5)).'@example.invalid';
$password='fictitious-concurrent-password'; $customer=null;
$fields=['nombre'=>'Cliente de prueba','direccion'=>'Habitual de prueba','cp'=>'00000','poblacion'=>'Prueba','provincia'=>'','pais'=>'España'];
$login=static function() use($email,$password): TestBrowser {
    $b=new TestBrowser(); $b->request('public/acceso.php');
    $b->request('public/acceso.php',['email'=>$email,'password'=>$password]);
    if($b->status!==303) { throw new RuntimeException('No se pudo preparar la cuenta'); }
    $b->request('public/perfil.php'); return $b;
};
$version=static function(string $body): string {
    preg_match('/name="profile_version" value="([a-f0-9]{64})"/',$body,$m); return $m[1]??'';
};
try {
    $pdo->exec('DELETE FROM acceso_intento');
    $pdo->prepare('INSERT INTO cliente(email,nombre) VALUES (?,?)')->execute([$email,$fields['nombre']]); $customer=(int)$pdo->lastInsertId();
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO cliente_registrado(id_cliente,password_hash,direccion,cp,poblacion,pais) VALUES (?,?,?,?,?,?)')
        ->execute([$customer,$hash,$fields['direccion'],$fields['cp'],$fields['poblacion'],$fields['pais']]);
    $a=$login(); $b=$login(); $v=$version($a->request('public/perfil.php'));
    $one=$fields+['action'=>'profile','profile_version'=>$v]; $one['nombre']='Cambio A';
    $two=$one; $two['nombre']='Cambio B';
    profileRaceRequests([$a,$b],[['public/perfil.php',$one],['public/perfil.php',$two]],$customer);
    check(in_array([$a->status,$b->status],[[303,409],[409,303]],true),'dos ediciones: se guarda una y se rechaza la antigua');
    check($pdo->query('SELECT nombre FROM cliente WHERE id='.$customer)->fetchColumn()===($a->status===303?'Cambio A':'Cambio B'),'ningún cambio simultáneo se pierde silenciosamente');

    $change=['action'=>'password','current_password'=>$password,'password'=>'new-concurrent-password','password2'=>'new-concurrent-password'];
    $a->request('public/perfil.php'); $b->request('public/perfil.php');
    profileRaceRequests([$a,$b],[['public/perfil.php',$change],['public/perfil.php',$change]],$customer);
    check(in_array([$a->status,$b->status],[[303,409],[409,303]],true),'dos cambios de contraseña: solo uno se completa y se rechaza la prueba antigua');
    $hashNow=$pdo->query('SELECT password_hash FROM cliente_registrado WHERE id_cliente='.$customer)->fetchColumn();
    check(password_verify($change['password'],$hashNow),'se conserva exactamente la contraseña del cambio aceptado');

    // Recuperación y perfil usan cuenta -> token, sin interbloqueos y con revalidación.
    foreach([true,false] as $profileFirst) {
        $pdo->prepare('UPDATE cliente_registrado SET password_hash=? WHERE id_cliente=?')->execute([$hash,$customer]);
        $pdo->exec('DELETE FROM acceso_intento');
        $a=$login(); $reset=new TestBrowser(); $token=bin2hex(random_bytes(32));
        $pdo->prepare('INSERT INTO cliente_recuperacion(token_hash,id_cliente,expira) VALUES (?,?,UTC_TIMESTAMP()+INTERVAL 1 HOUR)')->execute([hash('sha256',$token),$customer]);
        $reset->request('public/restablecer.php?token='.$token);
        $resetPost=['token'=>$token,'password'=>'reset-concurrent-password','password2'=>'reset-concurrent-password'];
        $bs=$profileFirst?[$a,$reset]:[$reset,$a];
        $requests=$profileFirst?[['public/perfil.php',$change],['public/restablecer.php',$resetPost]]:[['public/restablecer.php',$resetPost],['public/perfil.php',$change]];
        profileRaceRequests($bs,$requests,$customer);
        check(in_array([$a->status,$reset->status],[[303,410],[409,303]],true),'perfil y recuperación: solo un cambio se completa');
        $hashNow=$pdo->query('SELECT password_hash FROM cliente_registrado WHERE id_cliente='.$customer)->fetchColumn();
        check(password_verify($a->status===303?$change['password']:$resetPost['password'],$hashNow),'la operación rechazada no sustituye la nueva contraseña');
        $pdo->exec('DELETE FROM cliente_recuperacion WHERE id_cliente='.$customer);
    }
    echo "PASS $checks comprobaciones de concurrencia de perfil\n";
} finally {
    if($customer) {
        $pdo->exec('DELETE FROM cliente_registrado WHERE id_cliente='.$customer);
        $pdo->exec('DELETE FROM cliente WHERE id='.$customer);
    }
    $pdo->exec('DELETE FROM acceso_intento');
}
