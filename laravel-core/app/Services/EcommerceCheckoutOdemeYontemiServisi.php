<?php

namespace App\Services;

use App\Models\Ecommerce\EcommerceOdemeYontemi;
use App\Models\Firma;
use App\Models\Muhasebe\BankaHesabi;
use App\Support\EcommerceOdemeTanimlari;

class EcommerceCheckoutOdemeYontemiServisi
{
    public function __construct(
        private readonly EcommerceOdemeFirmaAyarServisi $ecommerceOdemeFirmaAyarServisi,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function secenekler(int $firmaId, ?float $siparisTutari = null, ?string $paraBirimi = null): array
    {
        if ($firmaId <= 0) {
            return [];
        }

        $secenekler = [];

        foreach ($this->onlineOdemeKayitlari($firmaId) as $kayit) {
            $secenekler[] = $this->onlineOdemeSecenegi($kayit);
        }

        if ($secenekler === []) {
            $onlineKart = $this->onlineKartSecenegi($firmaId);
            if ($onlineKart !== null) {
                $secenekler[] = $onlineKart;
            }
        }

        $havaleKayitlari = EcommerceOdemeYontemi::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->where('saglayici', EcommerceOdemeTanimlari::SAGLAYICI_HAVALE_EFT)
            ->orderByDesc('varsayilan_mi')
            ->orderBy('ad')
            ->get();

        foreach ($havaleKayitlari as $kayit) {
            $secenek = $this->havaleEftSecenegi($kayit);
            if ($secenek !== null) {
                $secenekler[] = $secenek;
            }
        }

        if ($siparisTutari === null) {
            return $secenekler;
        }

        return array_values(array_filter(
            $secenekler,
            fn (array $secenek): bool => $this->odemeSecenegiTutarIcinUygunMu($secenek, $siparisTutari, $paraBirimi)
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function secimBul(int $firmaId, string $secim, ?float $siparisTutari = null, ?string $paraBirimi = null): ?array
    {
        $secim = trim($secim);
        if ($firmaId <= 0 || $secim === '') {
            return null;
        }

        foreach ($this->secenekler($firmaId, $siparisTutari, $paraBirimi) as $secenek) {
            if ((string) ($secenek['secim'] ?? '') === $secim) {
                return $secenek;
            }
        }

        return null;
    }

    public function odemeSecenegiTutarIcinUygunMu(array $secenek, float $siparisTutari, ?string $paraBirimi = null): bool
    {
        $pb = strtoupper((string) ($paraBirimi ?: 'TRY'));
        $izinliParaBirimleri = array_map(
            static fn ($kod): string => strtoupper((string) $kod),
            (array) ($secenek['para_birimleri'] ?? [])
        );

        if ($izinliParaBirimleri !== [] && ! in_array($pb, $izinliParaBirimleri, true)) {
            return false;
        }

        $minTutar = (float) ($secenek['min_tutar'] ?? 0);
        if ($minTutar > 0 && $siparisTutari < $minTutar) {
            return false;
        }

        $maxTutar = (float) ($secenek['max_tutar'] ?? 0);

        return ! ($maxTutar > 0 && $siparisTutari >= $maxTutar);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function onlineKartSecenegi(int $firmaId): ?array
    {
        $ayarlar = $this->ecommerceOdemeFirmaAyarServisi->odemeAyarlariniGetir($firmaId);

        if (! (bool) ($ayarlar['ecommerce_odeme_aktif_mi'] ?? false)) {
            return null;
        }

        $provider = (string) ($ayarlar['ecommerce_odeme_provider'] ?? '');
        if (! in_array($provider, ['paytr', 'iyzico'], true)) {
            return null;
        }

        $providerEtiketi = EcommerceOdemeTanimlari::saglayicilar()[$provider] ?? strtoupper($provider);
        $yontemKaydi = EcommerceOdemeYontemi::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->where('saglayici', $provider)
            ->orderByDesc('varsayilan_mi')
            ->orderBy('id')
            ->first();

        return [
            'secim' => 'online_kart',
            'tip' => 'online',
            'kod' => 'online_kart',
            'ad' => 'Kredi / Banka Kartı',
            'provider' => $provider,
            'aciklama' => $providerEtiketi.' ile güvenli online ödeme',
            'is_default' => true,
            'ecommerce_odeme_yontemi_id' => $yontemKaydi?->getKey(),
            'para_birimleri' => (array) ($yontemKaydi?->para_birimleri ?? ['TRY']),
            'min_tutar' => $this->minTutar($yontemKaydi?->saglayici_ayarlar ?? [], $provider),
            'max_tutar' => $this->maxTutar($yontemKaydi?->saglayici_ayarlar ?? [], $provider),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, EcommerceOdemeYontemi>
     */
    private function onlineOdemeKayitlari(int $firmaId): \Illuminate\Support\Collection
    {
        return EcommerceOdemeYontemi::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->whereIn('saglayici', ['iyzico', 'paytr', 'stripe', 'payoneer'])
            ->orderByDesc('varsayilan_mi')
            ->orderBy('ad')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function onlineOdemeSecenegi(EcommerceOdemeYontemi $kayit): array
    {
        $provider = (string) $kayit->saglayici;
        $providerEtiketi = EcommerceOdemeTanimlari::saglayicilar()[$provider] ?? strtoupper($provider);

        return [
            'secim' => 'online:'.$kayit->getKey(),
            'tip' => 'online',
            'kod' => (string) ($kayit->kod ?: 'online_kart'),
            'ad' => (string) ($kayit->ad ?: 'Kredi / Banka Kartı'),
            'provider' => $provider,
            'aciklama' => $providerEtiketi.' ile güvenli online ödeme',
            'is_default' => (bool) $kayit->varsayilan_mi,
            'ecommerce_odeme_yontemi_id' => (int) $kayit->getKey(),
            'para_birimleri' => (array) ($kayit->para_birimleri ?? []),
            'min_tutar' => $this->minTutar($kayit->saglayici_ayarlar ?? [], $provider),
            'max_tutar' => $this->maxTutar($kayit->saglayici_ayarlar ?? [], $provider),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function havaleEftSecenegi(EcommerceOdemeYontemi $kayit): ?array
    {
        $ayarlar = is_array($kayit->saglayici_ayarlar) ? $kayit->saglayici_ayarlar : [];
        $bankaHesapId = (int) ($ayarlar['banka_hesap_id'] ?? 0);
        $bankaHesabi = $bankaHesapId > 0
            ? BankaHesabi::query()
                ->where('firma_id', (int) $kayit->firma_id)
                ->whereKey($bankaHesapId)
                ->first()
            : null;

        $firma = Firma::query()->find((int) $kayit->firma_id);
        $hesapSahibi = trim((string) ($bankaHesabi?->hesap_sahibi_unvan ?? ''));
        if ($hesapSahibi === '') {
            $hesapSahibi = trim((string) ($firma?->ad ?? $bankaHesabi?->ad ?? ''));
        }
        $bankaAdi = trim(implode(' / ', array_filter([
            (string) ($bankaHesabi?->banka_adi ?? ''),
            (string) ($bankaHesabi?->sube ?? ''),
        ])));
        $odemeNotu = trim((string) ($ayarlar['odeme_notu'] ?? ''));

        if ($odemeNotu === '' && ! filled($bankaHesabi?->iban)) {
            $odemeNotu = 'Banka hesap bilgileri sipariş sonrası tarafınıza iletilecektir.';
        }

        return [
            'secim' => 'havale_eft:'.$kayit->getKey(),
            'tip' => 'havale_eft',
            'kod' => (string) ($kayit->kod ?: 'havale_eft'),
            'ad' => (string) ($kayit->ad ?: 'Havale/EFT'),
            'provider' => EcommerceOdemeTanimlari::SAGLAYICI_HAVALE_EFT,
            'aciklama' => 'Banka transferi ile sipariş talebi oluşturun',
            'is_default' => (bool) $kayit->varsayilan_mi,
            'ecommerce_odeme_yontemi_id' => (int) $kayit->getKey(),
            'banka_hesap_id' => $bankaHesabi ? (int) $bankaHesabi->getKey() : null,
            'banka_adi' => $bankaAdi !== '' ? $bankaAdi : (string) ($bankaHesabi?->ad ?? ''),
            'hesap_sahibi' => $hesapSahibi,
            'iban' => (string) ($bankaHesabi?->iban ?? ''),
            'odeme_notu' => $odemeNotu,
            'para_birimleri' => (array) ($kayit->para_birimleri ?? []),
            'min_tutar' => $this->minTutar($ayarlar, EcommerceOdemeTanimlari::SAGLAYICI_HAVALE_EFT),
            'max_tutar' => $this->maxTutar($ayarlar, EcommerceOdemeTanimlari::SAGLAYICI_HAVALE_EFT),
        ];
    }

    private function minTutar(mixed $ayarlar, string $provider): float
    {
        $ayarlar = is_array($ayarlar) ? $ayarlar : [];

        return max(0.0, (float) ($ayarlar['min_tutar'] ?? 0));
    }

    private function maxTutar(mixed $ayarlar, string $provider): float
    {
        $ayarlar = is_array($ayarlar) ? $ayarlar : [];
        $maxTutar = (float) ($ayarlar['max_tutar'] ?? 0);
        if ($maxTutar > 0) {
            return $maxTutar;
        }

        return $provider === 'iyzico' ? 100000.0 : 0.0;
    }
}
