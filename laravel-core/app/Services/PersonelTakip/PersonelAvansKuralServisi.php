<?php

namespace App\Services\PersonelTakip;

use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class PersonelAvansKuralServisi
{
    public function hazirlaVeDogrula(PersonelAvansi $avans): void
    {
        if (! $avans->firma_id || ! $avans->personel_id) {
            return;
        }

        $hatalar = [];

        $personelVar = Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $avans->firma_id)
            ->whereKey($avans->personel_id)
            ->exists();

        if (! $personelVar) {
            $hatalar['personel_id'][] = 'Seçilen personel bu firmaya ait değil.';
        }

        if ((float) $avans->tutar <= 0) {
            $hatalar['tutar'][] = 'Avans tutarı sıfırdan büyük olmalıdır.';
        }

        $this->hesapUyumlulugunuDogrula($avans, $hatalar);

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }

        $this->varsayilanlariDoldur($avans);
    }

    /** @param array<string, array<int, string>> $hatalar */
    private function hesapUyumlulugunuDogrula(PersonelAvansi $avans, array &$hatalar): void
    {
        $kanal = (string) $avans->odeme_kanali;

        if ($kanal === 'kasa') {
            $this->hesapZorunluMu($avans, 'kasa_hesap_id', $hatalar);
        }

        if ($kanal === 'banka') {
            $this->hesapZorunluMu($avans, 'banka_hesap_id', $hatalar);
        }

        if ($kanal === 'pos') {
            $this->hesapZorunluMu($avans, 'pos_hesap_id', $hatalar);
        }

        $this->firmaHesabiMi($avans, KasaHesabi::class, 'kasa_hesap_id', $hatalar);
        $this->firmaHesabiMi($avans, BankaHesabi::class, 'banka_hesap_id', $hatalar);
        $this->firmaHesabiMi($avans, PosHesabi::class, 'pos_hesap_id', $hatalar);
    }

    /** @param array<string, array<int, string>> $hatalar */
    private function hesapZorunluMu(PersonelAvansi $avans, string $alan, array &$hatalar): void
    {
        if ($avans->durum === 'onaylandi' && ! $avans->{$alan}) {
            $hatalar[$alan][] = 'Onaylı avans için ödeme hesabı seçilmelidir.';
        }
    }

    /**
     * @param class-string<Model> $model
     * @param array<string, array<int, string>> $hatalar
     */
    private function firmaHesabiMi(PersonelAvansi $avans, string $model, string $alan, array &$hatalar): void
    {
        $id = $avans->{$alan};
        if (! $id) {
            return;
        }

        $hesap = $model::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $avans->firma_id)
            ->whereKey($id)
            ->first();

        if (! $hesap) {
            $hatalar[$alan][] = 'Seçilen ödeme hesabı bu firmaya ait değil.';

            return;
        }

        $hesapParaBirimi = strtoupper((string) $hesap->getAttribute('para_birimi'));
        $islemParaBirimi = strtoupper((string) ($avans->para_birimi ?: 'TRY'));
        if ($hesapParaBirimi !== '' && $hesapParaBirimi !== $islemParaBirimi) {
            $hatalar[$alan][] = 'Ödeme hesabının para birimi avans para birimiyle uyumlu değil.';
        }
    }

    private function varsayilanlariDoldur(PersonelAvansi $avans): void
    {
        $avans->para_birimi = $avans->para_birimi ?: 'TRY';
        $avans->onay_durumu = $avans->onay_durumu ?: ($avans->durum === 'onaylandi' ? 'onaylandi' : 'bekliyor');
        $avans->mahsup_durumu = $avans->mahsup_durumu ?: 'bekliyor';
        $avans->odeme_kaynagi = $avans->odeme_kaynagi ?: $avans->odeme_kanali;

        if (! $avans->kalan_tutar && ! $avans->maastan_dusuldu_mu) {
            $avans->kalan_tutar = $avans->tutar;
        }

        if ($avans->maastan_dusuldu_mu) {
            $avans->kalan_tutar = 0;
            $avans->mahsup_durumu = 'mahsup_edildi';
        }

        $avans->kasa_hesabi_id = $avans->kasa_hesabi_id ?: $avans->kasa_hesap_id;
        $avans->banka_hesabi_id = $avans->banka_hesabi_id ?: $avans->banka_hesap_id;
    }
}
