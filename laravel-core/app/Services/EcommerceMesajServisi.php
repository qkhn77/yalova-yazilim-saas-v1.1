<?php

namespace App\Services;

use App\Models\Ecommerce\EcommerceMesaj;
use App\Models\Ecommerce\EcommerceMesajKonu;
use App\Support\EcommerceMesajTanimlari;
use Illuminate\Support\Facades\DB;

class EcommerceMesajServisi
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function konuOlustur(array $data): EcommerceMesajKonu
    {
        return DB::transaction(function () use ($data): EcommerceMesajKonu {
            $firmaId = (int) ($data['firma_id'] ?? 0);
            $gonderenTipi = (string) ($data['gonderen_tipi'] ?? EcommerceMesajTanimlari::GONDEREN_MUSTERI);
            $icerik = trim((string) ($data['ilk_mesaj'] ?? ''));

            $konu = EcommerceMesajKonu::query()->create([
                'firma_id' => $firmaId,
                'konu_tipi' => (string) ($data['konu_tipi'] ?? EcommerceMesajTanimlari::KONU_TIPI_MUSTERI),
                'kullanici_id' => isset($data['kullanici_id']) && is_numeric((string) $data['kullanici_id']) ? (int) $data['kullanici_id'] : null,
                'stok_karti_id' => isset($data['stok_karti_id']) && is_numeric((string) $data['stok_karti_id']) ? (int) $data['stok_karti_id'] : null,
                'siparis_id' => isset($data['siparis_id']) && is_numeric((string) $data['siparis_id']) ? (int) $data['siparis_id'] : null,
                'visible_on_product' => (bool) ($data['visible_on_product'] ?? false),
                'baslik' => (string) ($data['baslik'] ?? 'Yeni mesaj konusu'),
                'musteri_ad_soyad' => (string) ($data['musteri_ad_soyad'] ?? ''),
                'musteri_email' => (string) ($data['musteri_email'] ?? ''),
                'musteri_telefon' => (string) ($data['musteri_telefon'] ?? ''),
                'durum' => EcommerceMesajTanimlari::DURUM_YENI,
                'okunmamis_mi' => true,
                'okunmamis_mesaj_sayisi' => 1,
                'son_musteri_mesaji_at' => $gonderenTipi === EcommerceMesajTanimlari::GONDEREN_MUSTERI ? now() : null,
                'son_admin_mesaji_at' => $gonderenTipi === EcommerceMesajTanimlari::GONDEREN_ADMIN ? now() : null,
                'sla_son_tarih_at' => now()->addHours(12),
                'sla_ihlal_mi' => false,
            ]);

            if ($icerik !== '') {
                EcommerceMesaj::query()->create([
                    'konu_id' => (int) $konu->id,
                    'firma_id' => $firmaId,
                    'kullanici_id' => $konu->kullanici_id,
                    'gonderen_tipi' => $gonderenTipi,
                    'ic_not_mu' => false,
                    'icerik' => $icerik,
                ]);
            }

            return $konu->fresh('mesajlar');
        });
    }

    public function mesajiEkle(
        EcommerceMesajKonu $konu,
        string $gonderenTipi,
        string $icerik,
        bool $icNot = false,
        bool $tamamlandiSecili = false,
        ?string $manuelDurum = null,
    ): EcommerceMesajKonu {
        return DB::transaction(function () use ($konu, $gonderenTipi, $icerik, $icNot, $tamamlandiSecili, $manuelDurum): EcommerceMesajKonu {
            $icerik = trim($icerik);
            if ($icerik === '') {
                return $konu->fresh('mesajlar');
            }

            EcommerceMesaj::query()->create([
                'konu_id' => (int) $konu->id,
                'firma_id' => (int) $konu->firma_id,
                'kullanici_id' => $konu->kullanici_id,
                'gonderen_tipi' => $gonderenTipi,
                'ic_not_mu' => $icNot,
                'icerik' => $icerik,
            ]);

            $updates = $this->durumGuncellemePayload($konu, $gonderenTipi, $tamamlandiSecili, $manuelDurum);
            $konu->update($updates);

            return $konu->fresh('mesajlar');
        });
    }

    public function slaDurumlariniGuncelle(?int $firmaId = null): int
    {
        $query = EcommerceMesajKonu::query()->whereIn('durum', EcommerceMesajTanimlari::slaTakipDurumlari());
        if ($firmaId !== null && $firmaId > 0) {
            $query->where('firma_id', $firmaId);
        }

        $sayac = 0;
        $query->chunkById(200, function ($konular) use (&$sayac): void {
            foreach ($konular as $konu) {
                $deadline = $konu->sla_son_tarih_at;
                $ihlal = $deadline !== null && $deadline->lt(now());
                if ((bool) $konu->sla_ihlal_mi === $ihlal) {
                    continue;
                }

                $konu->update(['sla_ihlal_mi' => $ihlal]);
                $sayac++;
            }
        });

        return $sayac;
    }

    /**
     * @return array<string, mixed>
     */
    private function durumGuncellemePayload(
        EcommerceMesajKonu $konu,
        string $gonderenTipi,
        bool $tamamlandiSecili,
        ?string $manuelDurum,
    ): array {
        if ($tamamlandiSecili) {
            return [
                'durum' => EcommerceMesajTanimlari::DURUM_TAMAMLANDI,
                'okunmamis_mi' => false,
                'okunmamis_mesaj_sayisi' => 0,
                'sla_ihlal_mi' => false,
                'sla_son_tarih_at' => null,
                'tamamlandi_at' => now(),
                'son_admin_mesaji_at' => $gonderenTipi === EcommerceMesajTanimlari::GONDEREN_ADMIN ? now() : $konu->son_admin_mesaji_at,
                'son_musteri_mesaji_at' => $gonderenTipi === EcommerceMesajTanimlari::GONDEREN_MUSTERI ? now() : $konu->son_musteri_mesaji_at,
            ];
        }

        if (is_string($manuelDurum) && array_key_exists($manuelDurum, EcommerceMesajTanimlari::durumlar())) {
            return [
                'durum' => $manuelDurum,
                'okunmamis_mi' => in_array($manuelDurum, [EcommerceMesajTanimlari::DURUM_YENI, EcommerceMesajTanimlari::DURUM_OKUNMAMIS, EcommerceMesajTanimlari::DURUM_MUSTERI_YANITI_GELDI], true),
                'okunmamis_mesaj_sayisi' => in_array($manuelDurum, [EcommerceMesajTanimlari::DURUM_YENI, EcommerceMesajTanimlari::DURUM_OKUNMAMIS, EcommerceMesajTanimlari::DURUM_MUSTERI_YANITI_GELDI], true)
                    ? max(1, (int) $konu->okunmamis_mesaj_sayisi)
                    : 0,
                'sla_son_tarih_at' => in_array($manuelDurum, EcommerceMesajTanimlari::slaTakipDurumlari(), true) ? now()->addHours(12) : null,
                'sla_ihlal_mi' => false,
                'tamamlandi_at' => $manuelDurum === EcommerceMesajTanimlari::DURUM_TAMAMLANDI ? now() : null,
                'son_admin_mesaji_at' => $gonderenTipi === EcommerceMesajTanimlari::GONDEREN_ADMIN ? now() : $konu->son_admin_mesaji_at,
                'son_musteri_mesaji_at' => $gonderenTipi === EcommerceMesajTanimlari::GONDEREN_MUSTERI ? now() : $konu->son_musteri_mesaji_at,
            ];
        }

        if ($gonderenTipi === EcommerceMesajTanimlari::GONDEREN_ADMIN) {
            return [
                'durum' => EcommerceMesajTanimlari::DURUM_YANITLANDI,
                'okunmamis_mi' => false,
                'okunmamis_mesaj_sayisi' => 0,
                'son_admin_mesaji_at' => now(),
                'ilk_yanit_at' => $konu->ilk_yanit_at ?? now(),
                'sla_son_tarih_at' => null,
                'sla_ihlal_mi' => false,
                'tamamlandi_at' => null,
            ];
        }

        $dahaOnceAdminYanitVar = $konu->mesajlar()
            ->where('gonderen_tipi', EcommerceMesajTanimlari::GONDEREN_ADMIN)
            ->exists();

        return [
            'durum' => $dahaOnceAdminYanitVar ? EcommerceMesajTanimlari::DURUM_OKUNMAMIS : EcommerceMesajTanimlari::DURUM_YENI,
            'okunmamis_mi' => true,
            'okunmamis_mesaj_sayisi' => max(1, ((int) $konu->okunmamis_mesaj_sayisi) + 1),
            'son_musteri_mesaji_at' => now(),
            'sla_son_tarih_at' => now()->addHours(12),
            'sla_ihlal_mi' => false,
            'tamamlandi_at' => null,
        ];
    }
}
