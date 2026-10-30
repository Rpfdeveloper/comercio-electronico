<?php
declare(strict_types=1);

// Ejecutar únicamente contra una instalación local y una BD desechable *_test.
$base = rtrim(getenv('TIENDA_TEST_URL') ?: '', '/');
$dsn = getenv('TIENDA_TEST_DSN') ?: '';
if (!in_array(parse_url($base, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true) ||
    !preg_match('/dbname=([a-zA-Z0-9_]+_test)(;|$)/', $dsn) ||
    !preg_match('/host=(127\.0\.0\.1|localhost)(;|$)/', $dsn)) {
    fwrite(STDERR, "Configura TIENDA_TEST_URL local y TIENDA_TEST_DSN con una base *_test.\n");
    exit(2);
}
$pdo = new PDO($dsn, getenv('TIENDA_TEST_USER') ?: 'root', getenv('TIENDA_TEST_PASS') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
// Las fixtures Unicode deben usar la misma codificación que la aplicación.
$pdo->exec('SET NAMES utf8mb4');
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    ++$checks;
    echo "OK $message\n";
}
class TestBrowser {
    private $curl;
    public $token = '';
    public $status;
    public $headers = [];
    public function __construct() {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [CURLOPT_COOKIEFILE => '', CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20]);
        curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, function ($handle, string $header): int {
            if (strpos($header, ':') !== false) {
                [$name, $value] = explode(':', $header, 2);
                $this->headers[strtolower(trim($name))] = trim($value);
            }
            return strlen($header);
        });
    }
    public function prepare(string $path, ?array $post = null, bool $withToken = true) {
        global $base;
        $this->headers = [];
        if ($post !== null && $withToken) { $post['csrf_token'] = $this->token; }
        curl_setopt($this->curl, CURLOPT_URL, $base . '/' . $path);
        if ($post !== null) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($post));
        } else {
            curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        }
        return $this->curl;
    }
    public function receive($body): string {
        if ($body === false || curl_errno($this->curl)) {
            throw new RuntimeException(curl_error($this->curl));
        }
        $this->status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $body, $m)) { $this->token = $m[1]; }
        if (preg_match('/Fatal error|Warning:|Deprecated:/', $body)) { throw new RuntimeException('Error PHP en respuesta HTTP'); }
        return $body;
    }
    public function request(string $path, ?array $post = null, bool $withToken = true): string {
        return $this->receive(curl_exec($this->prepare($path, $post, $withToken)));
    }
    public function copySession(): self {
        $copy = new self();
        foreach (curl_getinfo($this->curl, CURLINFO_COOKIELIST) as $cookie) {
            curl_setopt($copy->curl, CURLOPT_COOKIELIST, $cookie);
        }
        $copy->token = $this->token;
        return $copy;
    }
    public function __destruct() { curl_close($this->curl); }
}
