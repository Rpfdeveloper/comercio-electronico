<?php
declare(strict_types=1);

function catalog_filters(array $query): array
{
    $values = [];
    foreach (['q', 'c', 'marca', 'min', 'max', 'stock', 'orden', 'page'] as $key) {
        $value = $query[$key] ?? '';
        if (!is_string($value) || strlen($value) > 600 || !preg_match('//u', $value) ||
            preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException('Revisa los filtros e introduce valores válidos.');
        }
        $values[$key] = trim($value);
    }
    if (preg_match_all('/./us', $values['q']) > 100 ||
        strlen($values['c']) > 140 || strlen($values['marca']) > 140) {
        throw new InvalidArgumentException('La búsqueda admite hasta 100 caracteres.');
    }
    foreach (['min', 'max'] as $key) {
        $values[$key] = str_replace(',', '.', $values[$key]);
        if ($values[$key] !== '' && !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $values[$key])) {
            throw new InvalidArgumentException('Introduce precios positivos con un máximo de dos decimales.');
        }
    }
    if ($values['min'] !== '' && $values['max'] !== '' && (float)$values['min'] > (float)$values['max']) {
        throw new InvalidArgumentException('El precio mínimo no puede superar al máximo.');
    }
    $values['orden'] = $values['orden'] === '' ? 'nombre' : $values['orden'];
    if (!isset(catalog_orders()[$values['orden']]) || !in_array($values['stock'], ['', '1'], true)) {
        throw new InvalidArgumentException('Selecciona una ordenación y disponibilidad válidas.');
    }
    $values['page'] = $values['page'] === '' ? '1' : $values['page'];
    if (!preg_match('/^[1-9]\d{0,6}$/D', $values['page']) || (int)$values['page'] > 1000000) {
        throw new InvalidArgumentException('Introduce un número de página válido.');
    }
    return $values;
}

function catalog_orders(): array
{
    return ['nombre' => 'Nombre: A–Z', 'precio-asc' => 'Precio: de menor a mayor',
        'precio-desc' => 'Precio: de mayor a menor', 'recientes' => 'Más recientes'];
}

// Mismo precio efectivo que el carrito: una oferta positiva sustituye al precio base.
// Con variantes se usa el mínimo comprable; si todas están agotadas, el mínimo activo.
// Un producto con variantes inactivas nunca se vende como producto base.
function catalog_source(): string
{
    return "SELECT p.id, p.nombre, p.slug, p.descripcion_corta, p.imagen_principal,
        p.created_at, c.slug AS categoria_slug, m.slug AS marca_slug, m.nombre AS marca,
        CASE WHEN v.total IS NULL THEN CASE WHEN p.precio_oferta > 0 THEN p.precio_oferta ELSE p.precio END
             ELSE COALESCE(v.precio_disponible, v.precio_activo) END AS precio_desde,
        CASE WHEN v.total IS NULL THEN p.stock > 0 ELSE v.disponibles > 0 END AS disponible
        FROM producto p JOIN categoria c ON c.id = p.id_categoria
        JOIN marca m ON m.id = p.id_marca
        LEFT JOIN (
            SELECT id_producto, COUNT(*) AS total,
                SUM(activo = 1 AND stock > 0) AS disponibles,
                MIN(CASE WHEN activo = 1 AND stock > 0 THEN
                    CASE WHEN precio_oferta > 0 THEN precio_oferta ELSE precio END END) AS precio_disponible,
                MIN(CASE WHEN activo = 1 THEN
                    CASE WHEN precio_oferta > 0 THEN precio_oferta ELSE precio END END) AS precio_activo
            FROM producto_variante GROUP BY id_producto
        ) v ON v.id_producto = p.id
        WHERE p.activo = 1 AND c.activa = 1";
}

function catalog_results(PDO $pdo, array $filters): array
{
    $where = ['1 = 1']; $params = [];
    foreach (['c' => 'categoria_slug', 'marca' => 'marca_slug'] as $key => $column) {
        if ($filters[$key] !== '') { $where[] = "$column = ?"; $params[] = $filters[$key]; }
    }
    if ($filters['q'] !== '') {
        // El usuario busca caracteres literales, incluidos %, _ y el propio escape.
        $term = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $where[] = "(nombre LIKE ? ESCAPE '!' OR descripcion_corta LIKE ? ESCAPE '!' OR marca LIKE ? ESCAPE '!')";
        array_push($params, $term, $term, $term);
    }
    foreach (['min' => '>=', 'max' => '<='] as $key => $operator) {
        if ($filters[$key] !== '') { $where[] = "precio_desde $operator ?"; $params[] = $filters[$key]; }
    }
    if ($filters['stock'] === '1') { $where[] = 'disponible = 1'; }
    $from = ' FROM (' . catalog_source() . ') catalogo WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*)' . $from);
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / 12));
    $page = (int)$filters['page'];
    if ($page > $pages) { throw new OutOfBoundsException('Página no encontrada.'); }
    $orders = ['nombre' => 'nombre, id', 'precio-asc' => 'precio_desde IS NULL, precio_desde, nombre, id',
        'precio-desc' => 'precio_desde IS NULL, precio_desde DESC, nombre, id', 'recientes' => 'created_at DESC, id DESC'];
    $st = $pdo->prepare('SELECT *' . $from . ' ORDER BY ' . $orders[$filters['orden']] . ' LIMIT ? OFFSET ?');
    foreach ($params as $i => $value) { $st->bindValue($i + 1, $value, PDO::PARAM_STR); }
    $st->bindValue(count($params) + 1, 12, PDO::PARAM_INT);
    $st->bindValue(count($params) + 2, ($page - 1) * 12, PDO::PARAM_INT);
    $st->execute();
    return ['products' => $st->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
}

function catalog_link(string $path, array $filters, int $page): string
{
    $filters['page'] = (string)$page;
    $query = array_filter($filters, static function ($value) { return $value !== ''; });
    return $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}
