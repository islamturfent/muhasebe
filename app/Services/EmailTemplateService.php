<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Translator;

/**
 * Branded, localized e-mail templating.
 *
 * All automated e-mails (invoice reminders, invites, subscription notices,
 * verification, notifications) are rendered through this service so they share
 * a consistent Hesap360 look and are produced in the recipient's locale.
 *
 * `translate()` allows rendering a translation key for an explicit locale
 * (not just the current HTTP session locale), which is what batch/background
 * e-mails need when the recipient is an accounting office or client in a
 * different language.
 */
final class EmailTemplateService
{
    /** Render a translation key for an explicit locale. */
    public static function translate(string $locale, string $key, array $replace = []): string
    {
        $locale = self::norm($locale);
        return Translator::instance()->translate($key, $replace, $locale);
    }

    /** Normalize to a supported locale. */
    public static function norm(string $locale): string
    {
        return $locale === 'en' ? 'en' : 'tr';
    }

    /** Wrap a localised body fragment in a consistent branded HTML e-mail layout. */
    public static function layout(string $locale, string $title, string $bodyHtml): string
    {
        $locale = self::norm($locale);
        $brand = self::translate($locale, 'common.product_name') ?: 'Hesap360';
        $address = self::translate($locale, 'mail.footer_address') ?: '';
        $help = self::translate($locale, 'mail.help_line', ['brand' => $brand]);

        return '<!DOCTYPE html><html lang="' . $locale . '"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;"><tr><td align="center" style="padding:24px 12px;">'
            . '<table role="presentation" width="100%" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">'
            . '<tr><td style="background:#2b55e0;padding:20px 24px;">'
            . '<span style="color:#ffffff;font-size:18px;font-weight:700;">' . $brand . '</span>'
            . '</td></tr>'
            . '<tr><td style="padding:24px;">'
            . '<h1 style="margin:0 0 16px;font-size:18px;color:#0f172a;">' . $title . '</h1>'
            . '<div style="font-size:14px;line-height:1.6;color:#334155;">' . $bodyHtml . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:16px 24px;border-top:1px solid #e2e8f0;background:#f8fafc;color:#64748b;font-size:12px;">'
            . $help
            . ($address !== '' ? '<br>' . $address : '')
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /** Send a branded, localized e-mail: wraps $bodyHtml in the layout and dispatches via Mailer. */
    public static function send(string $locale, string $to, string $subject, string $bodyHtml): bool
    {
        $locale = self::norm($locale);
        $html = self::layout($locale, $subject, $bodyHtml);
        return (new Mailer())->send($to, $subject, $html);
    }

    /** Render a CTA link/button in the mail-safe inline style. */
    public static function button(string $locale, string $url, string $label): string
    {
        $url = e($url);
        $label = e($label);
        return '<p style="margin:20px 0;"><a href="' . $url . '" style="display:inline-block;padding:11px 20px;background:#2b55e0;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;">'
            . $label . '</a></p>'
            . '<p style="font-size:12px;color:#64748b;word-break:break-all;">' . $url . '</p>';
    }
}
