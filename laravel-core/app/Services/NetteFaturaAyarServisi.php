<?php

namespace App\Services;

use App\Models\Firma;

class NetteFaturaAyarServisi
{
    public const DEFAULT_SERVICE_URL = 'https://nettefatura.isnet.net.tr/WS/services/EFatura';

    public const DEFAULT_WSDL_URL = 'https://nfhostservis.isnet.net.tr/ws/services/efatura?singleWsdl';

    public const DEFAULT_MOBILE_API_URL = 'https://einvoiceapi.isnet.net.tr/api';

    public function __construct(
        private readonly FirmaAyarDeposu $depo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ayarlariGetir(int $firmaId): array
    {
        $firma = Firma::query()->withoutGlobalScopes()->find($firmaId);

        return [
            'nette_fatura_aktif_mi' => (bool) $this->depo->oku($firmaId, 'nette_fatura_aktif_mi', false),
            'nette_fatura_test_modu' => (bool) $this->depo->oku($firmaId, 'nette_fatura_test_modu', true),
            'nette_fatura_service_url' => $this->urlTemizle($this->depo->oku($firmaId, 'nette_fatura_service_url', self::DEFAULT_SERVICE_URL), self::DEFAULT_SERVICE_URL),
            'nette_fatura_wsdl_url' => $this->urlTemizle($this->depo->oku($firmaId, 'nette_fatura_wsdl_url', self::DEFAULT_WSDL_URL), self::DEFAULT_WSDL_URL),
            'nette_fatura_mobile_api_url' => $this->urlTemizle($this->depo->oku($firmaId, 'nette_fatura_mobile_api_url', self::DEFAULT_MOBILE_API_URL), self::DEFAULT_MOBILE_API_URL),
            'nette_fatura_company_id' => $this->araliktaInt($this->depo->oku($firmaId, 'nette_fatura_company_id', 0), 0, 999999999, 0),
            'nette_fatura_kullanici_adi' => (string) $this->depo->oku($firmaId, 'nette_fatura_kullanici_adi', ''),
            'nette_fatura_sifre' => (string) $this->depo->oku($firmaId, 'nette_fatura_sifre', ''),
            'nette_fatura_zaman_asimi_saniye' => $this->araliktaInt($this->depo->oku($firmaId, 'nette_fatura_zaman_asimi_saniye', 20), 5, 120, 20),
            'nette_fatura_gonderici_unvan' => $this->metin($this->depo->oku($firmaId, 'nette_fatura_gonderici_unvan', $firma?->ad ?? '')),
            'nette_fatura_gonderici_vergi_no' => $this->metin($this->depo->oku($firmaId, 'nette_fatura_gonderici_vergi_no', $firma?->vergi_no ?? '')),
            'nette_fatura_gonderici_vergi_dairesi' => $this->metin($this->depo->oku($firmaId, 'nette_fatura_gonderici_vergi_dairesi', '')),
            'nette_fatura_gonderici_adres' => $this->metin($this->depo->oku($firmaId, 'nette_fatura_gonderici_adres', $firma?->adres ?? '')),
            'nette_fatura_gonderici_il' => $this->metin($this->depo->oku($firmaId, 'nette_fatura_gonderici_il', '')),
            'nette_fatura_gonderici_ilce' => $this->metin($this->depo->oku($firmaId, 'nette_fatura_gonderici_ilce', '')),
            'nette_fatura_gonderici_ulke' => $this->metin($this->depo->oku($firmaId, 'nette_fatura_gonderici_ulke', 'Türkiye')),
            'nette_fatura_gonderici_eposta' => $this->metin($this->depo->oku($firmaId, 'nette_fatura_gonderici_eposta', $firma?->eposta ?? '')),
            'nette_fatura_gonderici_telefon' => $this->metin($this->depo->oku($firmaId, 'nette_fatura_gonderici_telefon', $firma?->telefon ?? '')),
            'nette_fatura_gonderici_etiket' => $this->metin($this->depo->oku($firmaId, 'nette_fatura_gonderici_etiket', '')),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function kaydetAyarlar(int $firmaId, array $data): void
    {
        $this->depo->yaz($firmaId, 'nette_fatura_aktif_mi', (bool) ($data['nette_fatura_aktif_mi'] ?? false));
        $this->depo->yaz($firmaId, 'nette_fatura_test_modu', (bool) ($data['nette_fatura_test_modu'] ?? true));
        $this->depo->yaz($firmaId, 'nette_fatura_service_url', $this->urlTemizle($data['nette_fatura_service_url'] ?? null, self::DEFAULT_SERVICE_URL));
        $this->depo->yaz($firmaId, 'nette_fatura_wsdl_url', $this->urlTemizle($data['nette_fatura_wsdl_url'] ?? null, self::DEFAULT_WSDL_URL));
        $this->depo->yaz($firmaId, 'nette_fatura_mobile_api_url', $this->urlTemizle($data['nette_fatura_mobile_api_url'] ?? null, self::DEFAULT_MOBILE_API_URL));
        $this->depo->yaz($firmaId, 'nette_fatura_company_id', $this->araliktaInt($data['nette_fatura_company_id'] ?? 0, 0, 999999999, 0));
        $this->depo->yaz($firmaId, 'nette_fatura_kullanici_adi', trim((string) ($data['nette_fatura_kullanici_adi'] ?? '')));
        $this->depo->yaz($firmaId, 'nette_fatura_sifre', (string) ($data['nette_fatura_sifre'] ?? ''));
        $this->depo->yaz($firmaId, 'nette_fatura_zaman_asimi_saniye', $this->araliktaInt($data['nette_fatura_zaman_asimi_saniye'] ?? 20, 5, 120, 20));

        foreach ([
            'nette_fatura_gonderici_unvan',
            'nette_fatura_gonderici_vergi_no',
            'nette_fatura_gonderici_vergi_dairesi',
            'nette_fatura_gonderici_adres',
            'nette_fatura_gonderici_il',
            'nette_fatura_gonderici_ilce',
            'nette_fatura_gonderici_ulke',
            'nette_fatura_gonderici_eposta',
            'nette_fatura_gonderici_telefon',
            'nette_fatura_gonderici_etiket',
        ] as $anahtar) {
            $this->depo->yaz($firmaId, $anahtar, $this->metin($data[$anahtar] ?? ''));
        }
    }

    private function urlTemizle(mixed $deger, string $varsayilan): string
    {
        $url = trim((string) $deger);
        if ($url === '' || ! preg_match('~^https?://~i', $url)) {
            return $varsayilan;
        }

        return mb_substr($url, 0, 512);
    }

    private function araliktaInt(mixed $deger, int $min, int $max, int $varsayilan): int
    {
        $int = (int) $deger;

        return $int >= $min && $int <= $max ? $int : $varsayilan;
    }

    private function metin(mixed $deger): string
    {
        return mb_substr(trim((string) $deger), 0, 512);
    }
}
