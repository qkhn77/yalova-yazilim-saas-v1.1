<?php

namespace App\Services\Ecommerce\Pazaryeri;

use App\Models\Ecommerce\EcommercePazaryeriEntegrasyon;

class TrendyolSiparisAdaptor extends AbstractPazaryeriSiparisAdaptor
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function siparisleriGetir(EcommercePazaryeriEntegrasyon $entegrasyon): array
    {
        $kimlik = (array) ($entegrasyon->kimlik_bilgileri ?? []);
        $saticiId = trim((string) ($kimlik['satici_id'] ?? ''));
        $apiSecret = (string) ($kimlik['api_secret'] ?? '');
        $auth = base64_encode($saticiId.':'.$apiSecret);

        return $this->varsayilanApiCagri($entegrasyon, [
            'Authorization' => 'Basic '.$auth,
            'User-Agent' => $saticiId !== '' ? $saticiId.' - SelfIntegration' : 'SelfIntegration',
        ]);
    }
}

