<?php
declare(strict_types=1);

// Carrito guardado en la cuenta: mientras hay sesión de cliente, la base de datos es la referencia.
// Solo se guardan identificadores y cantidades; precios, stock y disponibilidad se releen siempre.
require_once __DIR__ . '/customer.php';

const CART_ACCOUNT_LINES = 100;

class CartAccountSessionExpired extends RuntimeException {}

// Entradas bien formadas del carrito de sesión; las demás se descartan sin interpretarlas.
function cart_account_clean($cart): array
{
    $clean = [];
    if (!is_array($cart)) { return $clean; }
    foreach ($cart as $key => $item) {
        if (!is_array($item) || !is_string($key)) { continue; }
        $product = filter_var($item['id_producto'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>2147483647]]);
        $variantRaw = $item['id_variante'] ?? null;
        $variant = $variantRaw === null ? null : filter_var($variantRaw, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>2147483647]]);
        $qty = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>999]]);
        if ($product === false || $variant === false || $qty === false) { continue; }
        if ($key !== ($variant !== null ? 'v' . $variant : 'p' . $product)) { continue; }
        $clean[$key] = ['id_producto'=>$product, 'id_variante'=>$variant, 'cantidad'=>$qty];
    }
    return $clean;
}

// Unión sin duplicar unidades: el mismo carrito en dos sitios no suma dos veces.
function cart_account_merge(array $saved, array $session): array
{
    $merged = $saved;
    foreach ($session as $key => $item) {
        if (isset($merged[$key])) {
            $merged[$key]['cantidad'] = max((int)$merged[$key]['cantidad'], (int)$item['cantidad']);
        } else {
            $merged[$key] = $item;
        }
    }
    return array_slice($merged, 0, CART_ACCOUNT_LINES, true);
}

function cart_account_load(PDO $pdo, int $customerId): array
{
    $st = $pdo->prepare('SELECT clave,id_producto,id_variante,cantidad FROM cliente_carrito WHERE id_cliente=? ORDER BY actualizado, clave');
    $st->execute([$customerId]);
    $cart = [];
    foreach ($st->fetchAll() as $row) {
        $cart[$row['clave']] = ['id_producto'=>(int)$row['id_producto'],
            'id_variante'=>$row['id_variante'] === null ? null : (int)$row['id_variante'], 'cantidad'=>(int)$row['cantidad']];
    }
    return cart_account_clean($cart);
}

// Sustituye el carrito guardado. $mergeSaved une primero con lo ya guardado (al iniciar sesión).
function cart_account_write(PDO $pdo, array $customer, array $cart, bool $mergeSaved = false): array
{
    $cart = cart_account_clean($cart);
    for ($attempt = 0; ; ++$attempt) {
        try {
            $pdo->beginTransaction();
            // Serializa cambios de la misma cuenta desde varias sesiones y comprueba la credencial.
            $lock = $pdo->prepare('SELECT password_hash FROM cliente_registrado WHERE id_cliente=? FOR UPDATE');
            $lock->execute([(int)$customer['id']]);
            $hash = $lock->fetchColumn();
            if (!is_string($hash) || !hash_equals((string)$customer['password_hash'], $hash)) {
                throw new CartAccountSessionExpired('La sesión ha cambiado.');
            }
            $result = $mergeSaved ? cart_account_merge(cart_account_load($pdo, (int)$customer['id']), $cart) : $cart;
            $result = array_slice($result, 0, CART_ACCOUNT_LINES, true);
            $pdo->prepare('DELETE FROM cliente_carrito WHERE id_cliente=?')->execute([(int)$customer['id']]);
            // Solo referencias existentes y coherentes; las retiradas de la base no se guardan.
            $productExists = $pdo->prepare('SELECT 1 FROM producto WHERE id=?');
            $variantExists = $pdo->prepare('SELECT 1 FROM producto_variante WHERE id=? AND id_producto=?');
            $insert = $pdo->prepare('INSERT INTO cliente_carrito(id_cliente,clave,id_producto,id_variante,cantidad) VALUES (?,?,?,?,?)');
            $stored = [];
            foreach ($result as $key => $item) {
                if ($item['id_variante'] !== null) { $variantExists->execute([$item['id_variante'], $item['id_producto']]); $found = $variantExists->fetchColumn(); }
                else { $productExists->execute([$item['id_producto']]); $found = $productExists->fetchColumn(); }
                if ($found === false) { continue; }
                $insert->execute([(int)$customer['id'], $key, $item['id_producto'], $item['id_variante'], $item['cantidad']]);
                $stored[$key] = $item;
            }
            $pdo->commit();
            return $stored;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($e instanceof PDOException && in_array((int)($e->errorInfo[1] ?? 0), [1205, 1213], true) && $attempt < 2) {
                usleep(random_int(1000, 5000));
                continue;
            }
            throw $e;
        }
    }
}

