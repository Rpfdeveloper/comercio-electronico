<?php
declare(strict_types=1);

const ADMIN_ORDERS_PAGE_SIZE = 25;

function admin_orders_filters(array $input): array
{
    $filters = ['q'=>'', 'estado'=>'', 'desde'=>'', 'hasta'=>'', 'orden'=>'recientes', 'pagina'=>'1'];
    $errors = [];
    foreach ($filters as $key=>$default) {
        if (!array_key_exists($key, $input)) { continue; }
        if (!is_string($input[$key]) || strlen($input[$key]) > 160 ||
            preg_match('//u', $input[$key]) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $input[$key])) {
            $errors[$key] = 'El valor recibido no es válido.';
        } else { $filters[$key] = trim($input[$key]); }
    }
    if (!in_array($filters['estado'], ['', 'pending', 'paid', 'processing', 'shipped', 'canceled', 'otros'], true)) {
        $errors['estado'] = 'Elige uno de los estados disponibles.';
    }
    if (!in_array($filters['orden'], ['recientes', 'antiguos', 'importe_mayor', 'importe_menor'], true)) {
        $errors['orden'] = 'Elige una de las ordenaciones disponibles.';
    }
    foreach (['desde', 'hasta'] as $key) {
        $date = $filters[$key];
        if ($date !== '' && (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $parts) ||
            !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]))) {
            $errors[$key] = 'Introduce una fecha válida.';
        }
    }
    if (!isset($errors['desde']) && !isset($errors['hasta']) && $filters['desde'] !== '' &&
        $filters['hasta'] !== '' && $filters['desde'] > $filters['hasta']) {
        $errors['hasta'] = 'La fecha final debe ser igual o posterior a la inicial.';
    }
    if (!preg_match('/\A[1-9]\d{0,6}\z/', $filters['pagina']) || (int)$filters['pagina'] > 1000000) {
        $errors['pagina'] = 'El número de página no es válido.';
    }
    return [$filters, $errors];
}

function admin_orders_url(array $filters, ?int $page = null): string
{
    if ($page !== null) { $filters['pagina'] = (string)$page; }
    return 'pedidos.php?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
}

function admin_order_state(string $state): array
{
    $states = ['pending'=>['Pendiente', 'pending'], 'paid'=>['Pago registrado', 'confirmed'],
        'processing'=>['En preparación', 'other'], 'shipped'=>['Enviado', 'confirmed'],
        'canceled'=>['Cancelado', 'other']];
    return $states[$state] ?? ['Estado: ' . $state, 'other'];
}

function admin_orders_search(PDO $pdo, array $filters): array
{
    $where = []; $params = [];
    if ($filters['q'] !== '') {
        // Buscar literalmente % y _, sin convertirlos en comodines de SQL.
        $like = '%' . strtr($filters['q'], ['!'=>'!!', '%'=>'!%', '_'=>'!_']) . '%';
        if (preg_match('/\A[0-9]+\z/', $filters['q']) === 1) {
            $where[] = 'p.id = ?';
            $params[] = strlen($filters['q']) <= 10 ? $filters['q'] : 0;
        } else {
            $where[] = "(nombre_envio LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!')";
            $params = [$like, $like];
        }
    }
    if ($filters['estado'] === 'otros') { $where[] = "e.descripcion NOT IN ('pending','paid','processing','shipped','canceled')"; }
    elseif ($filters['estado'] !== '') { $where[] = 'e.descripcion = ?'; $params[] = $filters['estado']; }
    if ($filters['desde'] !== '') { $where[] = 'fecha_pedido >= ?'; $params[] = $filters['desde'] . ' 00:00:00'; }
    if ($filters['hasta'] !== '') { $where[] = 'fecha_pedido <= ?'; $params[] = $filters['hasta'] . ' 23:59:59'; }
    $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $summary = $pdo->prepare('SELECT COUNT(*) AS cantidad, COALESCE(SUM(total),0) AS importe,
        COALESCE(SUM(e.descripcion=\'pending\'),0) AS pendientes, COALESCE(SUM(e.descripcion=\'paid\'),0) AS pagados FROM pedido p JOIN estado_pedido e ON e.id=p.estado' . $sqlWhere);
    $summary->execute($params);
    $totals = $summary->fetch();
    $pages = max(1, (int)ceil((int)$totals['cantidad'] / ADMIN_ORDERS_PAGE_SIZE));
    $page = min((int)$filters['pagina'], $pages);
    $offset = ($page - 1) * ADMIN_ORDERS_PAGE_SIZE;
    $sorts = ['recientes'=>'fecha_pedido DESC, p.id DESC', 'antiguos'=>'fecha_pedido ASC, p.id ASC',
        'importe_mayor'=>'total DESC, p.id DESC', 'importe_menor'=>'total ASC, p.id ASC'];
    $query = $pdo->prepare('SELECT p.id,fecha_pedido,email,nombre_envio,total,e.descripcion AS estado_texto FROM pedido p JOIN estado_pedido e ON e.id=p.estado' .
        $sqlWhere . ' ORDER BY ' . $sorts[$filters['orden']] . ' LIMIT ' . ADMIN_ORDERS_PAGE_SIZE . ' OFFSET ' . $offset);
    $query->execute($params);
    return ['orders'=>$query->fetchAll(), 'totals'=>$totals, 'page'=>$page, 'pages'=>$pages, 'offset'=>$offset];
}
