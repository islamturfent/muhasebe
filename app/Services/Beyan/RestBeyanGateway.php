<?php

declare(strict_types=1);

namespace Muh\Services\Beyan;

/**
 * Gerçek e-Beyan (GİB / entegratör) REST adapter stub.
 * Canlı uç noktası ve kimliği olmadan gönderime geçilmez; yapılandırıldığında
 * gerçek HTTP çağrısı burada yapılır.
 */
final class RestBeyanGateway implements BeyanGateway
{
    /** @var array<string,mixed> */
    private array $cfg;

    /** @param array<string,mixed> $cfg */
    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
    }

    public function configured(): bool
    {
        $url = (string) ($this->cfg['test_url'] ?? '');
        $credential = (string) ($this->cfg['token'] ?? '') !== ''
            || (string) ($this->cfg['username'] ?? '') !== '';
        return $url !== '' && $credential;
    }

    public function submit(array $form): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'reference' => null, 'message' => 'e-Beyan REST entegrasyonu yapılandırılmamış (simulated kullanın)'];
        }
        // Gerçek GİB/entegratör çağrısı burada yapılır (canlı kimlik/anahtar gerektirir).
        return ['ok' => false, 'reference' => null, 'message' => 'REST e-Beyan gönderim uç noktası canlı krediyle kullanılır (Faz 1: simulated)'];
    }
}
