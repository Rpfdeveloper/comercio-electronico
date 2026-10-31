<?php
declare(strict_types=1);

function checkout_fields(): array
{
    return [
        'email' => ['label'=>'Correo electrónico', 'max'=>160, 'autocomplete'=>'email', 'type'=>'email', 'required'=>true],
        'nombre' => ['label'=>'Nombre completo', 'max'=>120, 'autocomplete'=>'shipping name', 'type'=>'text', 'required'=>true],
        'direccion' => ['label'=>'Dirección de envío', 'max'=>200, 'autocomplete'=>'shipping street-address', 'type'=>'text', 'required'=>true],
        'cp' => ['label'=>'Código postal', 'max'=>12, 'autocomplete'=>'shipping postal-code', 'type'=>'text', 'required'=>true],
        'poblacion' => ['label'=>'Población', 'max'=>120, 'autocomplete'=>'shipping address-level2', 'type'=>'text', 'required'=>true],
        'provincia' => ['label'=>'Provincia', 'max'=>120, 'autocomplete'=>'shipping address-level1', 'type'=>'text', 'required'=>false],
        'pais' => ['label'=>'País', 'max'=>120, 'autocomplete'=>'shipping country-name', 'type'=>'text', 'required'=>true],
    ];
}

function checkout_validate(array $data): array
{
    $errors = [];
    if (!in_array($data['tipo'], ['registrado', 'invitado'], true)) {
        $errors['tipo'] = 'Elige cómo quieres comprar.';
    }
    foreach (checkout_fields() as $key => $field) {
        $value = $data[$key];
        if ($value === '' && $field['required']) {
            $errors[$key] = 'Completa el campo «' . $field['label'] . '».';
        } elseif (strlen($value) > $field['max'] * 4) {
            $errors[$key] = 'Usa como máximo ' . $field['max'] . ' caracteres.';
        } elseif (!preg_match('//u', $value) || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            $errors[$key] = 'Introduce texto válido, sin saltos de línea ni caracteres de control.';
        } elseif (preg_match_all('/./us', $value) > $field['max']) {
            $errors[$key] = 'Usa como máximo ' . $field['max'] . ' caracteres.';
        }
    }
    if (!isset($errors['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Introduce un correo electrónico válido.';
    }
    return $errors;
}
