<?php

namespace App\Modules\Urun\Servisler;

use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisGecmisi;
use App\Services\EcommerceBildirimServisi;
use App\Services\EcommerceMuhasebeEntegrasyonServisi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Panelden siparis durum gecis kurallari (odeme cekirdegine dokunmaz).
 */
class SiparisDurumGecisServisi
{
    public function __construct(
        private readonly SiparisGecmisServisi $gecmisServisi,
        private readonly EcommerceBildirimServisi $bildirimServisi,
        private readonly EcommerceMuhasebeEntegrasyonServisi $ecommerceMuhasebeEntegrasyonServisi,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public function gecisMatrisi(): array
    {
        return [
            Siparis::DURUM_DETAY_BEKLEYEN => [
                Siparis::DURUM_ONAY_BEKLIYOR,
                Siparis::DURUM_IPTAL_TALEBI,
                Siparis::DURUM_IPTAL_EDILDI,
            ],
            Siparis::DURUM_ONAY_BEKLIYOR => [
                Siparis::DURUM_DETAY_BEKLEYEN,
                Siparis::DURUM_ONAYLANDI_YENI,
                Siparis::DURUM_BASARISIZ_ODEME,
                Siparis::DURUM_IPTAL_TALEBI,
                Siparis::DURUM_IPTAL_EDILDI,
            ],
            Siparis::DURUM_BASARISIZ_ODEME => [
                Siparis::DURUM_ONAY_BEKLIYOR,
                Siparis::DURUM_ONAYLANDI_YENI,
                Siparis::DURUM_IPTAL_TALEBI,
                Siparis::DURUM_IPTAL_EDILDI,
            ],
            Siparis::DURUM_EFT_ONAYI_BEKLIYOR => [
                Siparis::DURUM_ONAYLANDI_YENI,
                Siparis::DURUM_IPTAL_TALEBI,
                Siparis::DURUM_IPTAL_EDILDI,
            ],
            Siparis::DURUM_ONAYLANDI_YENI => [
                Siparis::DURUM_GONDERILDI,
                Siparis::DURUM_IPTAL_TALEBI,
                Siparis::DURUM_IPTAL_EDILDI,
            ],
            Siparis::DURUM_GONDERILDI => [
                Siparis::DURUM_TESLIM_EDILDI,
                Siparis::DURUM_IADE_TALEBI,
            ],
            Siparis::DURUM_TESLIM_EDILDI => [
                Siparis::DURUM_IADE_TALEBI,
            ],
            Siparis::DURUM_IPTAL_TALEBI => [
                Siparis::DURUM_IPTAL_EDILDI,
                Siparis::DURUM_ONAYLANDI_YENI,
            ],
            Siparis::DURUM_IADE_TALEBI => [
                Siparis::DURUM_IADE_EDILDI,
                Siparis::DURUM_TESLIM_EDILDI,
            ],
            Siparis::DURUM_IPTAL_EDILDI => [],
            Siparis::DURUM_IADE_EDILDI => [],
        ];
    }

    /**
     * @return list<string>
     */
    public function izinliHedefDurumlar(string $mevcutDurum): array
    {
        $matris = $this->gecisMatrisi();

        return $matris[$this->normalizeDurum($mevcutDurum)] ?? [];
    }

    public function gecisIzinli(string $eskiDurum, string $yeniDurum): bool
    {
        $normalizeEski = $this->normalizeDurum($eskiDurum);
        $normalizeYeni = $this->normalizeDurum($yeniDurum);

        if ($normalizeEski === $normalizeYeni) {
            return true;
        }

        if (in_array($normalizeEski, [Siparis::DURUM_IPTAL_EDILDI, Siparis::DURUM_IADE_EDILDI], true)) {
            return false;
        }

        return in_array($normalizeYeni, $this->izinliHedefDurumlar($normalizeEski), true);
    }

    /**
     * @return array<string, string>
     */
    public function durumSecimOpsiyonlari(string $mevcutDurum): array
    {
        $hamMevcut = trim($mevcutDurum);
        $normalizeMevcut = $this->normalizeDurum($mevcutDurum);
        $izinli = $this->izinliHedefDurumlar($normalizeMevcut);
        $durumlar = array_values(array_unique(array_merge([$hamMevcut, $normalizeMevcut], $izinli)));
        $etiketler = Siparis::durumEtiketleri();
        $opsiyonlar = [];

        foreach ($durumlar as $durum) {
            if ($durum === '') {
                continue;
            }
            $opsiyonlar[$durum] = $etiketler[$durum] ?? $durum;
        }

        return $opsiyonlar;
    }

    /**
     * Iptal haric durum guncellemesi (stok/finans tetiklemez).
     */
    public function durumuGuncelle(
        Siparis $siparis,
        string $yeniDurum,
        ?int $kullaniciId = null,
    ): void {
        $eski = (string) $siparis->durum;
        $normalizeYeni = $this->normalizeDurum($yeniDurum);
        $normalizeEski = $this->normalizeDurum($eski);

        if ($normalizeYeni === Siparis::DURUM_IPTAL) {
            $normalizeYeni = Siparis::DURUM_IPTAL_EDILDI;
        }

        if ($normalizeYeni === Siparis::DURUM_IPTAL_EDILDI) {
            throw ValidationException::withMessages([
                'durum' => 'Iptal bu servis uzerinden yapilmaz; Iptal aksiyonunu kullanin.',
            ]);
        }

        if (! $this->gecisIzinli($normalizeEski, $normalizeYeni)) {
            $eskiEtiket = Siparis::durumEtiketi($eski);
            $yeniEtiket = Siparis::durumEtiketi($normalizeYeni);

            throw ValidationException::withMessages([
                'durum' => 'Bu durum gecisine izin verilmiyor: '.$eskiEtiket.' -> '.$yeniEtiket,
            ]);
        }

        DB::transaction(function () use ($siparis, $yeniDurum, $normalizeYeni, $eski, $normalizeEski, $kullaniciId): void {
            $yazilacakDurum = $this->yazilacakDurumuBelirle($yeniDurum, $normalizeYeni);
            $siparis->update(['durum' => $yazilacakDurum]);

            $this->gecmisServisi->kaydet(
                $siparis->fresh(),
                SiparisGecmisi::OLAY_DURUM_DEGISTI,
                $eski.' -> '.$yazilacakDurum,
                ['eski' => $eski, 'eski_normalize' => $normalizeEski, 'yeni' => $yazilacakDurum, 'yeni_normalize' => $normalizeYeni],
                $kullaniciId
            );

            if ($normalizeYeni === Siparis::DURUM_ONAYLANDI_YENI) {
                $this->ecommerceMuhasebeEntegrasyonServisi->siparisiMuhasebeyeEntegreEt($siparis->fresh());
            }

            $this->bildirimServisi->siparisDurumDegisti(
                $siparis->fresh(),
                $eski,
                $yazilacakDurum
            );
        });
    }

    private function normalizeDurum(string $durum): string
    {
        return match ($durum) {
            Siparis::DURUM_ODEME_BEKLENIYOR => Siparis::DURUM_ONAY_BEKLIYOR,
            Siparis::DURUM_ODENDI,
            Siparis::DURUM_HAZIRLANIYOR,
            Siparis::DURUM_BEKLEMEDE,
            Siparis::DURUM_ONAYLANDI => Siparis::DURUM_ONAYLANDI_YENI,
            Siparis::DURUM_KARGOLANDI => Siparis::DURUM_GONDERILDI,
            Siparis::DURUM_TAMAMLANDI => Siparis::DURUM_TESLIM_EDILDI,
            Siparis::DURUM_IPTAL => Siparis::DURUM_IPTAL_EDILDI,
            default => $durum,
        };
    }

    private function yazilacakDurumuBelirle(string $istenenDurum, string $normalizeDurum): string
    {
        return match ($istenenDurum) {
            Siparis::DURUM_ODEME_BEKLENIYOR,
            Siparis::DURUM_ODENDI,
            Siparis::DURUM_HAZIRLANIYOR,
            Siparis::DURUM_KARGOLANDI,
            Siparis::DURUM_TAMAMLANDI,
            Siparis::DURUM_IPTAL => $istenenDurum,
            default => $normalizeDurum,
        };
    }
}
