<?php

declare(strict_types=1);

namespace Muh\Core;

use RuntimeException;

/**
 * Server-rendered view engine.
 *
 * Views live in resources/views and are plain PHP templates using the
 * helpers in app/helpers.php. A view may declare a layout through a
 * "layout" key in its data; the captured content is injected into the
 * layout via the $content variable.
 */
final class View
{
    private static ?self $instance = null;
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            $base = dirname(__DIR__, 2) . '/resources/views';
            self::$instance = new self($base);
        }
        return self::$instance;
    }

    public function render(string $view, array $data = []): string
    {
        $layout = $data['layout'] ?? null;

        $file = $this->resolve($view);
        $data['__view'] = $this;

        $content = $this->capture($file, $data);

        if ($layout) {
            $layoutFile = $this->resolve($layout);
            return $this->capture($layoutFile, array_merge($data, ['content' => $content]));
        }

        return $content;
    }

    /** Render a partial/file inline (available as $this->partial(...) in views). */
    public function partial(string $view, array $data = []): string
    {
        $file = $this->resolve($view);
        return $this->capture($file, $data);
    }

    public function exists(string $view): bool
    {
        return is_file($this->path($view));
    }

    private function resolve(string $view): string
    {
        $file = $this->path($view);
        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$view}");
        }
        return $file;
    }

    private function path(string $view): string
    {
        return $this->basePath . '/' . str_replace('.', '/', $view) . '.php';
    }

    private function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
