<?php

namespace App\Services\PersonelTakip;

use App\Models\Muhasebe\BankaHareketi;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelMaasOdemeKaydi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Enumlar\HareketDurumu;
use App\Muhasebe\Yardimcilar\FinansAuditBaglami;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PersonelFinansHareketServisi
{
    public function avansOdemesiniIptalEt(PersonelAvansi $avans, ?string $neden = null): PersonelAvansi
    {
        return DB::transaction(function () use ($avans, $neden): PersonelAvansi {
            $kilitli = PersonelAvansi::query()->withoutGlobalScope(FirmaIdTenantScope::class)
                ->whereKey($avans->getKey())->lockForUpdate()->firstOrFail();
            if (! $kilitli->finans_hareketi_id) {
                throw ValidationException::withMessages(['avans' => 'Avansın aktif finans hareketi bulunamadı.']);
            }
            $finans = FinansHareketi::query()->withoutGlobalScope(FirmaIdTenantScope::class)
                ->lockForUpdate()->findOrFail($kilitli->finans_hareketi_id);
            $finansDurumu = $finans->durum instanceof FinansHareketDurumu ? $finans->durum->value : (string) $finans->durum;
            if ($finansDurumu !== FinansHareketDurumu::Aktif->value) {
                throw ValidationException::withMessages(['avans' => 'Avans finans hareketi zaten iptal edilmiş.']);
            }
            app(\App\Muhasebe\Servisler\FinansHareketServisi::class)->tersKayitOlustur($finans, $neden ?: 'Personel avansı iptali');
            $kilitli->forceFill([
                'durum' => 'iptal',
                'onay_durumu' => 'iptal',
                'aciklama' => $neden ?: $kilitli->aciklama,
            ])->save();
            return $kilitli->refresh();
        });
    }

    public function maasOdemesiniIptalEt(PersonelMaasOdemeKaydi $odeme, ?string $neden = null): PersonelMaasOdemeKaydi
    {
        return DB::transaction(function () use ($odeme, $neden): PersonelMaasOdemeKaydi {
            $kilitli = PersonelMaasOdemeKaydi::query()->withoutGlobalScope(FirmaIdTenantScope::class)
                ->whereKey($odeme->getKey())->lockForUpdate()->firstOrFail();
            if (! $kilitli->finans_hareketi_id) {
                throw ValidationException::withMessages(['odeme' => 'Maaş ödemesinin aktif finans hareketi bulunamadı.']);
            }
            $finans = FinansHareketi::query()->withoutGlobalScope(FirmaIdTenantScope::class)
                ->lockForUpdate()->findOrFail($kilitli->finans_hareketi_id);
            $finansDurumu = $finans->durum instanceof FinansHareketDurumu ? $finans->durum->value : (string) $finans->durum;
            if ($finansDurumu !== FinansHareketDurumu::Aktif->value) {
                throw ValidationException::withMessages(['odeme' => 'Maaş ödeme finans hareketi zaten iptal edilmiş.']);
            }
            app(\App\Muhasebe\Servisler\FinansHareketServisi::class)->tersKayitOlustur($finans, $neden ?: 'Personel maaş ödemesi iptali');
            $kilitli->forceFill(['aciklama' => $neden ?: $kilitli->aciklama])->save();
            return $kilitli->refresh();
        });
    }

    public function avansOdemesiniFinansaIsle(PersonelAvansi $avans): FinansHareketi
    {
        return DB::transaction(function () use ($avans): FinansHareketi {
            $kilitli = PersonelAvansi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->lockForUpdate()
                ->findOrFail($avans->getKey());

            if ($kilitli->finans_hareketi_id) {
                return FinansHareketi::query()
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->findOrFail($kilitli->finans_hareketi_id);
            }

            $finans = $this->odemeFinansiOlustur(
                firmaId: (int) $kilitli->firma_id,
                tutar: (string) $kilitli->tutar,
                paraBirimi: (string) $kilitli->para_birimi,
                tarih: $kilitli->tarih?->toDateString() ?: now()->toDateString(),
                aciklama: $kilitli->aciklama ?: 'Personel avans ödemesi',
                referansTuru: 'personel_avans',
                referansId: (int) $kilitli->id,
            );

            $this->hesapHareketiOlustur($finans, (string) $kilitli->odeme_kanali, $kilitli->kasa_hesap_id, $kilitli->banka_hesap_id);

            $kilitli->forceFill([
                'finans_hareketi_id' => $finans->id,
                'durum' => 'onaylandi',
                'onay_durumu' => 'onaylandi',
            ])->save();

            return $finans;
        });
    }

    public function maasOdemesiniFinansaIsle(PersonelMaasOdemeKaydi $odeme): FinansHareketi
    {
        return DB::transaction(function () use ($odeme): FinansHareketi {
            $kilitli = PersonelMaasOdemeKaydi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->lockForUpdate()
                ->findOrFail($odeme->getKey());

            if ($kilitli->finans_hareketi_id) {
                return FinansHareketi::query()
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->findOrFail($kilitli->finans_hareketi_id);
            }

            $finans = $this->odemeFinansiOlustur(
                firmaId: (int) $kilitli->firma_id,
                tutar: (string) $kilitli->tutar,
                paraBirimi: (string) $kilitli->para_birimi,
                tarih: $kilitli->tarih?->toDateString() ?: now()->toDateString(),
                aciklama: $kilitli->aciklama ?: 'Personel maaş ödemesi',
                referansTuru: 'personel_maas_odeme',
                referansId: (int) $kilitli->id,
            );

            $this->hesapHareketiOlustur($finans, (string) $kilitli->odeme_kanali, $kilitli->kasa_hesap_id, $kilitli->banka_hesap_id);

            $kilitli->forceFill(['finans_hareketi_id' => $finans->id])->save();

            return $finans;
        });
    }

    private function odemeFinansiOlustur(
        int $firmaId,
        string $tutar,
        string $paraBirimi,
        string $tarih,
        string $aciklama,
        string $referansTuru,
        int $referansId,
    ): FinansHareketi {
        if ((float) $tutar <= 0) {
            throw ValidationException::withMessages(['tutar' => 'Finans hareketi tutarı sıfırdan büyük olmalıdır.']);
        }

        return FinansHareketi::query()->create(array_merge(
            FinansAuditBaglami::otomatikFinansAlanlari(),
            [
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Odeme,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'baz_tutar' => $tutar,
                'para_birimi' => strtoupper($paraBirimi ?: 'TRY'),
                'baz_para_birimi' => strtoupper($paraBirimi ?: 'TRY'),
                'kur' => 1,
                'cari_id' => null,
                'aciklama' => $aciklama,
                'referans_turu' => $referansTuru,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]
        ));
    }

    private function hesapHareketiOlustur(FinansHareketi $finans, string $kanal, mixed $kasaHesapId, mixed $bankaHesapId): Model
    {
        $negatifTutar = -1 * (float) $finans->tutar;

        if ($kanal === 'kasa') {
            $kasa = $this->hesapDogrula(KasaHesabi::class, (int) $finans->firma_id, $kasaHesapId, (string) $finans->para_birimi, 'kasa_hesap_id');

            return KasaHareketi::query()->create([
                'firma_id' => $finans->firma_id,
                'finans_hareket_id' => $finans->id,
                'kasa_hesap_id' => $kasa->id,
                'tutar' => $negatifTutar,
                'para_birimi' => $finans->para_birimi,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);
        }

        if ($kanal === 'banka') {
            $banka = $this->hesapDogrula(BankaHesabi::class, (int) $finans->firma_id, $bankaHesapId, (string) $finans->para_birimi, 'banka_hesap_id');

            return BankaHareketi::query()->create([
                'firma_id' => $finans->firma_id,
                'finans_hareket_id' => $finans->id,
                'banka_hesap_id' => $banka->id,
                'tutar' => $negatifTutar,
                'para_birimi' => $finans->para_birimi,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);
        }

        throw ValidationException::withMessages(['odeme_kanali' => 'Personel finans işlemi için kasa veya banka kanalı seçilmelidir.']);
    }

    /**
     * @param class-string<Model> $model
     */
    private function hesapDogrula(string $model, int $firmaId, mixed $id, string $paraBirimi, string $alan): Model
    {
        if (! $id) {
            throw ValidationException::withMessages([$alan => 'Ödeme hesabı seçilmelidir.']);
        }

        $hesap = $model::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->whereKey($id)
            ->first();

        if (! $hesap) {
            throw ValidationException::withMessages([$alan => 'Seçilen ödeme hesabı bu firmaya ait değil.']);
        }

        if (strtoupper((string) $hesap->getAttribute('para_birimi')) !== strtoupper($paraBirimi)) {
            throw ValidationException::withMessages([$alan => 'Ödeme hesabının para birimi işlemle uyumlu değil.']);
        }

        return $hesap;
    }
}
