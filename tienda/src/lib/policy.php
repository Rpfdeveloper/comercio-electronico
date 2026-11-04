<?php
declare(strict_types=1);

// Política comercial: IVA y gastos de envío. Una sola fila; sin la migración 007 se usa
// la regla anterior (21 % y envío gratuito), de modo que la tienda sigue funcionando igual.
const POLICY_DEFAULT = ['iva'=>'21.00', 'envio'=>'0.00', 'envio_gratis_desde'=>null];

class PolicyValidation extends DomainException
{
    public array $errors;
    public function __construct(array $errors) { parent::__construct('Revisa los datos indicados.'); $this->errors = $errors; }
}
class PolicyConflict extends RuntimeException {}

function policy_read(PDO $pdo, bool $lock = false): array
{
    $st = $pdo->query('SELECT iva,envio,envio_gratis_desde FROM tienda_politica WHERE id=1' . ($lock ? ' LOCK IN SHARE MODE' : ''));
    $row = $st->fetch();
    if (!$row) { return POLICY_DEFAULT; }
    return ['iva'=>(string)$row['iva'], 'envio'=>(string)$row['envio'],
        'envio_gratis_desde'=>$row['envio_gratis_desde'] === null ? null : (string)$row['envio_gratis_desde']];
}

// Para la tienda: nunca impide vender por un fallo de lectura de la política.
function commerce_policy(PDO $pdo, bool $lock = false): array
{
    try { return policy_read($pdo, $lock); }
    catch (PDOException $e) { return POLICY_DEFAULT; }
}

function policy_version(array $policy): string
{
    return hash('sha256', json_encode($policy, JSON_THROW_ON_ERROR));
}

// Importe con coma o punto y hasta dos decimales, dentro de un rango; vacío solo si $optional.
function policy_amount($value, float $min, float $max, bool $optional = false): ?string
{
    // trim() eliminaría caracteres de control como NUL: se rechazan antes de recortar espacios.
    if (!is_string($value) || strlen($value) > 40 || preg_match('/[\x00-\x1f\x7f]/', $value)) { throw new InvalidArgumentException('Introduce un número válido.'); }
    $value = trim($value, ' ');
    if ($value === '' && $optional) { return null; }
    if (!preg_match('/\A\d{1,6}(?:[.,]\d{1,2})?\z/', $value)) { throw new InvalidArgumentException('Usa un número con hasta dos decimales, sin símbolos.'); }
    $number = (float)str_replace(',', '.', $value);
    if ($number < $min || $number > $max) {
        throw new InvalidArgumentException('Introduce un valor entre ' . rtrim(rtrim(number_format($min, 2, ',', '.'), '0'), ',') . ' y ' . number_format($max, 0, ',', '.') . '.');
    }
    return number_format($number, 2, '.', '');
}

function policy_values(array $input): array
{
    $rules = ['iva'=>[0, 30, false], 'envio'=>[0, 1000, false], 'envio_gratis_desde'=>[0.01, 100000, true]];
    $data = []; $errors = [];
    foreach ($rules as $key=>[$min, $max, $optional]) {
        try { $data[$key] = policy_amount($input[$key] ?? null, (float)$min, (float)$max, $optional); }
        catch (InvalidArgumentException $e) { $errors[$key] = $e->getMessage(); }
    }
    if ($errors) { throw new PolicyValidation($errors); }
    return $data;
}

function policy_write(PDO $pdo, array $input): array
{
    $data = policy_values($input);
    $pdo->beginTransaction();
    try {
        $st = $pdo->query('SELECT iva,envio,envio_gratis_desde FROM tienda_politica WHERE id=1 FOR UPDATE');
        $row = $st->fetch();
        if (!$row) { throw new PDOException('Falta la política comercial.'); }
        $before = ['iva'=>(string)$row['iva'], 'envio'=>(string)$row['envio'], 'envio_gratis_desde'=>$row['envio_gratis_desde'] === null ? null : (string)$row['envio_gratis_desde']];
        if (!is_string($input['version'] ?? null) || !hash_equals(policy_version($before), $input['version'])) {
            throw new PolicyConflict('La política ha cambiado desde que abriste el formulario. Revisa los valores actuales; no se ha sobrescrito nada.');
        }
        if ($data !== $before) {
            $pdo->prepare('UPDATE tienda_politica SET iva=?,envio=?,envio_gratis_desde=? WHERE id=1')->execute([$data['iva'], $data['envio'], $data['envio_gratis_desde']]);
            $pdo->prepare('INSERT INTO tienda_politica_cambio(antes,despues) VALUES (?,?)')
                ->execute([json_encode($before, JSON_THROW_ON_ERROR), json_encode($data, JSON_THROW_ON_ERROR)]);
        }
        $pdo->commit();
        return $data;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function policy_percent(string $rate): string
{
    return rtrim(rtrim(number_format((float)$rate, 2, ',', ''), '0'), ',');
}
