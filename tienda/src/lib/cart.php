<?php
declare(strict_types=1);

function cart_integer($value, int $minimum = 1): int
{
    if (!is_int($value) && !is_string($value)) {
        throw new DomainException('Introduce una cantidad o identificador válido.');
    }
    $number = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $minimum, 'max_range' => 999999],
    ]);
    if ($number === false) {
        throw new DomainException('Introduce una cantidad o identificador válido.');
    }
    return $number;
}

// El stock de una variante es independiente; el stock del producto se usa sin variantes.
function cart_product(PDO $pdo, int $productId, ?int $variantId, bool $lock = false): array
{
    $suffix = $lock ? ' FOR UPDATE' : '';
    $st = $pdo->prepare(
        'SELECT id AS id_producto, id_categoria, nombre, precio, precio_oferta, stock, activo
         FROM producto WHERE id = ?' . $suffix
    );
    $st->execute([$productId]);
    $p = $st->fetch();
    if (!$p || !(int)$p['activo']) {
        throw new DomainException('Uno de los productos ya no está disponible.');
    }
    // Mantener la categoría activa hasta confirmar la transacción. El bloqueo
    // compartido permite compras simultáneas de productos de la misma categoría.
    $category = $pdo->prepare('SELECT activa FROM categoria WHERE id = ?' .
        ($lock ? ' LOCK IN SHARE MODE' : ''));
    $category->execute([$p['id_categoria']]);
    if ((int)$category->fetchColumn() !== 1) {
        throw new DomainException('Uno de los productos ya no está disponible.');
    }
    if ($variantId !== null) {
        $st = $pdo->prepare(
            'SELECT id AS id_variante, capacidad_ml, precio, precio_oferta, stock, activo
             FROM producto_variante WHERE id = ? AND id_producto = ?' . $suffix
        );
        $st->execute([$variantId, $productId]);
        $v = $st->fetch();
        if (!$v || !(int)$v['activo']) {
            throw new DomainException('La variante seleccionada no está disponible para este producto.');
        }
        $p = array_merge($p, $v);
    } else {
        $st = $pdo->prepare('SELECT id FROM producto_variante WHERE id_producto = ? LIMIT 1');
        $st->execute([$productId]);
        if ($st->fetch()) {
            throw new DomainException('Selecciona una capacidad disponible para este producto.');
        }
        $p['id_variante'] = null;
        $p['capacidad_ml'] = null;
    }
    $p['precio'] = (float)$p['precio_oferta'] > 0 ?
        (float)$p['precio_oferta'] : (float)$p['precio'];
    return $p;
}

function cart_items(PDO $pdo, array $cart, bool $lock = false): array
{
    // Orden estable de bloqueos de productos y variantes para reducir interbloqueos.
    uasort($cart, function (array $a, array $b): int {
        return [(int)$a['id_producto'], (int)$a['id_variante']] <=>
            [(int)$b['id_producto'], (int)$b['id_variante']];
    });
    $items = [];
    foreach ($cart as $key => $item) {
        $id = cart_integer($item['id_producto'] ?? null);
        $variant = isset($item['id_variante']) ? cart_integer($item['id_variante']) : null;
        $qty = cart_integer($item['cantidad'] ?? null);
        if ($qty > 999) {
            throw new DomainException('La cantidad máxima por producto es 999.');
        }
        $expectedKey = $variant !== null ? 'v' . $variant : 'p' . $id;
        if ($key !== $expectedKey) {
            throw new DomainException('El carrito no es válido. Vuelve a añadir el producto.');
        }
        $p = cart_product($pdo, $id, $variant, $lock);
        if ($qty > (int)$p['stock']) {
            throw new DomainException('No hay stock suficiente. Revisa las cantidades del carrito.');
        }
        $items[$key] = [
            'key' => $key,
            'id_producto' => $id,
            'id_variante' => $variant,
            'nombre' => $p['nombre'],
            'capacidad_ml' => $p['capacidad_ml'],
            'precio' => $p['precio'],
            'cantidad' => $qty,
            'subtotal' => round($p['precio'] * $qty, 2),
        ];
    }
    return $items;
}

function cart_reduce_stock(PDO $pdo, array $items): void
{
    foreach ($items as $it) {
        $variant = $it['id_variante'] !== null;
        $table = $variant ? 'producto_variante' : 'producto';
        $id = $variant ? $it['id_variante'] : $it['id_producto'];
        $st = $pdo->prepare(
            "UPDATE $table SET stock = stock - ? WHERE id = ? AND activo = 1 AND stock >= ?"
        );
        $st->execute([$it['cantidad'], $id, $it['cantidad']]);
        if ($st->rowCount() !== 1) {
            throw new DomainException('El stock ha cambiado. Revisa el carrito antes de confirmar.');
        }
    }
}

// Regla actual del prototipo, compartida por carrito, resumen y confirmación.
function cart_totals(array $items): array
{
    $products = round(array_sum(array_column($items, 'subtotal')), 2);
    $tax = round($products * 0.21, 2);
    return ['products'=>$products, 'tax'=>$tax, 'shipping'=>0.00,
        'total'=>round($products + $tax, 2)];
}
