<?php

declare(strict_types=1);

namespace Muh\Services\Beyan;

/**
 * GİB e-Beyan gönderim soyutlaması (Faz 1). Simulated sürücü offline/test
 * akışını verir; RestBeyanGateway gerçek entegratör/GİB uç noktasıdır.
 */
interface BeyanGateway
{
    /**
     * @param array $form fully packaged declaration payload
     * @return array{ok:bool, reference:?string, message:?string}
     */
    public function submit(array $form): array;
}
