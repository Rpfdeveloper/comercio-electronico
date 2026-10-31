<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
$adminPassword = getenv('TIENDA_TEST_ADMIN_PASS') ?: '';
if ($adminPassword === '') { throw new RuntimeException('Define TIENDA_TEST_ADMIN_PASS'); }
$marker = 'panel-' . bin2hex(random_bytes(6));
$ids = [];
$customState = 120;
$stateCreated = false;
$rows = static function (string $html): array {
    preg_match_all('/data-order-id="(\d+)"/', $html, $matches);
    return array_map('intval', $matches[1]);
};
$summary = static function (string $html, string $label): string {
    preg_match('~<span>' . preg_quote($label, '~') . '</span><strong>(.*?)</strong>~s', $html, $match);
    return html_entity_decode($match[1] ?? '', ENT_QUOTES, 'UTF-8');
};
try {
    $pdo->exec('DELETE FROM acceso_intento');
    $pdo->prepare('INSERT INTO estado_pedido(id,descripcion) VALUES (?,?)')->execute([$customState, 'custom-test']);
    $stateCreated = true;
    $insert = $pdo->prepare('INSERT INTO pedido(email,nombre_envio,direccion_envio,cp_envio,poblacion_envio,pais_envio,total,estado,fecha_pedido,observaciones) VALUES (?,?,?,?,?,?,?,?,?,?)');
    for ($i = 0; $i < 30; ++$i) {
        $insert->execute([$marker . '-' . $i . '@example.invalid', $marker . ' Cliente ' . $i,
            'Dirección de prueba', '00000', 'Prueba', 'Prueba', ($i + 1) * 10, [1, 2, $customState][$i % 3],
            $i < 15 ? '2026-10-05 00:00:00' : '2026-10-06 23:59:59', $i === 0 ? '<script>nota</script>' : null]);
        $ids[] = (int)$pdo->lastInsertId();
    }
    $pdo->prepare('UPDATE pedido SET nombre_envio=? WHERE id=?')->execute([$marker . ' 100%_! <script>cliente</script>', $ids[0]]);
    $pdo->prepare('INSERT INTO linea_pedido(id_pedido,id_producto,nombre_producto,capacidad_ml,precio_unitario,unidades,precio_neto) VALUES (?,?,?,?,?,?,?)')
        ->execute([$ids[0], (int)$pdo->query('SELECT id FROM producto LIMIT 1')->fetchColumn(), '<script>perfume</script>', 50, 5, 2, 10]);
    $b = new TestBrowser();
    $b->request('admin/pedidos.php?q=' . $marker);
    check($b->status === 302 && ($b->headers['location'] ?? '') === 'login.php', 'buscar pedidos exige sesion administrativa');
    $b->request('admin/login.php');
    $b->request('admin/login.php', ['user'=>'admin', 'pass'=>$adminPassword]);
    check($b->status === 302, 'administrador accede al nuevo panel');
    $request = static function (array $filters = []) use ($b, $marker): string {
        return $b->request('admin/pedidos.php?' . http_build_query($filters + ['q'=>$marker]));
    };
    $html = $request();
    $first = $rows($html);
    check($b->status === 200 && count($first) === 25, 'primera pagina limita a 25 pedidos');
    check($first === array_reverse(array_slice($ids, 5)), 'orden reciente estable desempata por identificador');
    check($summary($html, 'Pedidos encontrados') === '30', 'resumen cuenta todos los resultados, no solo la pagina');
    check($summary($html, 'Pendientes') === '10' && $summary($html, 'Pago registrado') === '10', 'resumen distingue estados y no reclasifica desconocidos');
    check($summary($html, 'Importe de los pedidos') === '4.650,00 €', 'importe agrega pedidos filtrados con formato monetario');
    check(strpos($b->headers['cache-control'] ?? '', 'no-store') !== false, 'resultados privados no se almacenan en cache');
    check(($b->headers['referrer-policy'] ?? '') === 'no-referrer', 'busqueda privada no se reenvia por Referer');
    check(strpos($b->headers['x-robots-tag'] ?? '', 'noindex') !== false, 'panel conserva noindex');
    check(strpos($html, 'name="viewport"') !== false && strpos($html, 'scope="col"') !== false, 'panel incluye viewport y encabezados de tabla');
    preg_match('/rel="next" href="([^"]+)"/', $html, $nextMatch);
    $next = html_entity_decode($nextMatch[1] ?? '', ENT_QUOTES, 'UTF-8');
    check(strpos($next, 'q=' . $marker) !== false && strpos($next, 'pagina=2') !== false, 'pagina siguiente conserva filtros');
    $html = $b->request('admin/' . $next);
    $second = $rows($html);
    check($second === array_reverse(array_slice($ids, 0, 5)) && !array_intersect($first, $second), 'segunda pagina completa resultados sin duplicados');
    check(strpos($html, 'rel="prev"') !== false && strpos($html, 'rel="next"') === false, 'ultima pagina solo ofrece retroceso');
    check(strpos($html, '&lt;script&gt;cliente&lt;/script&gt;') !== false && strpos($html, '<script>cliente') === false, 'listado escapa nombres de clientes');
    check($rows($request(['pagina'=>'1000000'])) === $second, 'pagina fuera del resultado se ajusta a la ultima');
    check($rows($request(['orden'=>'antiguos'])) === array_slice($ids, 0, 25), 'orden antiguo estable');
    check($rows($request(['orden'=>'importe_menor'])) === array_slice($ids, 0, 25), 'orden por menor importe');
    check($rows($request(['orden'=>'importe_mayor'])) === array_reverse(array_slice($ids, 5)), 'orden por mayor importe');
    foreach (['pending', 'paid', 'otros'] as $state) {
        $expected = [];
        foreach ($ids as $i=>$id) { if ($i % 3 === array_search($state, ['pending', 'paid', 'otros'], true)) { $expected[] = $id; } }
        $html = $request(['estado'=>$state]);
        check($rows($html) === array_reverse($expected) && $summary($html, 'Pedidos encontrados') === '10', 'filtro de estado ' . $state);
        if ($state === 'otros') { check(strpos($html, 'Estado: custom-test') !== false && strpos($html, 'badge pending') === false, 'estados desconocidos se identifican sin inventar equivalencias'); }
    }
    $html = $request(['desde'=>'2026-10-05', 'hasta'=>'2026-10-05']);
    check($rows($html) === array_reverse(array_slice($ids, 0, 15)), 'intervalo inclusivo incluye medianoche');
    $html = $request(['desde'=>'2026-10-06', 'hasta'=>'2026-10-06']);
    check($rows($html) === array_reverse(array_slice($ids, 15)), 'intervalo inclusivo incluye ultimo segundo del dia');
    check(count($rows($request(['desde'=>'2026-10-06']))) === 15, 'filtro solo desde');
    check(count($rows($request(['hasta'=>'2026-10-05']))) === 15, 'filtro solo hasta');
    $html = $request(['estado'=>'pending', 'desde'=>'2026-10-06', 'hasta'=>'2026-10-06', 'orden'=>'antiguos']);
    check($rows($html) === [$ids[15], $ids[18], $ids[21], $ids[24], $ids[27]], 'busqueda estado fechas y orden se combinan');
    check($summary($html, 'Importe de los pedidos') === '1.100,00 €', 'resumen monetario respeta todos los filtros');
    check($rows($request(['q'=>(string)$ids[0]])) === [$ids[0]], 'busqueda por numero de pedido');
    check($rows($request(['q'=>$marker . '-12@example.invalid'])) === [$ids[12]], 'busqueda por correo');
    check($rows($request(['q'=>$marker . ' Cliente 12'])) === [$ids[12]], 'busqueda por nombre');
    check($rows($request(['q'=>'100%_!'])) === [$ids[0]], 'porcentaje guion bajo y escape se buscan literalmente');
    $html = $request(['q'=>"' OR 1=1 --"]);
    check($b->status === 200 && !$rows($html), 'texto SQL no amplia la consulta');
    $html = $request(['q'=>'<img src=x onerror=alert(1)>']);
    check($b->status === 200 && strpos($html, '<img src=x') === false && strpos($html, '&lt;img src=x') !== false, 'valor de busqueda se escapa al reflejarlo');
    $html = $request(['q'=>$marker . '-inexistente']);
    check(!$rows($html) && strpos($html, 'No hay pedidos con estos filtros.') !== false && $summary($html, 'Importe de los pedidos') === '0,00 €', 'estado vacio claro con total cero');
    foreach (['q'=>['x'], 'estado'=>['1'], 'desde'=>['x'], 'hasta'=>['x'], 'orden'=>['x'], 'pagina'=>['1']] as $key=>$value) {
        $html = $request([$key=>$value]);
        check($b->status === 422 && !$rows($html), 'filtro no escalar rechazado: ' . $key);
    }
    foreach ([['estado'=>'invalid'], ['orden'=>'total;DROP TABLE pedido'], ['desde'=>'2026-02-30'], ['hasta'=>'2026-13-01'],
        ['desde'=>'2026-10-07', 'hasta'=>'2026-10-05'], ['pagina'=>'0'], ['pagina'=>'-1'], ['pagina'=>'1.5'],
        ['pagina'=>'1000001'], ['q'=>str_repeat('x',161)], ['q'=>"x\0y"], ['q'=>"\xFF"]] as $invalid) {
        $html = $request($invalid);
        check($b->status === 422 && !$rows($html) && strpos($html, 'Revisa los filtros') !== false, 'filtro invalido no ejecuta busqueda: ' . array_key_first($invalid));
    }
    foreach ([3=>'processing', 4=>'shipped', 5=>'canceled'] as $stateId=>$stateName) {
        $pdo->prepare('UPDATE pedido SET estado=? WHERE id=?')->execute([$stateId, $ids[0]]);
        check($rows($request(['estado'=>$stateName])) === [$ids[0]], 'filtro del estado real ' . $stateName);
    }
    $pdo->prepare('UPDATE pedido SET estado=1 WHERE id=?')->execute([$ids[0]]);
    $html = $request(['pagina'=>'2', 'orden'=>'antiguos']);
    preg_match('/class="order-link" href="([^"]+)"/', $html, $detailMatch);
    $detailUrl = html_entity_decode($detailMatch[1] ?? '', ENT_QUOTES, 'UTF-8');
    $html = $b->request('admin/' . $detailUrl);
    check($b->status === 200 && strpos($html, 'q=' . $marker) !== false && strpos($html, 'pagina=2') !== false && strpos($html, 'orden=antiguos') !== false, 'detalle permite regresar conservando filtros y pagina');
    $html = $b->request('admin/pedido.php?id=' . $ids[0]);
    check(strpos($html, '&lt;script&gt;nota&lt;/script&gt;') !== false && strpos($html, '<script>nota') === false, 'detalle muestra observaciones escapadas');
    check(strpos($html, '&lt;script&gt;perfume&lt;/script&gt;') !== false && strpos($html, '50 ml') !== false, 'detalle muestra linea y variante escapadas');
    check(strpos($html, 'no confirma un cobro') !== false, 'detalle no presenta confirmacion como pago');
    check(($b->headers['referrer-policy'] ?? '') === 'no-referrer', 'detalle evita fuga de filtros por Referer');
    $html = $b->request('admin/pedido.php?id=' . $ids[1] . '&q[]=x');
    check($b->status === 200 && strpos($html, 'href="pedidos.php"') !== false, 'filtros de retorno invalidos usan listado seguro');
    $count = $pdo->prepare('SELECT COUNT(*) FROM pedido WHERE email LIKE ?');
    $count->execute([$marker . '%']);
    check((int)$count->fetchColumn() === 30, 'consultas y entradas hostiles conservan todos los pedidos');
    echo "PASS $checks comprobaciones de consulta administrativa\n";
} finally {
    foreach ($ids as $id) {
        $pdo->prepare('DELETE FROM linea_pedido WHERE id_pedido=?')->execute([$id]);
        $pdo->prepare('DELETE FROM pedido WHERE id=?')->execute([$id]);
    }
    if ($stateCreated) { $pdo->prepare('DELETE FROM estado_pedido WHERE id=?')->execute([$customState]); }
    $pdo->exec('DELETE FROM acceso_intento');
}
