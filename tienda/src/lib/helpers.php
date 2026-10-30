<?php
declare(strict_types=1);

// Escapa texto para salida HTML
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Genera una URL interna a partir de BASE_URL
function url(string $path = ''): string {
    $base = rtrim(BASE_URL, '/');
    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

// Genera la URL de un recurso estático (CSS, JS, imágenes)
function asset(string $path): string {
    return url($path);
}