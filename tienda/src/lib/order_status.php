<?php
declare(strict_types=1);

// Estados conocidos y flujo permitido. Los estados desconocidos no tienen salidas.
const ORDER_STATUS_NOTE_LIMIT = 500;

class OrderStatusValidation extends RuntimeException {}
class OrderStatusConflict extends RuntimeException {}

function order_status_label(string $state): string
{
    $labels = ['pending'=>'Pendiente', 'paid'=>'Pago registrado', 'processing'=>'En preparación',
        'shipped'=>'Enviado', 'canceled'=>'Cancelado'];
    return $labels[$state] ?? 'En revisión';
}

function order_status_transitions(string $state): array
{
    $flow = ['pending'=>['paid', 'canceled'], 'paid'=>['processing', 'canceled'],
        'processing'=>['shipped', 'canceled'], 'shipped'=>[], 'canceled'=>[]];
    return $flow[$state] ?? [];
}

function order_status_action_label(string $state): string
{
    $labels = ['paid'=>'Registrar pago comprobado', 'processing'=>'Pasar a preparación',
        'shipped'=>'Marcar como enviado', 'canceled'=>'Cancelar y reponer existencias'];
    return $labels[$state] ?? $state;
}

// Unidades que volverían al stock si se cancela: una fila por referencia.
function order_status_restock_lines(PDO $pdo, int $orderId): array
{
    $st = $pdo->prepare('SELECT id_producto,id_variante,SUM(unidades) AS unidades FROM linea_pedido
        WHERE id_pedido=? GROUP BY id_producto,id_variante ORDER BY id_variante IS NULL, id_variante, id_producto');
    $st->execute([$orderId]);
    return $st->fetchAll();
}

function order_status_history(PDO $pdo, int $orderId): array
{
    $st = $pdo->prepare('SELECT h.id,h.nota,h.unidades_repuestas,h.fecha,a.descripcion AS anterior,n.descripcion AS nuevo
        FROM pedido_estado_cambio h JOIN estado_pedido a ON a.id=h.estado_anterior JOIN estado_pedido n ON n.id=h.estado_nuevo
        WHERE h.id_pedido=? ORDER BY h.id DESC LIMIT 50');
    $st->execute([$orderId]);
    return $st->fetchAll();
}

function order_status_change(PDO $pdo, int $orderId, array $input): array
{
    foreach (['estado_actual', 'estado_nuevo'] as $key) {
        if (!is_string($input[$key] ?? null) || !preg_match('/\A[a-z]{1,20}\z/', $input[$key])) {
            throw new OrderStatusValidation('Elige uno de los cambios de estado disponibles.');
        }
    }
    $note = $input['nota'] ?? '';
    if (!is_string($note) || preg_match('//u', $note) !== 1 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $note)) {
        throw new OrderStatusValidation('La nota contiene caracteres no válidos.');
    }
    $note = trim(str_replace("\r\n", "\n", $note));
    if (preg_match_all('/./su', $note) > ORDER_STATUS_NOTE_LIMIT) {
        throw new OrderStatusValidation('La nota admite hasta ' . ORDER_STATUS_NOTE_LIMIT . ' caracteres.');
    }
    $target = $input['estado_nuevo'];
    if ($target === 'canceled' && ($input['confirmar'] ?? null) !== '1') {
        throw new OrderStatusValidation('Marca la confirmación para cancelar el pedido y reponer sus existencias.');
    }
    for ($attempt = 1; ; ++$attempt) {
        $pdo->beginTransaction();
        try {
            // El bloqueo del pedido serializa cambios simultáneos y confirmaciones repetidas.
            $st = $pdo->prepare('SELECT p.id,p.estado,e.descripcion FROM pedido p JOIN estado_pedido e ON e.id=p.estado WHERE p.id=? FOR UPDATE');
            $st->execute([$orderId]);
            $order = $st->fetch();
            if (!$order) { throw new OutOfBoundsException('Pedido no encontrado.'); }
            if (!hash_equals($order['descripcion'], $input['estado_actual'])) {
                throw new OrderStatusConflict('El pedido ha cambiado de estado desde que abriste la página. Revisa su estado actual; no se ha aplicado ningún cambio.');
            }
            if (!in_array($target, order_status_transitions($order['descripcion']), true)) {
                throw new OrderStatusValidation('Ese cambio de estado no está permitido para este pedido.');
            }
            $st = $pdo->prepare('SELECT id FROM estado_pedido WHERE descripcion=?');
            $st->execute([$target]);
            $targetId = $st->fetchColumn();
            if ($targetId === false) { throw new OrderStatusValidation('El estado elegido no existe en esta instalación.'); }
            $restored = 0;
            if ($target === 'canceled') {
                // Antes del envío las unidades siguen en el almacén: vuelven a estar disponibles.
                foreach (order_status_restock_lines($pdo, $orderId) as $line) {
                    $variant = $line['id_variante'] !== null;
                    $table = $variant ? 'producto_variante' : 'producto';
                    $up = $pdo->prepare("UPDATE $table SET stock = COALESCE(stock,0) + ? WHERE id = ?");
                    $up->execute([(int)$line['unidades'], $variant ? $line['id_variante'] : $line['id_producto']]);
                    if ($up->rowCount() !== 1) {
                        throw new OrderStatusValidation('Una referencia del pedido ya no existe y no se pueden reponer sus unidades. No se ha cancelado.');
                    }
                    $restored += (int)$line['unidades'];
                }
            }
            $pdo->prepare('UPDATE pedido SET estado=? WHERE id=?')->execute([(int)$targetId, $orderId]);
            $pdo->prepare('INSERT INTO pedido_estado_cambio(id_pedido,estado_anterior,estado_nuevo,nota,unidades_repuestas) VALUES (?,?,?,?,?)')
                ->execute([$orderId, (int)$order['estado'], (int)$targetId, $note === '' ? null : $note, $restored]);
            $pdo->commit();
            return ['from'=>$order['descripcion'], 'to'=>$target, 'restored'=>$restored];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            // Interbloqueo con una compra simultánea: InnoDB revierte todo y se puede repetir.
            if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1213 && $attempt < 3) { continue; }
            throw $e;
        }
    }
}
