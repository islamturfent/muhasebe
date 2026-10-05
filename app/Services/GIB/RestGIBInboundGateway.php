<?php

declare(strict_types=1);

namespace Muh\Services\GIB;

/**
 * Gerçek GİB e-Belge (Express) REST adapter stub.
 * Canlı kimlik/uç noktasıyla gerçek HTTP çağrısı yapar; canlı kredi olmadan
 * simulated sürücüye düşülür.
 */
final class RestGIBInboundGateway implements GIBInboundGateway
{
    public function __construct(private array $cfg)
    {
    }

    public function configured(): bool
    {
        return (string) ($this->cfg['test_url'] ?? '') !== ''
            && ((string) ($this->cfg['token'] ?? '') !== '' || (string) ($this->cfg['username'] ?? '') !== '');
    }

    public function fetch(string $docType, string $from, string $to): array
    {
        if (!$this->configured()) {
            throw new \RuntimeException('GİB e-Belge REST entegrasyonu yapılandırılmamış (simulated kullanın)');
        }
        // Gerçek GİB/entegratör API çağrısı canlı krediyle burada yapılır.
        throw new \RuntimeException('REST e-Belge çekme, canlı kimlikle kullanılır (Faz 2: simulated)');
    }
}
