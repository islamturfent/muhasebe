<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * Simple file-based configuration repository.
 */
final class Config
{
    private static array $items = [];

    public static function load(string $dir): void
    {
        foreach (glob(rtrim($dir, '/') . '/*.php') as $file) {
            $key = basename($file, '.php');
            $value = require $file;
            if (is_array($value)) {
                static::$items[$key] = $value;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = static::$items;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $array = &static::$items;
        foreach ($segments as $segment) {
            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }
            $array = &$array[$segment];
        }
        $array = $value;
    }

    public static function all(): array
    {
        return static::$items;
    }
}
