<?php

declare(strict_types=1);

use Muh\Core\Config;
use Muh\Core\Translator;

/**
 * Global helper functions.
 */

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('__')) {
    /** Translate a key with replacements. */
    function __(string $key, array $replace = []): string
    {
        return Translator::instance()->translate($key, $replace);
    }
}

if (!function_exists('trans')) {
    function trans(string $key, array $replace = []): string
    {
        return Translator::instance()->translate($key, $replace);
    }
}

if (!function_exists('e')) {
    /** Escape HTML output (XSS protection). */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = config('app.url', '');
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('request_path')) {
    /**
     * Application-relative path of the current request, with the sub-directory
     * base prefix (e.g. /muh or /muh/public) stripped. Use for redirect/return
     * parameters (e.g. theme toggle) so we never double-apply the base.
     */
    function request_path(): string
    {
        $full = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $uri = (string) parse_url($full, PHP_URL_PATH);
        $uri = $uri ?: '/';

        $baseUrl = Config::get('app.url', '');
        $basePath = (string) parse_url($baseUrl, PHP_URL_PATH);
        if ($basePath && $basePath !== '/') {
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
        // Preserve the query string so return-to-page keeps GET filters (e.g.
        // /app/current-accounts?type=customer) after toggling language/theme.
        $query = (string) parse_url($full, PHP_URL_QUERY);
        return $query !== '' ? $uri . '?' . $query : $uri;
    }
}

if (!function_exists('route_query')) {
    /**
     * Current request path + query string, with the given query parameters
     * overridden. Uses request_path() so the base sub-directory is preserved
     * and existing GET filters are kept (e.g. type=, company_id=, search=).
     * Pass null/'' for a param to remove it.
     */
    function route_query(array $overrides = []): string
    {
        $path = request_path();
        [$base, $qs] = array_pad(explode('?', $path, 2), 2, null);
        $params = [];
        if ($qs) {
            parse_str($qs, $params);
        }
        foreach ($overrides as $k => $v) {
            if ($v === null || $v === '') {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
        }
        return $base . (count($params) ? '?' . http_build_query($params) : '');
    }
}

if (!function_exists('paginate')) {
    /**
     * SQL-level pagination helper for list queries (no GROUP BY).
     * Converts `SELECT ... FROM ...` to a COUNT, applies LIMIT/OFFSET, and
     * clamps the page. Returns items + paging metadata.
     */
    function paginate(string $selectSql, array $params, int $perPage = 25, string $pageKey = 'page'): array
    {
        $page = max(1, (int) ($_GET[$pageKey] ?? 1));
        $countSql = preg_replace('/^\s*SELECT\s+.*?\s+FROM\s+/is', 'SELECT COUNT(*) FROM ', trim($selectSql));
        $countParams = [];
        foreach ($params as $k => $v) {
            if (is_scalar($v)) {
                $countParams[$k] = $v;
            }
        }
        $total = (int) \Muh\Core\DB::scalar($countSql, $countParams);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $items = \Muh\Core\DB::select($selectSql . ' LIMIT ' . $offset . ', ' . $perPage, $params);
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'lastPage' => $lastPage,
            'perPage' => $perPage,
        ];
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return config('app.url', '') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        $token = \Muh\Core\Session::get('_csrf');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            \Muh\Core\Session::set('_csrf', $token);
        }
        return $token;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): \Muh\Core\Response
    {
        return \Muh\Core\Response::redirect(url($path), $status);
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('money')) {
    /**
     * Format a DECIMAL as money for the active locale.
     * tr -> 1.234,56 ₺ | en -> ₺1,234.56 (or en_GB style)
     */
    function money(float|string|int|null $amount, ?string $currency = null): string
    {
        $amount = (float) ($amount ?? 0);
        $locale = Translator::instance()->locale();
        $currency = $currency ?: config('app.default_currency', 'TRY');

        $symbols = [
            'TRY' => '₺',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        ];
        $symbol = $symbols[$currency] ?? $currency;

        if ($locale === 'tr') {
            $formatted = number_format($amount, 2, ',', '.');
            return $formatted . ' ' . $symbol;
        }
        // English / international
        $formatted = number_format($amount, 2, '.', ',');
        return $symbol . $formatted;
    }
}

if (!function_exists('money_value')) {
    /** Raw decimal with dot separator, 2 digits — for inputs & API. */
    function money_value(float|string|int|null $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, '.', '');
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, ?string $format = null): string
    {
        if (!$date) {
            return '';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }
        $locale = Translator::instance()->locale();
        $format = $format ?: ($locale === 'tr' ? 'd.m.Y' : 'Y-m-d');
        return date($format, $ts);
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $date): string
    {
        return format_date($date, Translator::instance()->locale() === 'tr' ? 'd.m.Y H:i' : 'Y-m-d H:i');
    }
}

if (!function_exists('random_uuid')) {
    function random_uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }
}

if (!function_exists('str_slug')) {
    function str_slug(string $text, string $separator = '-'): string
    {
        $text = trim(strtolower($text));
        $turkish = ['ç','ğ','ı','i','ö','ş','ü'];
        $english  = ['c','g','i','i','o','s','u'];
        $text = str_replace($turkish, $english, $text);
        $text = preg_replace('/[^a-z0-9]+/', $separator, $text);
        return trim($text, $separator);
    }
}

if (!function_exists('app_log')) {
    function app_log(string $message, string $level = 'INFO'): void
    {
        $file = config('app.filesystem.logs', dirname(__DIR__, 2) . '/storage/logs') . '/app.log';
        $line = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $message . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('format_bytes')) {
    function format_bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $b = (float) $bytes;
        while ($b >= 1024 && $i < count($units) - 1) {
            $b /= 1024;
            $i++;
        }
        return number_format($b, $i > 0 ? 1 : 0, ',', '.') . ' ' . $units[$i];
    }
}
