<?php
declare(strict_types=1);

function admin_catalog_field(string $name, string $label, $value, string $type = 'text', int $length = 255, bool $required = false): void
{
    $id = 'edit-' . trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $name), '-');
    ?>
    <div class="field"><label for="<?= h($id) ?>"><?= h($label) ?></label>
    <?php if ($type === 'textarea'): ?><textarea id="<?= h($id) ?>" name="<?= h($name) ?>" maxlength="<?= $length ?>" rows="6"><?= h((string)$value) ?></textarea>
    <?php else: ?><input id="<?= h($id) ?>" name="<?= h($name) ?>" type="text"<?= $type === 'decimal' ? ' inputmode="decimal"' : ($type === 'integer' ? ' inputmode="numeric"' : '') ?> maxlength="<?= $length ?>" value="<?= h((string)$value) ?>"<?= $required ? ' required' : '' ?>><?php endif; ?>
    </div>
    <?php
}

function admin_catalog_price_fields(array $data, string $prefix = ''): void
{
    $name = static fn($key) => $prefix ? $prefix . '[' . $key . ']' : $key;
    admin_catalog_field($name('precio'), 'Precio habitual (€)', $data['precio'] ?? '', 'decimal', 11, true);
    admin_catalog_field($name('precio_oferta'), 'Precio de oferta (€) · opcional', $data['precio_oferta'] ?? '', 'decimal', 11);
    admin_catalog_field($name('stock'), 'Unidades disponibles', $data['stock'] ?? '', 'integer', 6, true);
}

function admin_catalog_active_field(array $data, string $prefix = ''): void
{
    $name = $prefix ? $prefix . '[activo]' : 'activo';
    $id = 'active-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $prefix ?: 'product');
    ?><div class="field"><label for="<?= h($id) ?>">Visibilidad</label><select id="<?= h($id) ?>" name="<?= h($name) ?>"><option value="0"<?= (string)($data['activo'] ?? '0') !== '1' ? ' selected' : '' ?>>Inactivo</option><option value="1"<?= (string)($data['activo'] ?? '0') === '1' ? ' selected' : '' ?>>Activo</option></select></div><?php
}

function admin_catalog_overlay(array $data, array $input): array
{
    foreach ($data as $key=>$value) {
        if (array_key_exists($key, $input)) { $data[$key] = is_string($input[$key]) && strlen($input[$key]) <= 40000 && preg_match('//u', $input[$key]) ? $input[$key] : ''; }
    }
    return $data;
}
