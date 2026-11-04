<?php
declare(strict_types=1);

// Colecciones (tabla categoria) y marcas: alta y edición sin borrado, porque los perfumes
// y los pedidos históricos las referencian. Los enlaces se fijan al crear.
require_once __DIR__ . '/admin_catalog.php';

function admin_taxonomy_types(): array
{
    return [
        'coleccion' => ['table'=>'categoria', 'fk'=>'id_categoria', 'singular'=>'colección', 'fields'=>['nombre','descripcion','activa','orden']],
        'marca' => ['table'=>'marca', 'fk'=>'id_marca', 'singular'=>'marca', 'fields'=>['nombre','descripcion','pais']],
    ];
}

function admin_taxonomy_type($value): string
{
    if (!is_string($value) || !array_key_exists($value, admin_taxonomy_types())) { throw new OutOfBoundsException('Tipo no válido.'); }
    return $value;
}

function admin_taxonomy_list(PDO $pdo, string $type): array
{
    $cfg = admin_taxonomy_types()[$type];
    $extra = $type === 'coleccion' ? 't.activa,t.orden' : 't.pais';
    $order = $type === 'coleccion' ? 't.orden,t.nombre,t.id' : 't.nombre,t.id';
    return $pdo->query("SELECT t.id,t.nombre,t.slug,$extra,
        (SELECT COUNT(*) FROM producto p WHERE p.{$cfg['fk']}=t.id) AS perfumes,
        (SELECT COUNT(*) FROM producto p WHERE p.{$cfg['fk']}=t.id AND p.activo=1) AS activos
        FROM {$cfg['table']} t ORDER BY $order")->fetchAll();
}

function admin_taxonomy_read(PDO $pdo, string $type, int $id, bool $lock = false): array
{
    $cfg = admin_taxonomy_types()[$type];
    $st = $pdo->prepare("SELECT * FROM {$cfg['table']} WHERE id=?" . ($lock ? ' FOR UPDATE' : ''));
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { throw new OutOfBoundsException('No encontrada.'); }
    return $row;
}

// Huella de los valores editables: detecta formularios abiertos antes de otro cambio.
function admin_taxonomy_version(string $type, array $row): string
{
    $data = [];
    foreach (array_merge(['slug'], admin_taxonomy_types()[$type]['fields']) as $field) { $data[$field] = (string)($row[$field] ?? ''); }
    return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
}

function admin_taxonomy_values(string $type, array $input, bool $create): array
{
    $errors = []; $data = [];
    $read = static function (string $key, callable $fn) use (&$errors, &$data): void {
        try { $data[$key] = $fn(); } catch (InvalidArgumentException $e) { $errors[$key] = $e->getMessage(); }
    };
    $read('nombre', static function () use ($input): string {
        $value = admin_catalog_text($input, 'nombre', 120);
        if ($value === '') { throw new InvalidArgumentException('Escribe un nombre.'); }
        return $value;
    });
    if ($create) {
        $read('slug', static function () use ($input): string {
            $value = admin_catalog_text($input, 'slug', 140);
            if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $value)) { throw new InvalidArgumentException('Usa letras minúsculas, números y guiones, sin espacios.'); }
            return $value;
        });
    }
    $read('descripcion', static fn() => admin_catalog_text($input, 'descripcion', 2000, true));
    if ($type === 'coleccion') {
        $read('activa', static function () use ($input): int {
            $value = $input['activa'] ?? null;
            if ($value !== '0' && $value !== '1') { throw new InvalidArgumentException('Elige si la colección es visible.'); }
            return (int)$value;
        });
        $read('orden', static fn() => admin_catalog_integer($input, 'orden', 0, 9999));
    } else {
        $read('pais', static fn() => admin_catalog_text($input, 'pais', 80));
    }
    if ($errors) { throw new CatalogValidation($errors); }
    foreach (['descripcion', 'pais'] as $optional) { if (($data[$optional] ?? null) === '') { $data[$optional] = null; } }
    return $data;
}

// Devuelve el identificador guardado. Las altas de colecciones nacen ocultas, como los perfumes.
function admin_taxonomy_write(PDO $pdo, string $type, int $id, array $input): int
{
    $cfg = admin_taxonomy_types()[$type];
    $data = admin_taxonomy_values($type, $input, $id === 0);
    if ($id === 0 && $type === 'coleccion') { $data['activa'] = 0; }
    $pdo->beginTransaction();
    try {
        if ($id !== 0) {
            $before = admin_taxonomy_read($pdo, $type, $id, true);
            if (!is_string($input['version'] ?? null) || !hash_equals(admin_taxonomy_version($type, $before), $input['version'])) {
                throw new CatalogConflict('Esta ' . $cfg['singular'] . ' ha cambiado desde que abriste el formulario. Recarga sus datos; no se ha sobrescrito ningún cambio.');
            }
            if ($type === 'coleccion' && (int)$before['activa'] === 1 && $data['activa'] === 0) {
                $st = $pdo->prepare('SELECT COUNT(*) FROM producto WHERE id_categoria=? AND activo=1');
                $st->execute([$id]);
                $visible = (int)$st->fetchColumn();
                if ($visible > 0 && ($input['confirmar_ocultar'] ?? null) !== '1') {
                    throw new CatalogValidation(['activa'=>'Ocultar la colección retira de la tienda ' . ($visible === 1 ? 'su único perfume activo' : 'sus ' . $visible . ' perfumes activos') . '. Marca la confirmación para continuar.']);
                }
            }
            $set = implode(',', array_map(static fn($f) => "$f=?", array_keys($data)));
            $pdo->prepare("UPDATE {$cfg['table']} SET $set WHERE id=?")->execute(array_merge(array_values($data), [$id]));
        } else {
            // Comprobación explícita: no depende de que la tabla tenga índice único.
            $st = $pdo->prepare("SELECT id FROM {$cfg['table']} WHERE slug=? FOR UPDATE");
            $st->execute([$data['slug']]);
            if ($st->fetch()) { throw new CatalogValidation(['slug'=>'Ese enlace ya existe. Usa otro.']); }
            $columns = implode(',', array_keys($data));
            $marks = implode(',', array_fill(0, count($data), '?'));
            $pdo->prepare("INSERT INTO {$cfg['table']}($columns) VALUES ($marks)")->execute(array_values($data));
            $id = (int)$pdo->lastInsertId();
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) {
            throw new CatalogValidation(['slug'=>'Ese enlace ya existe. Usa otro.']);
        }
        throw $e;
    }
}
