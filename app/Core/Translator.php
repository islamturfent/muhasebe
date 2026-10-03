<?php

declare(strict_types=1);

namespace Muh\Core;

use RuntimeException;

/**
 * Lightweight JSON-file based i18n translator (tr/en).
 *
 * Translation files live in resources/lang/{locale}/{group}.php and return
 * an associative array of key => string.
 *
 * Keys use dotted notation: invoice.title, dashboard.kpis.receivable ...
 */
final class Translator
{
    private static ?self $instance = null;
    private string $locale = 'tr';
    private array $dictionaries = [];

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function supportedLocales(): array
    {
        return Config::get('app.supported_locales', ['tr', 'en']);
    }

    public function load(string $dir): void
    {
        foreach (glob(rtrim($dir, '/') . '/*') as $localeDir) {
            $locale = basename($localeDir);
            $loaded = [];
            foreach (glob($localeDir . '/*.php') as $file) {
                $group = basename($file, '.php');
                $loaded[$group] = require $file;
            }
            $this->dictionaries[$locale] = $loaded;
        }
    }

    public function translate(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?: $this->locale;
        $fallback = Config::get('app.fallback_locale', 'tr');
        $line = $this->resolve($key, $locale) ?? $this->resolve($key, $fallback);

        if ($line === null) {
            return $key;
        }

        foreach ($replace as $search => $replacement) {
            $line = str_replace(':' . $search, (string) $replacement, $line);
        }

        return $line;
    }

    private function resolve(string $key, string $locale): ?string
    {
        $parts = explode('.', $key);
        $group = array_shift($parts);
        $dict = $this->dictionaries[$locale][$group] ?? null;
        if (!is_array($dict)) {
            return null;
        }
        $node = $dict;
        foreach ($parts as $part) {
            if (is_array($node) && array_key_exists($part, $node)) {
                $node = $node[$part];
            } else {
                return null;
            }
        }
        return is_string($node) ? $node : null;
    }

    /** Translate with fallback: if key missing, return the default string. */
    public function get(string $key, mixed $default = '', array $replace = []): string
    {
        $line = $this->translate($key, $replace);
        return $line === $key ? (string) $default : $line;
    }
}
