<?php

namespace App\Services\Ecommerce\Pazaryeri;

use App\Models\Ecommerce\EcommercePazaryeriEntegrasyon;

class EbaySiparisAdaptor extends AbstractPazaryeriSiparisAdaptor
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function siparisleriGetir(EcommercePazaryeriEntegrasyon $entegrasyon): array
    {
        $kimlik = (array) ($entegrasyon->kimlik_bilgileri ?? []);
        $token = trim((string) ($kimlik['access_token'] ?? $kimlik['api_key'] ?? ''));

        return $this->varsayilanApiCagri($entegrasyon, [
            'Authorization' => $token !== '' ? 'Bearer '.$token : '',
            'x-client-id' => (string) ($kimlik['client_id'] ?? ''),
            'x-client-secret' => (string) ($kimlik['client_secret'] ?? ''),
        ]);
    }
}

