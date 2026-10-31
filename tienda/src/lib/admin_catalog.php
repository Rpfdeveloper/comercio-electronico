<?php
declare(strict_types=1);

class CatalogValidation extends DomainException
{
    public array $errors;
    public function __construct(array $errors) { parent::__construct('Revisa los datos indicados.'); $this->errors = $errors; }
}
class CatalogConflict extends RuntimeException {}

function admin_catalog_images(): array
{
    $root = __DIR__ . '/../../public/';
    $files = glob($root . 'img/products/*/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
    $images = array_map(static fn($path) => substr($path, strlen($root)), $files);
    sort($images);
    return $images;
}

function admin_catalog_text(array $input, string $key, int $limit, bool $multiline = false): string
{
    $value = $input[$key] ?? '';
    if (!is_string($value) || strlen($value) > $limit * 4 || !preg_match('//u', $value) ||
        preg_match($multiline ? '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/' : '/[\x00-\x1f\x7f]/', $value) ||
        preg_match_all('/./us', $value) > $limit) {
        throw new InvalidArgumentException('Introduce texto válido de hasta ' . $limit . ' caracteres.');
    }
    return trim($value);
}

function admin_catalog_integer(array $input, string $key, int $min = 0, int $max = 999999): int
{
    $value = admin_catalog_text($input, $key, 10);
    if (!preg_match('/\A(?:0|[1-9]\d*)\z/', $value) || (int)$value < $min || (int)$value > $max) {
        throw new InvalidArgumentException("Introduce un entero entre $min y $max.");
    }
    return (int)$value;
}

function admin_catalog_money(array $input, string $key, bool $optional = false): ?string
{
    $value = str_replace(',', '.', admin_catalog_text($input, $key, 11));
    if ($value === '' && $optional) { return null; }
    if (!preg_match('/\A\d{1,8}(?:\.\d{1,2})?\z/', $value)) {
        throw new InvalidArgumentException('Introduce un precio positivo con hasta dos decimales.');
    }
    [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');
    $cents = (int)$whole * 100 + (int)str_pad($decimal, 2, '0');
    if ($cents < 1) { throw new InvalidArgumentException('El precio debe ser mayor que cero.'); }
    return intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
}

function admin_catalog_values(array $input, bool $variant = false): array
{
    $data = []; $errors = [];
    $fields = $variant ? ['capacidad_ml', 'sku'] : ['nombre', 'id_categoria', 'id_marca', 'descripcion_corta', 'descripcion_larga', 'imagen_principal'];
    foreach (array_merge($fields, ['precio', 'precio_oferta', 'stock', 'activo']) as $key) {
        try {
            if (in_array($key, ['precio','precio_oferta'], true)) { $data[$key] = admin_catalog_money($input, $key, $key === 'precio_oferta'); }
            elseif (in_array($key, ['id_categoria','id_marca'], true)) { $data[$key] = admin_catalog_integer($input, $key, 1, 2147483647); }
            elseif ($key === 'capacidad_ml') { $data[$key] = admin_catalog_integer($input, $key, 1, 10000); }
            elseif ($key === 'stock') { $data[$key] = admin_catalog_integer($input, $key); }
            elseif ($key === 'activo') { $data[$key] = admin_catalog_integer($input, $key, 0, 1); }
            else {
                $limit = ['nombre'=>180,'sku'=>64,'descripcion_corta'=>255,'descripcion_larga'=>10000,'imagen_principal'=>255][$key];
                $data[$key] = admin_catalog_text($input, $key, $limit, $key === 'descripcion_larga');
                if ($key === 'nombre' && $data[$key] === '') { throw new InvalidArgumentException('Indica el nombre del perfume.'); }
                if ($key === 'sku' && $data[$key] !== '' && !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/', $data[$key])) { throw new InvalidArgumentException('Usa letras, números, guion, punto o guion bajo.'); }
                if ($key === 'imagen_principal' && $data[$key] !== '' && !in_array($data[$key], admin_catalog_images(), true)) { throw new InvalidArgumentException('Selecciona una imagen del proyecto o deja el campo vacío.'); }
            }
        } catch (InvalidArgumentException $e) { $errors[$key] = $e->getMessage(); }
    }
    if (isset($data['precio'], $data['precio_oferta']) && (float)$data['precio_oferta'] > (float)$data['precio']) { $errors['precio_oferta'] = 'La oferta no puede superar el precio habitual.'; }
    if ($errors) { throw new CatalogValidation($errors); }
    return $data;
}

function admin_catalog_read(PDO $pdo, int $id, bool $lock = false): array
{
    $st = $pdo->prepare('SELECT * FROM producto WHERE id=?' . ($lock ? ' FOR UPDATE' : ''));
    $st->execute([$id]); $product = $st->fetch();
    if (!$product) { throw new OutOfBoundsException('El perfume no existe.'); }
    $st = $pdo->prepare('SELECT * FROM producto_variante WHERE id_producto=? ORDER BY id' . ($lock ? ' FOR UPDATE' : ''));
    $st->execute([$id]); $variants = $st->fetchAll();
    $st = $pdo->prepare('SELECT COALESCE(MAX(id),0) FROM catalogo_cambio WHERE id_producto=?');
    $st->execute([$id]);
    return ['product'=>$product, 'variants'=>$variants, 'revision'=>(string)$st->fetchColumn()];
}

function admin_catalog_version(array $snapshot): string
{
    return hash_hmac('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), auth_secret());
}

function admin_catalog_changes(?array $before, array $after): array
{
    $labels = ['nombre'=>'Nombre','slug'=>'Enlace','id_categoria'=>'Categoría (ID)','id_marca'=>'Marca (ID)',
        'descripcion_corta'=>'Descripción breve','descripcion_larga'=>'Descripción completa','imagen_principal'=>'Imagen',
        'precio'=>'Precio (€)','precio_oferta'=>'Oferta (€)','stock'=>'Unidades','activo'=>'Activo (1 sí, 0 no)', 'capacidad_ml'=>'Capacidad (ml)','sku'=>'SKU'];
    $changes = [];
    $compare = static function (array $old, array $new, string $prefix) use (&$changes, $labels): void {
        foreach ($labels as $field=>$label) {
            if (!array_key_exists($field, $new)) { continue; }
            $a = $old[$field] ?? null; $b = $new[$field];
            if ((string)$a !== (string)$b || ($a === null) !== ($b === null)) { $changes[] = ['campo'=>$prefix . $label, 'antes'=>$a, 'despues'=>$b]; }
        }
    };
    $compare($before['product'] ?? [], $after['product'], 'Perfume · ');
    $oldVariants = array_column($before['variants'] ?? [], null, 'id');
    foreach ($after['variants'] as $variant) { $compare($oldVariants[$variant['id']] ?? [], $variant, 'Formato #' . $variant['id'] . ' · '); }
    return $changes;
}

function admin_catalog_write(PDO $pdo, int $id, array $input): int
{
    $action = $input['action'] ?? '';
    if (!is_string($action) || !in_array($action, $id ? ['save','add_variant'] : ['create'], true)) { throw new CatalogValidation(['action'=>'La acción no es válida.']); }
    $pdo->beginTransaction();
    try {
        $before = $id ? admin_catalog_read($pdo, $id, true) : null;
        if ($before && (!is_string($input['version'] ?? null) || !hash_equals(admin_catalog_version($before), $input['version']))) {
            throw new CatalogConflict('El perfume ha cambiado desde que abriste el formulario. Recarga sus datos antes de guardar; no se ha sobrescrito ningún cambio.');
        }
        if ($action === 'add_variant') {
            if (!$before['variants'] && (int)$before['product']['stock'] !== 0) { throw new CatalogValidation(['stock'=>'El formato base debe tener cero unidades antes de añadir el primer formato. Ajusta primero sus existencias.']); }
            if (count($before['variants']) >= 100) { throw new CatalogValidation(['variants'=>'El panel admite hasta 100 formatos por perfume.']); }
            $data = admin_catalog_values($input, true);
            foreach ($before['variants'] as $v) { if ((int)$v['capacidad_ml'] === $data['capacidad_ml']) { throw new CatalogValidation(['capacidad_ml'=>'Ya existe un formato con esa capacidad. Edita el existente.']); } }
            $pdo->prepare('INSERT INTO producto_variante(id_producto,capacidad_ml,sku,precio,precio_oferta,es_oferta,stock,activo) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$id,$data['capacidad_ml'],$data['sku'] ?: null,$data['precio'],$data['precio_oferta'],(int)($data['precio_oferta'] !== null),$data['stock'],$data['activo']]);
        } else {
            // Con variantes, el stock y los precios base no se editan ni se suman.
            $source = $input;
            if ($before && $before['variants']) {
                foreach (['precio','precio_oferta','stock'] as $field) { $source[$field] = (string)($before['product'][$field] ?? ''); }
                // Los valores heredados de base pueden ser cero: son inertes con variantes.
                $source['precio'] = '1.00'; $source['precio_oferta'] = ''; $source['stock'] = '0';
            }
            if (!$id) { $source['activo'] = '0'; }
            $data = admin_catalog_values($source);
            foreach (['categoria'=>'id_categoria', 'marca'=>'id_marca'] as $table=>$field) {
                $st = $pdo->prepare("SELECT id FROM $table WHERE id=? LOCK IN SHARE MODE"); $st->execute([$data[$field]]);
                if (!$st->fetch()) { throw new CatalogValidation([$field=>'Selecciona una opción existente.']); }
            }
            if ($id) {
                if ($before['variants']) { foreach (['precio','precio_oferta','stock'] as $field) { $data[$field] = $before['product'][$field]; } }
                $pdo->prepare('UPDATE producto SET nombre=?,id_categoria=?,id_marca=?,descripcion_corta=?,descripcion_larga=?,imagen_principal=?,precio=?,precio_oferta=?,es_oferta=?,stock=?,activo=? WHERE id=?')
                    ->execute([$data['nombre'],$data['id_categoria'],$data['id_marca'],$data['descripcion_corta'],$data['descripcion_larga'],$data['imagen_principal'] ?: null,$data['precio'],$data['precio_oferta'],($before['variants'] ? $before['product']['es_oferta'] : (int)((float)$data['precio_oferta'] > 0)),$data['stock'],$data['activo'],$id]);
                $posted = $input['variants'] ?? [];
                if (!is_array($posted) || array_map('strval', array_keys($posted)) !== array_map('strval', array_column($before['variants'], 'id'))) { throw new CatalogValidation(['variants'=>'Los formatos del formulario no coinciden. Recarga el perfume.']); }
                foreach ($before['variants'] as $variant) {
                    $row = $posted[$variant['id']];
                    if (!is_array($row)) { throw new CatalogValidation(['variants'=>'Revisa los datos de los formatos.']); }
                    try { $v = admin_catalog_values($row, true); }
                    catch (CatalogValidation $e) { throw new CatalogValidation(['variants'=>'Formato ' . (int)$variant['capacidad_ml'] . ' ml: ' . implode(' ', $e->errors)]); }
                    // Capacidad y SKU son identidad del formato; se conservan en las ediciones.
                    if ($v['capacidad_ml'] !== (int)$variant['capacidad_ml'] || $v['sku'] !== (string)$variant['sku']) { throw new CatalogValidation(['variants'=>'La capacidad y el SKU de un formato existente no se modifican.']); }
                    $pdo->prepare('UPDATE producto_variante SET precio=?,precio_oferta=?,es_oferta=?,stock=?,activo=? WHERE id=? AND id_producto=?')
                        ->execute([$v['precio'],$v['precio_oferta'],(int)($v['precio_oferta'] !== null),$v['stock'],$v['activo'],$variant['id'],$id]);
                }
            } else {
                $slug = admin_catalog_text($input, 'slug', 200);
                if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug)) { throw new CatalogValidation(['slug'=>'Usa letras minúsculas, números y guiones, sin espacios.']); }
                $pdo->prepare('INSERT INTO producto(nombre,slug,id_categoria,id_marca,descripcion_corta,descripcion_larga,imagen_principal,precio,precio_oferta,es_oferta,stock,activo) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)')
                    ->execute([$data['nombre'],$slug,$data['id_categoria'],$data['id_marca'],$data['descripcion_corta'],$data['descripcion_larga'],$data['imagen_principal'] ?: null,$data['precio'],$data['precio_oferta'],(int)($data['precio_oferta'] !== null),$data['stock']]);
                $id = (int)$pdo->lastInsertId();
            }
        }
        $after = admin_catalog_read($pdo, $id, true);
        $changes = admin_catalog_changes($before, $after);
        if ($changes) { $pdo->prepare('INSERT INTO catalogo_cambio(id_producto,accion,cambios) VALUES (?,?,?)')->execute([$id,$action,json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]); }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) { throw new CatalogValidation(['duplicate'=>'El enlace del perfume o el SKU ya existe. Usa otro valor.']); }
        throw $e;
    }
}
