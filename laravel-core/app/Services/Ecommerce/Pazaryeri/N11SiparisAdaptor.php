<?php

namespace App\Services\Ecommerce\Pazaryeri;

use App\Models\Ecommerce\EcommercePazaryeriEntegrasyon;

class N11SiparisAdaptor extends AbstractPazaryeriSiparisAdaptor
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function siparisleriGetir(EcommercePazaryeriEntegrasyon $entegrasyon): array
    {
        $kimlik = (array) ($entegrasyon->kimlik_bilgileri ?? []);

        return $this->varsayilanApiCagri($entegrasyon, [
            'appKey' => (string) ($kimlik['api_key'] ?? ''),
            'appSecret' => (string) ($kimlik['api_secret'] ?? ''),
            'sellerId' => (string) ($kimlik['satici_id'] ?? ''),
        ]);
    }
}

