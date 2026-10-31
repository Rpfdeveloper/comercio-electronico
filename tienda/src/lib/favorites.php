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

// El cliente se obtiene de la sesión validada, nunca de un identificador del formulario.
function favorites_context(PDO $pdo): array
{
    require_once __DIR__ . '/customer.php';
    $customer = customer_current($pdo);
    return ['customer' => $customer, 'ids' => $customer === null ? favorites_ids() :
        favorites_account_ids($pdo, (int)$customer['id'])];
}

function favorites_account_ids(PDO $pdo, int $customerId): array
{
    $st = $pdo->prepare('SELECT id_producto FROM cliente_favorito WHERE id_cliente=? ORDER BY posicion');
    $st->execute([$customerId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

class FavoritesSessionExpired extends RuntimeException {}

function favorites_account_update(PDO $pdo, array $customer, string $action, ?int $id, array $guestIds): array
{
    for ($attempt = 0; $attempt < 3; ++$attempt) {
        try {
            $pdo->beginTransaction();
            // Serializa cambios de la misma cuenta entre sesiones y dispositivos.
            $lock = $pdo->prepare('SELECT password_hash FROM cliente_registrado WHERE id_cliente=? FOR UPDATE');
            $lock->execute([(int)$customer['id']]);
            $hash = $lock->fetchColumn();
            if (!is_string($hash) || !hash_equals($customer['password_hash'], $hash)) {
                throw new FavoritesSessionExpired('La sesión ha cambiado. Vuelve a acceder a tu cuenta.');
            }
            $ids = favorites_account_ids($pdo, (int)$customer['id']);
            if ($action === 'clear') {
                $ids = [];
            } elseif ($action === 'remove') {
                $ids = array_values(array_diff($ids, [$id]));
            } elseif ($action === 'add') {
                if ($id === null || !favorites_products($pdo, [$id])) {
                    throw new OutOfBoundsException('Este perfume ya no está en el catálogo.');
                }
                if (!in_array($id, $ids, true)) { array_unshift($ids, $id); }
            } elseif ($action === 'merge') {
                $valid = array_map('intval', array_column(favorites_products($pdo, $guestIds), 'id'));
                // Los nuevos preceden a los existentes; no reordena ni duplica los ya guardados.
                $ids = array_merge(array_values(array_diff($valid, $ids)), $ids);
            } else {
                throw new InvalidArgumentException('Elige una acción válida.');
            }
            if (count($ids) > FAVORITES_LIMIT) {
                throw new LengthException('Tu cuenta admite hasta 24 perfumes. Quita alguno antes de unir o añadir más; tus listas se han conservado.');
            }
            $pdo->prepare('DELETE FROM cliente_favorito WHERE id_cliente=?')->execute([(int)$customer['id']]);
            $insert = $pdo->prepare('INSERT INTO cliente_favorito(id_cliente,id_producto,posicion) VALUES (?,?,?)');
            foreach ($ids as $position => $productId) { $insert->execute([(int)$customer['id'], $productId, $position]); }
            $pdo->commit();
            return $ids;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($e instanceof PDOException && in_array((int)($e->errorInfo[1] ?? 0), [1205,1213], true) && $attempt < 2) {
                usleep(random_int(1000,5000));
                continue;
            }
            throw $e;
        }
    }
    throw new RuntimeException('No se pudo actualizar la colección.');
}
