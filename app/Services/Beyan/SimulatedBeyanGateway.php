<?php

declare(strict_types=1);

namespace Muh\Services\Beyan;

/**
 * Simulated e-Beyan: gerçek gönderim yapmaz; formu "kabul edilmiş" işaretler.
 * Test ve CI için deterministik bir GİB referansı üretir.
 */
final class SimulatedBeyanGateway implements BeyanGateway
{
    public function submit(array $form): array
    {
        $hash = strtoupper(substr(md5(json_encode($form, JSON_UNESCAPED_UNICODE)), 0, 12));
        return [
            'ok' => true,
            'reference' => 'GIB-SIM-' . $hash,
            'message' => 'GİB simulated kabul (gönderim test edildi)',
        ];
    }
}
