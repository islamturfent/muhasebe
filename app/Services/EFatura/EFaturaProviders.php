<?php

declare(strict_types=1);

namespace Muh\Services\EFatura;

/**
 * Registry of supported e-Fatura integrator providers.
 *
 * Each provider maps to the generic REST adapter with integrator-specific
 * endpoint conventions (document send / status query paths). New integrators
 * can be added here without touching the gateway logic.
 */
final class EFaturaProviders
{
    /**
     * Provider key => [label translation key, endpoints].
     * - label: used in the tenant e-Fatura settings UI.
     * - documents/status: relative paths under the (mode-scoped) base URL.
     */
    private const MAP = [
        'entegrator' => [
            'label'     => 'efatura.provider_rest',
            'documents' => '/documents',
            'status'    => '/documents/{uuid}',
        ],
        'logo' => [
            'label'     => 'efatura.provider_logo',
            'documents' => '/einvoice/documents',
            'status'    => '/einvoice/documents/{uuid}',
        ],
        'foriba' => [
            'label'     => 'efatura.provider_foriba',
            'documents' => '/api/v1/einvoice/documents',
            'status'    => '/api/v1/einvoice/documents/{uuid}',
        ],
        'izibiz' => [
            'label'     => 'efatura.provider_izibiz',
            'documents' => '/api/einvoice/documents',
            'status'    => '/api/einvoice/documents/{uuid}',
        ],
    ];

    /** Provider keys that are real HTTP integrators (backed by REST gateway). */
    private const REAL = ['entegrator', 'logo', 'foriba', 'izibiz'];

    /** @return array<string,string> provider key => label translation key */
    public static function labels(): array
    {
        $out = [];
        foreach (self::MAP as $key => $def) {
            $out[$key] = $def['label'];
        }
        return $out;
    }

    /** Provider keys handled by the real REST adapter (not simulated). */
    public static function real(): array
    {
        return self::REAL;
    }

    public static function isReal(string $provider): bool
    {
        return in_array($provider, self::REAL, true);
    }

    /** Endpoint paths for a provider (falls back to generic /documents). */
    public static function endpoints(string $provider): array
    {
        $def = self::MAP[$provider] ?? self::MAP['entegrator'];
        return ['documents' => $def['documents'], 'status' => $def['status']];
    }
}
