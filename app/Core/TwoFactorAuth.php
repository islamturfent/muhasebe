<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * RFC 6238 TOTP for MFA/2FA — no external dependencies.
 */
final class TwoFactorAuth
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $length = 32): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }
        return $secret;
    }

    public static function provisioningUri(string $secret, string $email, ?string $issuer = null): string
    {
        $issuer = $issuer ?: config('app.security.mfa_issuer', 'MUH Accounting');
        $label = $issuer . ':' . $email;
        return 'otpauth://totp/' . rawurlencode($label)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    /** Base32 decode. */
    private static function base32Decode(string $data): string
    {
        $map = array_flip(str_split(self::ALPHABET));
        $data = rtrim(strtoupper($data), '=');
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin($map[$c] ?? 0), 5, '0', STR_PAD_LEFT);
        }
        $octets = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $octets .= chr(bindec($byte));
            }
        }
        return $octets;
    }

    /** Generate a TOTP code for a given time (unix seconds). */
    public static function code(string $secret, ?int $time = null): string
    {
        $time = $time ?? time();
        $counter = pack('N*', 0) . pack('N*', intdiv((int) $time, 30));
        $hash = hash_hmac('sha1', $counter, self::base32Decode($secret), true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Verify a user-entered code allowing a ±1 time-step window. */
    public static function verify(string $secret, string $code): bool
    {
        $code = trim($code);
        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $time = time();
        foreach ([-1, 0, 1] as $window) {
            if (hash_equals(self::code($secret, $time + ($window * 30)), $code)) {
                return true;
            }
        }
        return false;
    }
}
