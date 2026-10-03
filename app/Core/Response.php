<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * HTTP Response abstraction.
 */
final class Response
{
    private int $status;
    private array $headers;
    private string $content;

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function make(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers);
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = json_encode(['error' => 'JSON encoding failed']);
            $status = 500;
        }
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        return new self($json, $status, $headers);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        // Resolve app-relative paths (e.g. /app/dashboard) to absolute URLs that
        // include the configured base path (e.g. /muh) so redirects work under
        // XAMPP sub-folders and at the web root alike.
        if ($url !== '' && $url[0] === '/' && !str_starts_with($url, '//')) {
            $url = \url($url);
        }
        return new self('', $status, ['Location' => $url]);
    }

    public static function download(string $path, string $filename, string $mime): self
    {
        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        return new self((string) file_get_contents($path), 200, $headers);
    }

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->content;
    }
}
