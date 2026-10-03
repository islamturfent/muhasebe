<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * Central application container / bootstrap.
 */
final class Application
{
    public static function boot(string $basePath): void
    {
        date_default_timezone_set(Config::get('app.timezone', 'UTC'));

        // Load all config files.
        Config::load($basePath . '/config');

        // Connect to the DB.
        DB::connect(Config::get('database'));

        // Start session.
        Session::start();

        // Restore a persistent 'remember me' login if no session exists yet.
        Auth::attemptRememberMe();

        // Detect locale from session (set at login) or default.
        $locale = Session::get('locale');
        if (!$locale) {
            $locale = Config::get('app.locale', 'tr');
            // Platform default locale (super admin Localization settings).
            try {
                $v = DB::scalar("SELECT value FROM settings WHERE `group` = 'platform' AND `key` = 'default_locale'");
                if ($v) {
                    $locale = $v;
                }
            } catch (\Throwable $e) {
                // DB unavailable during CLI/migration — keep config default.
            }
        }
        Translator::instance()->setLocale($locale);
        Translator::instance()->load($basePath . '/resources/lang');
    }
}
