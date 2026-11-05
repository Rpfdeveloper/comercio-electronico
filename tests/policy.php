<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
$password = getenv('TIENDA_TEST_ADMIN_PASS') ?: '';
if ($password === '') { throw new RuntimeException('Define TIENDA_TEST_ADMIN_PASS'); }
$marker = 'politica-' . bin2hex(random_bytes(4));
$category = null; $brand = null; $product = null;
$policyHidden = false;
$lastChange = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM tienda_politica_cambio')->fetchColumn();
$policy = static fn(): array => $pdo->query('SELECT iva,envio,envio_gratis_desde FROM tienda_politica WHERE id=1')->fetch();
$version = static function (string $html): string { preg_match('/name="version" value="([a-f0-9]{64})"/', $html, $m); return $m[1] ?? ''; };
$checkout = static function (TestBrowser $b, string $email): string {
    return $b->request('public/checkout.php', ['step'=>'form', 'tipo'=>'invitado', 'email'=>$email, 'nombre'=>'Cliente de prueba',
        'direccion'=>'Calle ficticia 1', 'cp'=>'28000', 'poblacion'=>'Madrid', 'pais'=>'España']);
};
try {
    $pdo->exec('DELETE FROM acceso_intento');
    check($policy() === ['iva'=>'21.00', 'envio'=>'0.00', 'envio_gratis_desde'=>null], 'la migracion conserva la regla anterior');
    $anon = new TestBrowser();
    $anon->request('admin/ajustes.php');
    check($anon->status === 302 && ($anon->headers['location'] ?? '') === 'login.php', 'ajustes requieren sesion administrativa');
    $b = new TestBrowser();
    $b->request('admin/login.php');
    $b->request('admin/login.php', ['user'=>'admin', 'pass'=>$password]);
    $html = $b->request('admin/ajustes.php');
    check($b->status === 200 && strpos($html, 'value="21,00"') !== false && strpos($html, 'data-example-total>60,50 €') !== false && strpos($html, 'aria-current="page">Ajustes de venta') !== false, 'muestra la politica vigente con un ejemplo calculado');
    $v1 = $version($html);
    $valid = ['iva'=>'10', 'envio'=>'6,00', 'envio_gratis_desde'=>'60', 'version'=>$v1];
    foreach ([['iva'=>'30,01'], ['iva'=>'-1'], ['iva'=>'abc'], ['iva'=>'10.123'], ['iva'=>''], ['iva'=>['10']], ['envio'=>'1000,01'], ['envio'=>'5 €'],
        ['envio_gratis_desde'=>'0'], ['envio_gratis_desde'=>'100000,01'], ['envio_gratis_desde'=>"60\0"]] as $bad) {
        $html = $b->request('admin/ajustes.php', $bad + $valid);
        check($b->status === 422 && $policy()['iva'] === '21.00' && strpos($html, 'No se ha guardado') !== false, 'rechaza ' . json_encode($bad));
    }
    check(strpos($html, 'aria-invalid="true" aria-describedby="error-envio_gratis_desde"') !== false && strpos($html, 'value="6,00"') !== false, 'error asociado al campo y valores conservados');
    $b->request('admin/ajustes.php', $valid, false);
    check($b->status === 403 && $policy()['iva'] === '21.00', 'sin CSRF no se cambia la politica');
    $b->request('admin/ajustes.php', ['version'=>str_repeat('0', 64)] + $valid);
    check($b->status === 409 && $policy()['iva'] === '21.00', 'formulario antiguo detecta conflicto');
    $b->request('admin/ajustes.php');
    $b->request('admin/ajustes.php', $valid);
    check($b->status === 303 && $policy() === ['iva'=>'10.00', 'envio'=>'6.00', 'envio_gratis_desde'=>'60.00'], 'politica guardada con coma decimal');
    $html = $b->request('admin/ajustes.php');
    check(strpos($html, 'Política guardada') !== false && strpos($html, 'data-example-total>61,60 €') !== false, 'ejemplo recalculado con envio e IVA nuevos');
    check(strpos($html, 'Antes:</span> IVA 21 % · envío gratis') !== false && strpos($html, 'Después:</span> IVA 10 % · envío 6,00 € · gratis desde 60,00 €') !== false, 'historial legible del cambio');
    $b->request('admin/ajustes.php', ['version'=>$version($html)] + $valid);
    check($b->status === 303 && (int)$pdo->query('SELECT COUNT(*) FROM tienda_politica_cambio WHERE id>' . $lastChange)->fetchColumn() === 1, 'guardar sin cambios no anade historial');
    $b->request('admin/ajustes.php', ['version'=>$v1] + $valid);
    check($b->status === 409, 'la version anterior ya no es valida');

    // La tienda aplica la política en carrito, resumen y pedido.
    $pdo->prepare('INSERT INTO categoria(nombre,slug,activa) VALUES (?,?,1)')->execute(['Colección política', $marker]);
    $category = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute(['Marca política', $marker]);
    $brand = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,precio,stock,activo) VALUES (?,?,?,?,?,?,1)')->execute([$category, $brand, $marker . ' perfume', $marker, 20, 10]);
    $product = (int)$pdo->lastInsertId();
    $c = new TestBrowser();
    $c->request('public/catalogo.php');
    $c->request('public/carrito.php', ['action'=>'add', 'id_producto'=>$product, 'id_variante'=>'', 'cantidad'=>2]);
    $html = $c->request('public/carrito.php');
    check(strpos($html, 'IVA (10%)') !== false && strpos($html, '6,00 €') !== false && strpos($html, '50,60 €') !== false, 'carrito suma envio e IVA sobre productos y envio');
    check(strpos($html, 'Te faltan 20,00 € en productos para el envío gratis') !== false, 'carrito indica cuanto falta para el envio gratis');
    $c->request('public/carrito.php', ['action'=>'update', 'cantidad'=>['p' . $product=>'3']]);
    $html = $c->request('public/carrito.php');
    check(strpos($html, '<dd>Gratis</dd>') !== false && strpos($html, '66,00 €') !== false && strpos($html, 'Envío gratis aplicado a partir de 60,00 €') !== false, 'envio gratis desde el umbral inclusivo');
    $c->request('public/carrito.php', ['action'=>'update', 'cantidad'=>['p' . $product=>'2']]);
    $html = $checkout($c, $marker . '@example.invalid');
    check(strpos($html, 'Confirmar pedido') !== false && strpos($html, '50,60 €') !== false, 'resumen muestra el total con la politica');
    // Cambio de política entre el resumen y la confirmación: no se cobra un importe distinto del mostrado.
    $pdo->exec("UPDATE tienda_politica SET envio=9.00 WHERE id=1");
    $html = $c->request('public/checkout.php', ['step'=>'confirm']);
    $orders = $pdo->prepare('SELECT importe_productos,gastos_envio,impuestos,total FROM pedido WHERE email=?');
    $orders->execute([$marker . '@example.invalid']);
    check($c->status === 409 && strpos($html, 'Los impuestos o los gastos de envío han cambiado') !== false && !$orders->fetchAll(), 'cambio de politica durante la compra exige revisar el resumen');
    $pdo->exec("UPDATE tienda_politica SET envio=6.00 WHERE id=1");
    $checkout($c, $marker . '@example.invalid');
    $html = $c->request('public/checkout.php', ['step'=>'confirm']);
    $orders->execute([$marker . '@example.invalid']);
    check(strpos($html, 'Pedido realizado') !== false && $orders->fetchAll() === [['importe_productos'=>'40.00', 'gastos_envio'=>'6.00', 'impuestos'=>'4.60', 'total'=>'50.60']], 'pedido guarda productos, envio, IVA y total mostrados');
    $pdo->exec("UPDATE tienda_politica SET iva=21.00, envio=0.00, envio_gratis_desde=NULL WHERE id=1");
    $orders->execute([$marker . '@example.invalid']);
    check($orders->fetch()['total'] === '50.60', 'cambiar la politica no altera pedidos registrados');
    $curl = $b->prepare('admin/ajustes.php'); curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
    $b->receive(curl_exec($curl)); curl_setopt($curl, CURLOPT_CUSTOMREQUEST, null);
    check($b->status === 405, 'metodos no previstos rechazados');

    // Una política ilegible nunca se convierte en IVA 21 % y envío gratuito.
    $c->request('public/catalogo.php');
    $c->request('public/carrito.php', ['action'=>'add', 'id_producto'=>$product, 'cantidad'=>1]);
    $checkout($c, $marker . '@example.invalid');
    $countBefore = (int)$pdo->query('SELECT COUNT(*) FROM pedido')->fetchColumn();
    $stockBefore = (int)$pdo->query('SELECT stock FROM producto WHERE id=' . $product)->fetchColumn();
    $pdo->exec('RENAME TABLE tienda_politica TO tienda_politica_pausa'); $policyHidden = true;
    $html = $c->request('public/carrito.php');
    check($c->status === 503 && strpos($html, 'Total no disponible') !== false && strpos($html, 'class="commerce-primary" disabled') !== false, 'sin politica no se ofrece un total de sustitucion');
    $html = $c->request('public/checkout.php', ['step'=>'confirm']);
    check($c->status === 503 && strpos($html, 'No podemos calcular') !== false && (int)$pdo->query('SELECT COUNT(*) FROM pedido')->fetchColumn() === $countBefore, 'confirmar sin politica no registra pedidos');
    check((int)$pdo->query('SELECT stock FROM producto WHERE id=' . $product)->fetchColumn() === $stockBefore, 'fallo de politica conserva stock');
    $pdo->exec('RENAME TABLE tienda_politica_pausa TO tienda_politica'); $policyHidden = false;
    $pdo->exec('DELETE FROM tienda_politica WHERE id=1');
    $html = $c->request('public/checkout.php');
    check($c->status === 503, 'fila de politica ausente tambien detiene la compra');
    $pdo->exec('INSERT INTO tienda_politica(id) VALUES (1)');
    $html = $c->request('public/carrito.php');
    check($c->status === 200 && strpos($html, '24,20 €') !== false, 'recuperar la politica conserva el carrito y su total real');
    echo "PASS $checks comprobaciones de politica comercial\n";
} finally {
    if ($policyHidden) { $pdo->exec('RENAME TABLE tienda_politica_pausa TO tienda_politica'); }
    $pdo->exec('INSERT IGNORE INTO tienda_politica(id) VALUES (1)');
    $pdo->exec("UPDATE tienda_politica SET iva=21.00, envio=0.00, envio_gratis_desde=NULL WHERE id=1");
    $pdo->exec('DELETE FROM tienda_politica_cambio WHERE id>' . $lastChange);
    $ids = $pdo->prepare('SELECT id FROM pedido WHERE email=?'); $ids->execute([$marker . '@example.invalid']);
    foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $id) { $pdo->prepare('DELETE FROM pedido WHERE id=?')->execute([$id]); }
    if ($product !== null) { $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$product]); }
    if ($brand !== null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
    if ($category !== null) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$category]); }
    $pdo->exec('DELETE FROM acceso_intento');
}
