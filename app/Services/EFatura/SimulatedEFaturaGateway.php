<?php

declare(strict_types=1);

namespace Muh\Services\EFatura;

use Muh\Core\Config;

/**
 * Default integrator for dev/test. In "test" mode it simulates the full
 * lifecycle; in "production" mode it refuses to send until a real integrator
 * is configured (so no fake documents can slip through).
 */
final class SimulatedEFaturaGateway implements EFaturaGateway
{
    public function sendDocument(array $payload): array
    {
        $mode = Config::get('efatura.mode', 'test');

        if ($mode === 'production') {
            return [
                'status' => 'error',
                'uuid' => $payload['uuid'] ?? '',
                'message' => 'Real e-Fatura integrator is not configured (production).',
            ];
        }

        // Simulate: sending -> sent -> accepted (idempotent-ish per run).
        usleep(200000); // small delay feels realistic
        return [
            'status' => 'sent',
            'uuid' => $payload['uuid'] ?? '',
            'message' => 'Simulated send OK',
            'envelope_id' => 'ENV-' . random_int(100000, 999999),
        ];
    }

    public function getStatus(string $uuid): string
    {
        return 'sent';
    }
}
