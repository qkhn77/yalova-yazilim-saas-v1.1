<?php

namespace App\Services\PersonelTakip;

use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Personel\PersonelMaasOdemeKaydi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class PersonelMaasOdemeKuralServisi
{
    public function hazirlaVeDogrula(PersonelMaasOdemeKaydi $odeme): void
    {
        if (! $odeme->firma_id || ! $odeme->maas_hareketi_id) {
            return;
        }

        $hatalar = [];
        $hareket = $this->hareket($odeme);

        if (! $hareket) {
            $hatalar['maas_hareketi_id'][] = 'Seçilen maaş hareketi bu firmaya ait değil.';
        }

        if ((float) $odeme->tutar <= 0) {
            $hatalar['tutar'][] = 'Ödeme tutarı sıfırdan büyük olmalıdır.';
        }

        if ($hareket && $this->toplamOdemeTutari($odeme) > (float) $hareket->net_tutar) {
            $hatalar['tutar'][] = 'Toplam ödeme net maaş tutarını aşamaz.';
        }

        $this->hesapUyumlulugunuDogrula($odeme, $hatalar);

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }

        $odeme->para_birimi = $odeme->para_birimi ?: 'TRY';
    }

    public function hareketOdemesiniGuncelle(PersonelMaasOdemeKaydi $odeme): void
    {
        $hareket = $this->hareket($odeme);
        if (! $hareket) {
            return;
        }

        $toplam = PersonelMaasOdemeKaydi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $odeme->firma_id)
            ->where('maas_hareketi_id', $odeme->maas_hareketi_id)
            ->sum('tutar');

        $durum = $hareket->durum;
        if ((float) $toplam >= (float) $hareket->net_tutar) {
            $durum = 'odendi';
        } elseif ($durum === 'odendi') {
            $durum = 'onaylandi';
        }

        $hareket->forceFill([
            'odenen_tutar' => round((float) $toplam, 2),
            'durum' => $durum,
        ])->save();

        if ($durum === 'odendi') {
            app(PersonelAvansMahsupServisi::class)->maasHareketiTamOdendigindeMahsupEt($hareket->refresh());
        }

        app(PersonelMaasDonemiOnayServisi::class)->odemeDurumunuGuncelle(
            (int) $hareket->firma_id,
            (int) $hareket->maas_donemi_id
        );
    }

    /** @param array<string, array<int, string>> $hatalar */
    private function hesapUyumlulugunuDogrula(PersonelMaasOdemeKaydi $odeme, array &$hatalar): void
    {
        if ($odeme->odeme_kanali === 'kasa') {
            $this->hesapZorunluMu($odeme, 'kasa_hesap_id', $hatalar);
        }

        if ($odeme->odeme_kanali === 'banka') {
            $this->hesapZorunluMu($odeme, 'banka_hesap_id', $hatalar);
        }

        $this->firmaHesabiMi($odeme, KasaHesabi::class, 'kasa_hesap_id', $hatalar);
        $this->firmaHesabiMi($odeme, BankaHesabi::class, 'banka_hesap_id', $hatalar);
    }

    /** @param array<string, array<int, string>> $hatalar */
    private function hesapZorunluMu(PersonelMaasOdemeKaydi $odeme, string $alan, array &$hatalar): void
    {
        if (! $odeme->{$alan}) {
            $hatalar[$alan][] = 'Maaş ödemesi için ödeme hesabı seçilmelidir.';
        }
    }

    /**
     * @param class-string<Model> $model
     * @param array<string, array<int, string>> $hatalar
     */
    private function firmaHesabiMi(PersonelMaasOdemeKaydi $odeme, string $model, string $alan, array &$hatalar): void
    {
        $id = $odeme->{$alan};
        if (! $id) {
            return;
        }

        $hesap = $model::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $odeme->firma_id)
            ->whereKey($id)
            ->first();

        if (! $hesap) {
            $hatalar[$alan][] = 'Seçilen ödeme hesabı bu firmaya ait değil.';

            return;
        }

        $hesapParaBirimi = strtoupper((string) $hesap->getAttribute('para_birimi'));
        $islemParaBirimi = strtoupper((string) ($odeme->para_birimi ?: 'TRY'));
        if ($hesapParaBirimi !== '' && $hesapParaBirimi !== $islemParaBirimi) {
            $hatalar[$alan][] = 'Ödeme hesabının para birimi maaş ödemesiyle uyumlu değil.';
        }
    }

    private function hareket(PersonelMaasOdemeKaydi $odeme): ?PersonelMaasHareketi
    {
        return PersonelMaasHareketi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $odeme->firma_id)
            ->whereKey($odeme->maas_hareketi_id)
            ->first();
    }

    private function toplamOdemeTutari(PersonelMaasOdemeKaydi $odeme): float
    {
        $mevcut = (float) PersonelMaasOdemeKaydi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $odeme->firma_id)
            ->where('maas_hareketi_id', $odeme->maas_hareketi_id)
            ->when($odeme->exists, fn ($query) => $query->whereKeyNot($odeme->getKey()))
            ->sum('tutar');

        return round($mevcut + (float) $odeme->tutar, 2);
    }
}