// Carrito vigente: el de la cuenta con sesión de cliente, si no el del navegador.
// Devuelve [cliente|null, carrito, aviso]. Un fallo de la base de datos conserva el del navegador.
function cart_account_context(PDO $pdo): array
{
    $raw = $_SESSION['cart'] ?? [];
    $customer = customer_current($pdo);
    if ($customer === null) { return [null, is_array($raw) ? $raw : [], '']; }
    try {
        // La primera sincronización de la sesión une ambos carritos; después manda la cuenta,
        // para que lo eliminado en otro dispositivo no reaparezca.
        if (($_SESSION['cart_synced'] ?? null) !== (int)$customer['id']) {
            $cart = cart_account_write($pdo, $customer, $raw, true);
            $_SESSION['cart_synced'] = (int)$customer['id'];
        } else {
            $cart = cart_account_load($pdo, (int)$customer['id']);
        }
        $_SESSION['cart'] = $cart;
        return [$customer, $cart, ''];
    } catch (CartAccountSessionExpired $e) {
        customer_forget();
        return [null, is_array($raw) ? $raw : [], ''];
    } catch (PDOException $e) {
        // Al recuperarse, la siguiente lectura vuelve a unir: lo añadido mientras tanto no se pierde.
        unset($_SESSION['cart_synced']);
        return [null, is_array($raw) ? $raw : [], 'No podemos recuperar el carrito de tu cuenta ahora. Usamos el de este navegador.'];
    }
}

// Guarda el carrito tras un cambio. Devuelve un aviso vacío si se guardó o no había cuenta.
function cart_account_save(PDO $pdo, ?array $customer, array $cart): string
{
    if ($customer === null) { return ''; }
    try {
        $_SESSION['cart'] = cart_account_write($pdo, $customer, $cart);
        return '';
    } catch (CartAccountSessionExpired $e) {
        customer_forget();
        return 'Tu sesión ha caducado. El carrito se conserva en este navegador; vuelve a acceder para guardarlo en tu cuenta.';
    } catch (PDOException $e) {
        unset($_SESSION['cart_synced']);
        return 'El carrito se ha actualizado en este navegador, pero no se ha podido guardar en tu cuenta.';
    }
}

// Al iniciar sesión: une el carrito del navegador con el guardado. Nunca impide el acceso;
// si falla, la unión se repite al abrir el carrito.
function cart_account_after_sign_in(PDO $pdo, array $customer): void
{
    unset($_SESSION['cart_synced']);
    try {
        $_SESSION['cart'] = cart_account_write($pdo, $customer, $_SESSION['cart'] ?? [], true);
        $_SESSION['cart_synced'] = (int)$customer['id'];
    } catch (Throwable $e) {
        // El carrito del navegador se conserva tal cual. Sin datos personales en el registro.
        error_log('Carrito de cuenta no sincronizado al acceder: ' . get_class($e) . ' ' . $e->getMessage());
    }
}

function cart_account_clear(PDO $pdo, int $customerId): void
{
    try { $pdo->prepare('DELETE FROM cliente_carrito WHERE id_cliente=?')->execute([$customerId]); }
    catch (PDOException $e) { /* El pedido ya está confirmado; no se revierte por esto. */ }
}
