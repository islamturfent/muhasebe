<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * HTTP-level security regression checker. Drives the live app over HTTP to
 * verify real request behaviours that unit tests cannot reach:
 *   - CSRF rejects token-less state-changing POSTs
 *   - Tenant isolation: a user cannot reach another tenant's data by URL
 *   - RBAC: a non-system-administrator cannot reach the /admin panel
 *   - Positive controls prove the legitimate flows still work
 *
 * Run via: php bin/muh security  (requires the web server to be running)
 */
final class SecurityChecker
{
    /** @return array{0:int,1:string,2:string} [http_code, body, final_url] */
    private static function req(string $base, string $jar, string $method, string $path, array $post = [], bool $follow = false): array
    {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $follow,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $eff = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return [$code, (string) $body, $eff];
    }

    private static function token(string $base, string $jar, string $url): string
    {
        [, $body] = self::req($base, $jar, 'GET', $url);
        if (preg_match('/name="_token" value="([^"]+)"/', $body, $m)) {
            return $m[1];
        }
        return '';
    }

    private static function login(string $base, string $jar, string $email, string $password): void
    {
        $t = self::token($base, $jar, '/login');
        self::req($base, $jar, 'POST', '/login', ['email' => $email, 'password' => $password, 'locale' => 'tr', '_token' => $t]);
    }

    public static function run(): int
    {
        $base = rtrim((string) config('app.url', 'http://localhost/muh'), '/');
        $tmp = sys_get_temp_dir();
        $results = [];
        $record = function (string $name, bool $ok, string $detail) use (&$results): void {
            $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
        };

        // ---- CSRF: POST without token must be rejected ----
        $jar = tempnam($tmp, 'csrf');
        self::login($base, $jar, 'demo@muh.local', 'Demo1234');
        [$code] = self::req($base, $jar, 'POST', '/app/current-accounts', ['name' => 'NoTokenCreate']);
        $record('CSRF: token\'sız POST reddi', in_array($code, [403, 419, 422], true), "HTTP {$code} (403 beklenir)");
        unlink($jar);

        // ---- Tenant isolation: a user cannot load another tenant's company ----
        // The authenticated test user is the demo-office owner; 'own' is a
        // company in *their* tenant, 'foreign' is a company in any OTHER tenant.
        // (The platform tenant for the super admin has no companies, so we pick
        // foreign by a tenant that actually owns a company.)
        $demoUser = DB::first("SELECT tenant_id FROM users WHERE email = 'demo@muh.local' AND deleted_at IS NULL");
        $own = null; $foreign = null;
        if ($demoUser) {
            $own = DB::first('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY id LIMIT 1', ['t' => (int) $demoUser['tenant_id']]);
            $foreignRow = DB::select(
                'SELECT c.id, c.name FROM companies c
                  WHERE c.tenant_id != :t AND c.deleted_at IS NULL
                  ORDER BY c.id LIMIT 1',
                ['t' => (int) $demoUser['tenant_id']]
            );
            $foreign = $foreignRow[0] ?? null;
        }
        if ($foreign && $own) {
            $jar = tempnam($tmp, 'ten');
            self::login($base, $jar, 'demo@muh.local', 'Demo1234'); // demo-office owner
            [, $body, $eff] = self::req($base, $jar, 'GET', '/app/companies/' . (int) $foreign['id'], [], true);
            $blocked = !str_contains($body, (string) $foreign['name']) && !str_contains($eff, '/app/companies/' . $foreign['id']);
            $record('Tenant izolasyonu: diğer tenant firmasına erişim', $blocked, 'comp-' . $foreign['id']);
            unlink($jar);
        } else {
            $record('Tenant izolasyonu', false, 'iki tenant/iki şirket gerekli (demo tenant veya ikinci ofis eksik)');
        }

        // ---- RBAC: normal owner (tenant) must NOT reach /admin ----
        $jar = tempnam($tmp, 'rbac');
        self::login($base, $jar, 'demo@muh.local', 'Demo1234');
        [, , $effAdmin] = self::req($base, $jar, 'GET', '/admin', [], true);
        $record('RBAC: ofis sahibi /admin paneline giremez', !str_contains($effAdmin, '/admin'), "final={$effAdmin}");
        unlink($jar);

        // ---- Positive control: super admin CAN reach /admin ----
        $jar = tempnam($tmp, 'adm');
        self::login($base, $jar, 'admin@muh.local', 'Admin1234!');
        [$codeA, , $effAdmin2] = self::req($base, $jar, 'GET', '/admin', [], true);
        $record('Pozitif: süper admin /admin erişir', $codeA === 200 && str_contains($effAdmin2, '/admin'), "HTTP {$codeA} final={$effAdmin2}");
        unlink($jar);

        $pass = 0;
        $fail = 0;
        foreach ($results as $r) {
            $r['ok'] ? $pass++ : $fail++;
            printf("  [%s] %s — %s\n", $r['ok'] ? 'PASS' : 'FAIL', $r['name'], $r['detail']);
        }
        printf("\n%d passed, %d failed\n", $pass, $fail);
        return $fail > 0 ? 1 : 0;
    }
}
