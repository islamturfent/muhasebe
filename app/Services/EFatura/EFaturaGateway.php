<?php

declare(strict_types=1);

namespace Muh\Services\EFatura;

/**
 * e-Fatura / e-Arşiv integrator contract.
 *
 * Real integrators (GİB Entegratör, Logo, Foriba, e-Belge...) implement this
 * interface with a test and a production mode. The application only talks to
 * this abstraction, so swapping providers never touches core logic.
 */
interface EFaturaGateway
{
    /** Send an e-document. Input: normalized document payload. */
    public function sendDocument(array $payload): array;

    /** Query the current status of a previously sent document. */
    public function getStatus(string $uuid): string;
}
