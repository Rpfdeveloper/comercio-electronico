<?php
declare(strict_types=1);

const FAVORITES_COOKIE = 'perfume_favorites';
const FAVORITES_LIMIT = 24;

// La cookie solo contiene identificadores públicos. Nunca precios, cuentas o permisos.
function favorites_ids(): array
{
    $value = $_COOKIE[FAVORITES_COOKIE] ?? '';
    if (!is_string($value) || strlen($value) > 263 ||
        !preg_match('/^[1-9][0-9]{0,9}(?:,[1-9][0-9]{0,9}){0,23}$/D', $value)) {
        return [];
    }
    $ids = array_map('intval', explode(',', $value));
    if (max($ids) > 2147483647) { return []; }
    return array_values(array_unique($ids));
}

function favorites_write(array $ids): void
{
    $value = implode(',', $ids);
    setcookie(FAVORITES_COOKIE, $value, [
        'expires' => $ids ? time() + 180 * 86400 : time() - 3600,
        'path' => rtrim(BASE_URL, '/') . '/',
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    $_COOKIE[FAVORITES_COOKIE] = $value;
}

function favorites_return($value): string
{
    if (!is_string($value) || strlen($value) > 2048 || preg_match('/[\\\\\x00-\x20\x7f]/', $value)) {
        return 'favoritos.php';
    }
    $parts = parse_url($value);
    if ($parts === false || array_diff(array_keys($parts), ['path', 'query']) ||
        !in_array($parts['path'] ?? '', ['index.php', 'catalogo.php', 'categoria.php', 'producto.php', 'descubrir.php', 'favoritos.php'], true)) {
        return 'favoritos.php';
    }
    return $value;
}

function favorites_current_path(): string
{
    $path = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $query = $_SERVER['QUERY_STRING'] ?? '';
    return favorites_return($path . ($query !== '' ? '?' . $query : ''));
}

function favorites_products(PDO $pdo, array $ids): array
{
    if (!$ids) { return []; }
    $st = $pdo->prepare('SELECT * FROM (' . catalog_source() . ') catalogo WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $st->execute($ids);
    $byId = array_column($st->fetchAll(), null, 'id');
    $products = [];
    foreach ($ids as $id) { if (isset($byId[$id])) { $products[] = $byId[$id]; } }
    return $products;
}
