<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\KasaHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Services\FirmaAyarDeposu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class KasaSilmeServisi
{
    public function __construct(private readonly FirmaAyarDeposu $ayarDeposu) {}

    /**
     * Kasa kayıtlarını firma içindeki Masaüstü kasasına taşıyarak hesabı siler.
     * Finans ve operasyon kayıtları korunur; yalnızca kasa bağlantısı değiştirilir.
     *
     * @return array{hedefler: array<int, string>, tasinan_kayit: int}
     */
    public function tasiyarakSil(KasaHesabi $kasa): array
    {
        $firmaId = (int) $kasa->firma_id;
        if ($firmaId < 1) {
            throw ValidationException::withMessages(['record' => 'Kasanın firması bulunamadı.']);
        }

        return DB::transaction(function () use ($kasa, $firmaId): array {
            /** @var KasaHesabi $kilitliKasa */
            $kilitliKasa = KasaHesabi::query()->lockForUpdate()->findOrFail($kasa->getKey());
            if ((int) $kilitliKasa->firma_id !== $firmaId) {
                throw ValidationException::withMessages(['record' => 'Kasa farklı firmaya ait.']);
            }

            $hedefler = [];
            $tasinanKayit = 0;
            $paraBirimi = strtoupper(trim((string) ($kilitliKasa->para_birimi ?: 'TRY')));

            $hareketParaBirimleri = KasaHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('kasa_hesap_id', $kilitliKasa->getKey())
                ->select('para_birimi')
                ->distinct()
                ->pluck('para_birimi')
                ->map(fn ($kod): string => strtoupper(trim((string) $kod)))
                ->filter()
                ->unique()
                ->values();

            if ($this->kasaBaglantisiVarMi($firmaId, (int) $kilitliKasa->getKey())) {
                $hareketParaBirimleri = $hareketParaBirimleri->push($paraBirimi)->unique()->values();
            }

            foreach ($hareketParaBirimleri as $kod) {
                $hedef = $this->masaustuKasasi($firmaId, $kod, (int) $kilitliKasa->getKey());
                $hedefler[] = $hedef->ad.' ('.$hedef->kod.')';
                $tasinanKayit += $this->kasaBaglantilariniTasi($firmaId, (int) $kilitliKasa->getKey(), (int) $hedef->getKey());
            }

            $ayarAnahtari = 'ecommerce_tahsilat_kasa_id';
            if ((int) $this->ayarDeposu->oku($firmaId, $ayarAnahtari, 0) === (int) $kilitliKasa->getKey()) {
                $hedef = $this->masaustuKasasi($firmaId, $paraBirimi, (int) $kilitliKasa->getKey());
                if (! in_array($hedef->ad.' ('.$hedef->kod.')', $hedefler, true)) {
                    $hedefler[] = $hedef->ad.' ('.$hedef->kod.')';
                }
                $this->ayarDeposu->yaz($firmaId, $ayarAnahtari, (int) $hedef->getKey());
            }

            $kilitliKasa->delete();

            return compact('hedefler', 'tasinanKayit');
        });
    }

    private function masaustuKasasi(int $firmaId, string $paraBirimi, int $haricId): KasaHesabi
    {
        $paraBirimi = strtoupper($paraBirimi ?: 'TRY');
        $ad = $paraBirimi === 'TRY' ? 'Masaüstü' : 'Masaüstü ('.$paraBirimi.')';
        $hedef = KasaHesabi::withTrashed()
            ->where('firma_id', $firmaId)
            ->where('ad', $ad)
            ->where('para_birimi', $paraBirimi)
            ->whereKeyNot($haricId)
            ->first();

        if ($hedef) {
            if ($hedef->trashed()) {
                $hedef->restore();
            }

            return $hedef;
        }

        $kodTabani = 'MASAUSTU'.($paraBirimi !== 'TRY' ? '-'.$paraBirimi : '');
        $kod = $kodTabani;
        $sayac = 1;
        while (KasaHesabi::withTrashed()->where('firma_id', $firmaId)->where('kod', $kod)->exists()) {
            $kod = $kodTabani.'-'.$sayac++;
        }

        return KasaHesabi::query()->create([
            'firma_id' => $firmaId,
            'kod' => $kod,
            'ad' => $ad,
            'para_birimi' => $paraBirimi,
            'durum' => 'aktif',
            'aciklama' => 'Silinen kasa kayıtlarının korunduğu sistem kasası.',
        ]);
    }

    private function kasaBaglantisiVarMi(int $firmaId, int $kasaId): bool
    {
        foreach ($this->baglantiTablosuVeKolonlari() as [$tablo, $kolon]) {
            if (Schema::hasTable($tablo) && Schema::hasColumn($tablo, $kolon)
                && DB::table($tablo)->where('firma_id', $firmaId)->where($kolon, $kasaId)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function kasaBaglantilariniTasi(int $firmaId, int $kaynakId, int $hedefId): int
    {
        $toplam = 0;
        foreach ($this->baglantiTablosuVeKolonlari() as [$tablo, $kolon]) {
            if (! Schema::hasTable($tablo) || ! Schema::hasColumn($tablo, $kolon)) {
                continue;
            }

            $guncelleme = [$kolon => $hedefId];
            if (Schema::hasColumn($tablo, 'updated_at')) {
                $guncelleme['updated_at'] = now();
            }

            $toplam += DB::table($tablo)
                ->where('firma_id', $firmaId)
                ->where($kolon, $kaynakId)
                ->update($guncelleme);
        }

        return $toplam;
    }

    /** @return array<int, array{0:string,1:string}> */
    private function baglantiTablosuVeKolonlari(): array
    {
        return [
            ['kasa_hareketleri', 'kasa_hesap_id'],
            ['teknik_servis_kayitlari', 'tahsilat_kasa_hesap_id'],
            ['teknik_servis_tahsilatlari', 'kasa_hesap_id'],
            ['personel_avanslari', 'kasa_hesap_id'],
            ['personel_maas_odeme_kayitlari', 'kasa_hesap_id'],
            ['restoran_adisyonlari', 'kasa_hesap_id'],
            ['restoran_adisyon_tahsilatlari', 'kasa_hesap_id'],
        ];
    }
}
