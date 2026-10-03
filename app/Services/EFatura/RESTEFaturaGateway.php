<?php

declare(strict_types=1);

namespace Muh\Services\EFatura;

use Muh\Core\Config;

/**
 * Generic REST e-Fatura / e-Arşiv integrator adapter.
 *
 * Connects to a real integrator (GİB entegratör, Logo, Foriba, e-Belge, ...)
 * over HTTP:
 *   POST {base}/{mode}/documents        -> send a document
 *   GET  {base}/{mode}/documents/{uuid} -> query status
 * Basic auth with EFATURA_USERNAME/PASSWORD.
 *
 * The integrator is expected to return JSON like:
 *   {"uuid":"...","envelope_id":"...","status":"sent","message":"..."}
 */
final class RESTEFaturaGateway implements EFaturaGateway
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $provider = '';

    /**
     * @param array $cfg optional overrides: provider, mode, test_url, production_url, username, password
     *                   (falls back to the env-based efatura.* config)
     */
    public function __construct(?array $cfg = null)
    {
        $cfg = $cfg ?? [];
        $this->provider = (string) ($cfg['provider'] ?? Config::get('efatura.provider', 'entegrator'));
        $mode = $cfg['mode'] ?? Config::get('efatura.mode', 'test');
        $base = $mode === 'production'
            ? ($cfg['production_url'] ?? Config::get('efatura.production_url', ''))
            : ($cfg['test_url'] ?? Config::get('efatura.test_url', ''));
        $this->baseUrl = rtrim((string) $base, '/');
        $this->username = (string) ($cfg['username'] ?? Config::get('efatura.username', ''));
        $this->password = (string) ($cfg['password'] ?? Config::get('efatura.password', ''));
    }

    public function configured(): bool
    {
        return $this->baseUrl !== '' && $this->username !== '';
    }

    public function sendDocument(array $payload): array
    {
        if (!$this->configured()) {
            return ['status' => 'error', 'uuid' => $payload['uuid'] ?? '', 'message' => 'e-Fatura REST integrator not configured.'];
        }
        $eps = EFaturaProviders::endpoints($this->provider);
        $res = $this->request('POST', $eps['documents'], $payload);
        return [
            'status' => in_array($res['status'] ?? null, ['sent', 'accepted', 'rejected', 'error'], true) ? $res['status'] : 'error',
            'uuid' => $res['uuid'] ?? ($payload['uuid'] ?? ''),
            'envelope_id' => $res['envelope_id'] ?? null,
            'message' => $res['message'] ?? null,
        ];
    }

    public function getStatus(string $uuid): string
    {
        if (!$this->configured()) {
            return 'error';
        }
        $eps = EFaturaProviders::endpoints($this->provider);
        $res = $this->request('GET', str_replace('{uuid}', urlencode($uuid), $eps['status']));
        return (string) ($res['status'] ?? 'error');
    }

    /** @return string The active integrator provider key. */
    public function provider(): string
    {
        return $this->provider;
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password),
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
        ]);
        if ($method === 'POST' && $body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) {
            return ['status' => 'error', 'message' => "HTTP {$code}"];
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
