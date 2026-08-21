<?php

namespace App\Services\Ecommerce\Pazaryeri;

use App\Models\Ecommerce\EcommercePazaryeriEntegrasyon;

class AmazonSiparisAdaptor extends AbstractPazaryeriSiparisAdaptor
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function siparisleriGetir(EcommercePazaryeriEntegrasyon $entegrasyon): array
    {
        $kimlik = (array) ($entegrasyon->kimlik_bilgileri ?? []);

        return $this->varsayilanApiCagri($entegrasyon, [
            'x-api-key' => (string) ($kimlik['api_key'] ?? ''),
            'x-api-secret' => (string) ($kimlik['api_secret'] ?? ''),
            'x-seller-id' => (string) ($kimlik['satici_id'] ?? ''),
        ]);
    }
}

