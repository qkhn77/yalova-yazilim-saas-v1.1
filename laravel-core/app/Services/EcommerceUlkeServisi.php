<?php

namespace App\Services;

use App\Models\Ecommerce\EcommerceKargoYontemi;

class EcommerceUlkeServisi
{
    /**
     * @return array<string, string>
     */
    public function tumUlkeSecenekleri(): array
    {
        return [
            'TR' => 'Türkiye',
            'DE' => 'Almanya',
            'AT' => 'Avusturya',
            'BE' => 'Belçika',
            'AE' => 'Birleşik Arap Emirlikleri',
            'GB' => 'Birleşik Krallık',
            'US' => 'Amerika Birleşik Devletleri',
            'FR' => 'Fransa',
            'NL' => 'Hollanda',
            'ES' => 'İspanya',
            'IT' => 'İtalya',
            'CH' => 'İsviçre',
            'SE' => 'İsveç',
            'QA' => 'Katar',
            'KW' => 'Kuveyt',
            'SA' => 'Suudi Arabistan',
            'AZ' => 'Azerbaycan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function checkoutUlkeSecenekleri(int $firmaId): array
    {
        $firmaAyarKodlari = $this->listeyiNormalizeEt(
            app(FirmaAyarDeposu::class)->oku($firmaId, 'ecommerce_checkout_ulke_kodlari', '')
        );

        $kaynakKodlar = $firmaAyarKodlari !== []
            ? $firmaAyarKodlari
            : $this->kargoYontemlerindenUlkeKodlari($firmaId);

        if ($kaynakKodlar === []) {
            $kaynakKodlar = ['TR', 'DE', 'GB', 'US'];
        }

        $secenekler = $this->tumUlkeSecenekleri();
        $varsayilan = $this->varsayilanUlkeKodu($firmaId);
        $sonuc = [];

        foreach ($kaynakKodlar as $kod) {
            $sonuc[$kod] = $secenekler[$kod] ?? $kod;
        }

        if (! array_key_exists($varsayilan, $sonuc)) {
            $sonuc = [$varsayilan => $secenekler[$varsayilan] ?? $varsayilan] + $sonuc;
        }

        return $sonuc;
    }

    public function varsayilanUlkeKodu(int $firmaId, string $fallback = 'TR'): string
    {
        $kod = strtoupper(trim((string) app(FirmaAyarDeposu::class)->oku($firmaId, 'ecommerce_varsayilan_checkout_ulke', $fallback)));

        return array_key_exists($kod, $this->tumUlkeSecenekleri()) ? $kod : $fallback;
    }

    /**
     * @return array{regex:?string, example:string, required:bool}
     */
    public function postaKoduKurali(string $ulkeKodu): array
    {
        return match (strtoupper(trim($ulkeKodu))) {
            'TR' => ['regex' => '/^\d{5}$/', 'example' => '34000', 'required' => true],
            'DE' => ['regex' => '/^\d{5}$/', 'example' => '10115', 'required' => true],
            'AT' => ['regex' => '/^\d{4}$/', 'example' => '1010', 'required' => true],
            'BE' => ['regex' => '/^\d{4}$/', 'example' => '1000', 'required' => true],
            'FR' => ['regex' => '/^\d{5}$/', 'example' => '75001', 'required' => true],
            'NL' => ['regex' => '/^\d{4}\s?[A-Z]{2}$/i', 'example' => '1012 AB', 'required' => true],
            'GB' => ['regex' => '/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i', 'example' => 'SW1A 1AA', 'required' => true],
            'US' => ['regex' => '/^\d{5}(-\d{4})?$/', 'example' => '10001', 'required' => true],
            'CH' => ['regex' => '/^\d{4}$/', 'example' => '8001', 'required' => true],
            'SE' => ['regex' => '/^\d{3}\s?\d{2}$/', 'example' => '111 22', 'required' => true],
            default => ['regex' => null, 'example' => 'Posta kodu', 'required' => false],
        };
    }

    public function postaKoduGecerliMi(string $ulkeKodu, ?string $postaKodu): bool
    {
        $kural = $this->postaKoduKurali($ulkeKodu);
        $deger = trim((string) $postaKodu);

        if ($deger === '') {
            return ! $kural['required'];
        }

        if (! is_string($kural['regex']) || $kural['regex'] === '') {
            return true;
        }

        return preg_match($kural['regex'], $deger) === 1;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function bolgeSecenekleri(): array
    {
        return [
            'TR' => ['Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Aksaray', 'Amasya', 'Ankara', 'Antalya', 'Ardahan', 'Artvin', 'Aydın', 'Balıkesir', 'Bartın', 'Batman', 'Bayburt', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Düzce', 'Edirne', 'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Iğdır', 'Isparta', 'İstanbul', 'İzmir', 'Kahramanmaraş', 'Karabük', 'Karaman', 'Kars', 'Kastamonu', 'Kayseri', 'Kırıkkale', 'Kırklareli', 'Kırşehir', 'Kilis', 'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Mardin', 'Mersin', 'Muğla', 'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Osmaniye', 'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop', 'Sivas', 'Şanlıurfa', 'Şırnak', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Uşak', 'Van', 'Yalova', 'Yozgat', 'Zonguldak'],
            'DE' => ['Baden-Württemberg', 'Bayern', 'Berlin', 'Brandenburg', 'Bremen', 'Hamburg', 'Hessen', 'Mecklenburg-Vorpommern', 'Niedersachsen', 'Nordrhein-Westfalen', 'Rheinland-Pfalz', 'Saarland', 'Sachsen', 'Sachsen-Anhalt', 'Schleswig-Holstein', 'Thüringen'],
            'GB' => ['England', 'Scotland', 'Wales', 'Northern Ireland'],
            'US' => ['California', 'Florida', 'Illinois', 'New Jersey', 'New York', 'Texas', 'Virginia', 'Washington'],
            'AE' => ['Abu Dhabi', 'Ajman', 'Dubai', 'Fujairah', 'Ras Al Khaimah', 'Sharjah'],
        ];
    }

    public function bolgeGecerliMi(string $ulkeKodu, ?string $bolge): bool
    {
        $ulkeKodu = strtoupper(trim($ulkeKodu));
        $bolge = trim((string) $bolge);
        $secenekler = $this->bolgeSecenekleri()[$ulkeKodu] ?? [];

        if ($secenekler === []) {
            return $bolge !== '';
        }

        return in_array($bolge, $secenekler, true);
    }

    public function telefonGecerliMi(?string $telefon, string $ulkeKodu = 'TR'): bool
    {
        $rakamlar = preg_replace('/\D+/', '', (string) $telefon) ?? '';
        if ($rakamlar === '') {
            return false;
        }

        if (strtoupper(trim($ulkeKodu)) === 'TR') {
            if (str_starts_with($rakamlar, '90')) {
                $rakamlar = substr($rakamlar, 2);
            }
            if (str_starts_with($rakamlar, '0')) {
                $rakamlar = substr($rakamlar, 1);
            }

            return strlen($rakamlar) === 10 && str_starts_with($rakamlar, '5');
        }

        return strlen($rakamlar) >= 7 && strlen($rakamlar) <= 15;
    }

    /**
     * @return array<int, string>
     */
    private function kargoYontemlerindenUlkeKodlari(int $firmaId): array
    {
        $tumUlkeler = array_keys($this->tumUlkeSecenekleri());
        $kodlar = [];

        $yontemler = EcommerceKargoYontemi::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->get(['yurt_ici_aktif', 'yurt_disi_aktif', 'bolge_kurali']);

        foreach ($yontemler as $yontem) {
            if ((bool) $yontem->yurt_ici_aktif) {
                $kodlar[] = 'TR';
            }

            if (! (bool) $yontem->yurt_disi_aktif) {
                continue;
            }

            $kapsam = (string) data_get($yontem->bolge_kurali, 'ulke_kapsami', '');
            $ulkeler = $this->listeyiNormalizeEt(data_get($yontem->bolge_kurali, 'ulkeler', ''));
            $haric = $this->listeyiNormalizeEt(data_get($yontem->bolge_kurali, 'haric_ulkeler', ''));

            if ($kapsam === 'selected_countries' && $ulkeler !== []) {
                $kodlar = [...$kodlar, ...$ulkeler];

                continue;
            }

            if ($kapsam === 'all_countries_except') {
                $kodlar = [...$kodlar, ...array_values(array_diff($tumUlkeler, $haric))];

                continue;
            }

            if ($kapsam === 'international_only' || $kapsam === '' || $kapsam === 'domestic_only') {
                $kodlar = [...$kodlar, ...array_values(array_diff($tumUlkeler, ['TR']))];
            }
        }

        $kodlar = array_values(array_unique(array_filter($kodlar)));
        sort($kodlar);

        return $kodlar;
    }

    /**
     * @return array<int, string>
     */
    private function listeyiNormalizeEt(mixed $veri): array
    {
        $liste = is_array($veri) ? $veri : preg_split('/[,;\r\n]+/', (string) $veri);

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $kod): string => strtoupper(trim((string) $kod)),
            is_array($liste) ? $liste : []
        ))));
    }
}
