<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * HTTP Request wrapper.
 */
final class Request
{
    private array $query;
    private array $body;
    private array $files;
    private array $server;
    private array $headers;

    public function __construct(array $query = null, array $body = null, array $files = null, array $server = null)
    {
        $this->query   = $query   ?? $_GET;
        $this->body    = $body    ?? $this->parseBody();
        $this->files   = $files   ?? $_FILES;
        $this->server  = $server  ?? $_SERVER;
        $this->headers = $this->parseHeaders();
    }

    private function parseBody(): array
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (stripos($contentType, 'application/json') !== false) {
                $raw = file_get_contents('php://input');
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } else {
                return $_POST;
            }
        }
        return $_POST ?? [];
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = $uri ?: '/';

        // Strip the sub-directory base path (e.g. /muh or /muh/public) so routes
        // match at the web root (dev server), under a XAMPP sub-folder, or when a
        // root .htaccess forwards /muh to the public/ web-root.
        $baseUrl = Config::get('app.url', '');
        $basePath = (string) parse_url($baseUrl, PHP_URL_PATH);
        if ($basePath && $basePath !== '/') {
            // Try longest prefix first so both /muh and /muh/public work.
            $candidates = [$basePath, rtrim($basePath, '/') . '/public'];
            usort($candidates, fn ($a, $b) => strlen($b) <=> strlen($a));
            foreach ($candidates as $prefix) {
                if ($prefix && ($uri === $prefix || str_starts_with($uri, rtrim($prefix, '/') . '/'))) {
                    $uri = substr($uri, strlen($prefix));
                    break;
                }
            }
        }
        $uri = $uri === '' ? '/' : $uri;
        return $uri;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }
        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }
        return $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->input($key);
        }
        return $result;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && ($this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if ($auth && preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public function ip(): string
    {
        $ip = $this->server['HTTP_X_FORWARDED_FOR'] ?? $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
        if (is_string($ip) && str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return $ip ?: '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('Accept');
        if (is_string($accept) && stripos($accept, 'application/json') !== false) {
            return true;
        }
        return stripos($this->server['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    }
}
