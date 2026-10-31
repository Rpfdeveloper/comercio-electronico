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
