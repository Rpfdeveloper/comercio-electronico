<?php
declare(strict_types=1);

// Dirección de la carpeta pública, configurada por el operador, nunca por Host.
function seo_public_base(): string
{
    $value = rtrim(getenv('TIENDA_PUBLIC_URL') ?: '', '/');
    if (strlen($value) > 500 || !filter_var($value, FILTER_VALIDATE_URL) ||
        preg_match('/[\\\\\x00-\x20\x7f]/', $value)) { return ''; }
    $parts = parse_url($value);
    if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['port']) ||
        !str_contains($parts['host'] ?? '', '.') || filter_var($parts['host'], FILTER_VALIDATE_IP) ||
        !preg_match('~^(?:/[a-zA-Z0-9_-]+)*$~D', $parts['path'] ?? '')) { return ''; }
    return $value;
}

function seo_ready(): bool
{
    return getenv('TIENDA_INDEXABLE') === 'true' && !APP_DEBUG && seo_public_base() !== '';
}

function seo_absolute(string $path): string
{
    $base = seo_public_base();
    return $base === '' ? '' : $base . '/' . ltrim($path, '/');
}

function seo_description(string $text): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    if (preg_match('/^.{0,157}/us', $text, $match) && strlen($match[0]) < strlen($text)) {
        return rtrim($match[0]) . '…';
    }
    return $text;
}

function seo_catalog_path(array $filters): string
{
    $path = $filters['c'] !== '' ? 'categoria.php' : 'catalogo.php';
    $params = $filters['c'] !== '' ? ['c' => $filters['c']] : [];
    if ((int)$filters['page'] > 1) { $params['page'] = $filters['page']; }
    return $path . ($params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
}

// Datos estructurados schema.org de una ficha: una oferta por formato activo, con el precio
// y la disponibilidad que muestra la página. Los precios se publican sin IVA, como en la ficha.
function seo_product_jsonld(array $product, array $formats): string
{
    $offer = static function (?string $name, $price, int $stock, ?string $sku): array {
        $amount = number_format((float)$price, 2, '.', '');
        $data = ['@type'=>'Offer', 'price'=>$amount, 'priceCurrency'=>'EUR',
            'priceSpecification'=>['@type'=>'UnitPriceSpecification', 'price'=>$amount, 'priceCurrency'=>'EUR', 'valueAddedTaxIncluded'=>false],
            'availability'=>'https://schema.org/' . ($stock > 0 ? 'InStock' : 'OutOfStock'),
            'itemCondition'=>'https://schema.org/NewCondition'];
        if ($name !== null) { $data['name'] = $name; }
        if ($sku !== null && $sku !== '') { $data['sku'] = $sku; }
        return $data;
    };
    $offers = [];
    if ((int)$product['variantes'] > 0) {
        foreach ($formats as $format) {
            $offers[] = $offer((int)$format['capacidad_ml'] . ' ml', $format['precio'], (int)$format['stock'], $format['sku'] ?? null);
        }
    } elseif ($product['precio_base'] !== null) {
        $offers[] = $offer(null, $product['precio_base'], (int)$product['stock'], null);
    }
    $data = ['@context'=>'https://schema.org', '@type'=>'Product', 'name'=>$product['nombre'],
        'description'=>seo_description((string)($product['descripcion_larga'] ?: $product['descripcion_corta'] ?: $product['nombre'])),
        'brand'=>['@type'=>'Brand', 'name'=>$product['marca']], 'category'=>category_label((string)$product['categoria'])];
    $url = seo_absolute('producto.php?p=' . rawurlencode((string)$product['slug']));
    if ($url !== '') {
        $data['url'] = $url;
        if (!empty($product['imagen_principal'])) { $data['image'] = seo_absolute((string)$product['imagen_principal']); }
        foreach ($offers as &$item) { $item['url'] = $url; }
        unset($item);
    }
    if ($offers) { $data['offers'] = count($offers) === 1 ? $offers[0] : $offers; }
    // JSON_HEX_* impide cerrar la etiqueta script o inyectar marcado desde los textos del catálogo.
    return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
