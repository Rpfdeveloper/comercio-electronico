<?php
declare(strict_types=1);

// Bandeja de correo saliente. Modo actual: «registro». Los mensajes se guardan para revisarlos
// en el panel, pero no se envían: el envío real se conectará al elegir un proveedor (SMTP o API).
function mail_queue(PDO $pdo, string $type, ?string $reference, string $to, string $subject, string $body): bool
{
    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) { return false; }
    // Sin saltos de línea en cabeceras: evita inyectar destinatarios o cabeceras al enviar de verdad.
    $subject = trim(preg_replace('/[\r\n\t]+/', ' ', $subject) ?? '');
    preg_match('/\A.{0,200}/us', $subject, $cut);
    $subject = $cut[0] ?? '';
    $st = $pdo->prepare('INSERT IGNORE INTO correo_saliente(tipo,referencia,destinatario,asunto,cuerpo) VALUES (?,?,?,?,?)');
    $st->execute([$type, $reference, $to, $subject, $body]);
    return $st->rowCount() === 1;
}

// Confirmación de pedido. Nunca impide registrar el pedido: un fallo solo deja el correo sin guardar.
function mail_order_confirmation(PDO $pdo, int $orderId, string $to, string $name, array $items, array $totals): void
{
    $lines = [];
    foreach ($items as $item) {
        $lines[] = sprintf('- %s%s × %d: %s', $item['nombre'], $item['capacidad_ml'] ? ' (' . (int)$item['capacidad_ml'] . ' ml)' : '',
            (int)$item['cantidad'], money_eur((float)$item['subtotal']));
    }
    $body = "Hola, $name:\n\nHemos registrado tu pedido #$orderId en Perfume Shop.\n\n" . implode("\n", $lines) .
        "\n\nProductos: " . money_eur((float)$totals['products']) .
        "\nEnvío: " . ((float)$totals['shipping'] > 0 ? money_eur((float)$totals['shipping']) : 'gratis') .
        "\nIVA: " . money_eur((float)$totals['tax']) .
        "\nTotal: " . money_eur((float)$totals['total']) .
        "\n\nEste pedido no supone ningún cobro: la tienda es un prototipo sin pasarela de pago. Si has comprado con cuenta, puedes consultar su estado en Mi cuenta.\n";
    try { mail_queue($pdo, 'pedido', (string)$orderId, $to, 'Pedido #' . $orderId . ' registrado', $body); }
    catch (PDOException $e) { /* Migración 008 pendiente o fallo puntual: el pedido ya está confirmado. */ }
}
