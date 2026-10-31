<?php
declare(strict_types=1);

const ADMIN_INVENTORY_PAGE_SIZE = 25;

function admin_inventory_filters(array $input): array
{
    $filters = ['q'=>'', 'stock'=>'', 'visibilidad'=>'', 'orden'=>'stock', 'pagina'=>'1'];
    $errors = [];
    foreach ($filters as $key=>$default) {
        if (!array_key_exists($key, $input)) { continue; }
        if (!is_string($input[$key]) || strlen($input[$key]) > 160 ||
            preg_match('//u', $input[$key]) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $input[$key])) {
            $errors[$key] = 'El valor recibido no es válido.';
        } else { $filters[$key] = trim($input[$key]); }
    }
    foreach (['stock'=>['', 'agotado', 'bajo', 'disponible', 'revision'],
        'visibilidad'=>['', 'activa', 'inactiva'], 'orden'=>['stock', 'nombre', 'stock_mayor']] as $key=>$allowed) {
        if (!in_array($filters[$key], $allowed, true)) { $errors[$key] = 'Elige una de las opciones disponibles.'; }
    }
    if (!preg_match('/\A[1-9]\d{0,6}\z/', $filters['pagina']) || (int)$filters['pagina'] > 1000000) {
        $errors['pagina'] = 'El número de página no es válido.';
    }
    return [$filters, $errors];
}

function admin_inventory_url(array $filters, int $page): string
{
    $filters['pagina'] = (string)$page;
    return 'inventario.php?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
}

function admin_inventory_stock($stock): array
{
    if ($stock === null || (int)$stock < 0) { return ['Revisar dato', 'warning']; }
    if ((int)$stock === 0) { return ['Agotado', 'warning']; }
    if ((int)$stock <= 5) { return ['Stock bajo', 'pending']; }
    return ['Disponible', 'confirmed'];
}

function admin_inventory_search(PDO $pdo, array $filters): array
{
    // Una fila por variante, o por producto sin variantes. Nunca se suma el stock
    // base cuando existen variantes, aunque todas estén inactivas.
    $source = "SELECT p.id AS producto_id, v.id AS variante_id, p.nombre, m.nombre AS marca,
        c.nombre AS categoria, v.capacidad_ml, v.sku,
        CASE WHEN v.id IS NULL THEN p.stock ELSE v.stock END AS existencias,
        CASE WHEN v.id IS NULL THEN CASE WHEN p.precio_oferta > 0 THEN p.precio_oferta ELSE p.precio END
            ELSE CASE WHEN v.precio_oferta > 0 THEN v.precio_oferta ELSE v.precio END END AS precio,
        COALESCE(p.activo = 1 AND c.activa = 1 AND (v.id IS NULL OR v.activo = 1),0) AS activa
        FROM producto p JOIN categoria c ON c.id=p.id_categoria JOIN marca m ON m.id=p.id_marca
        LEFT JOIN producto_variante v ON v.id_producto=p.id";
    $where = []; $params = [];
    if ($filters['q'] !== '') {
        $term = '%' . strtr($filters['q'], ['!'=>'!!', '%'=>'!%', '_'=>'!_']) . '%';
        $where[] = "(nombre LIKE ? ESCAPE '!' OR marca LIKE ? ESCAPE '!' OR sku LIKE ? ESCAPE '!')";
        $params = [$term, $term, $term];
    }
    $stockConditions = ['agotado'=>'existencias = 0', 'bajo'=>'existencias BETWEEN 1 AND 5',
        'disponible'=>'existencias > 5', 'revision'=>'(existencias IS NULL OR existencias < 0)'];
    if ($filters['stock'] !== '') { $where[] = $stockConditions[$filters['stock']]; }
    if ($filters['visibilidad'] !== '') { $where[] = 'activa = ?'; $params[] = $filters['visibilidad'] === 'activa' ? 1 : 0; }
    $from = ' FROM (' . $source . ') inventario' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $summary = $pdo->prepare('SELECT COUNT(*) AS cantidad, COALESCE(SUM(GREATEST(existencias,0)),0) AS unidades,
        COALESCE(SUM(existencias BETWEEN 1 AND 5),0) AS bajo, COALESCE(SUM(existencias=0),0) AS agotado,
        COALESCE(SUM(existencias IS NULL OR existencias < 0),0) AS revision' . $from);
    $summary->execute($params);
    $totals = $summary->fetch();
    $pages = max(1, (int)ceil((int)$totals['cantidad'] / ADMIN_INVENTORY_PAGE_SIZE));
    $page = min((int)$filters['pagina'], $pages);
    $offset = ($page - 1) * ADMIN_INVENTORY_PAGE_SIZE;
    $orders = ['stock'=>'existencias IS NOT NULL, existencias, nombre, producto_id, variante_id',
        'nombre'=>'nombre, producto_id, variante_id',
        'stock_mayor'=>'existencias IS NULL, existencias DESC, nombre, producto_id, variante_id'];
    $query = $pdo->prepare('SELECT *' . $from . ' ORDER BY ' . $orders[$filters['orden']] .
        ' LIMIT ' . ADMIN_INVENTORY_PAGE_SIZE . ' OFFSET ' . $offset);
    $query->execute($params);
    return ['items'=>$query->fetchAll(), 'totals'=>$totals, 'page'=>$page, 'pages'=>$pages, 'offset'=>$offset];
}
