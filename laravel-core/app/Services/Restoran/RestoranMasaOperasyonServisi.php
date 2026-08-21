<?php

namespace App\Services\Restoran;

use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMasasi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RestoranMasaOperasyonServisi
{
    public function masaAdisyonuAc(RestoranMasasi $masa, ?int $garsonPersonelId = null): RestoranAdisyonu
    {
        return DB::transaction(function () use ($masa, $garsonPersonelId): RestoranAdisyonu {
            $masa = $this->masayiKilitle($masa);

            if (! $masa->aktif_mi || $masa->durum === RestoranMasasi::DURUM_KAPALI) {
                throw ValidationException::withMessages([
                    'masa_id' => 'Masa aktif ve kullanilabilir olmalidir.',
                ]);
            }

            $acikVar = RestoranAdisyonu::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $masa->firma_id)
                ->where('masa_id', $masa->id)
                ->whereIn('durum', [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE])
                ->exists();

            if ($acikVar) {
                throw ValidationException::withMessages([
                    'masa_id' => 'Masada zaten acik adisyon vardir.',
                ]);
            }

            return RestoranAdisyonu::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->create([
                    'firma_id' => $masa->firma_id,
                    'sube_id' => $masa->sube_id,
                    'masa_id' => $masa->id,
                    'garson_personel_id' => $garsonPersonelId,
                    'siparis_tipi' => 'masa',
                    'musteri_sayisi' => 1,
                    'para_birimi' => 'TRY',
                    'durum' => RestoranAdisyonu::DURUM_ACIK,
                ]);
        });
    }

    public function odemeyeAl(RestoranAdisyonu $adisyon): RestoranAdisyonu
    {
        return DB::transaction(function () use ($adisyon): RestoranAdisyonu {
            $adisyon = $this->adisyonuKilitle($adisyon);
            $this->acikAdisyonDogrula($adisyon);

            if ($adisyon->durum === RestoranAdisyonu::DURUM_ACIK) {
                $adisyon->forceFill([
                    'durum' => RestoranAdisyonu::DURUM_ODEMEDE,
                ])->save();
            }

            return $adisyon->refresh();
        });
    }

    public function masaTasi(RestoranAdisyonu $adisyon, RestoranMasasi $hedefMasa): RestoranAdisyonu
    {
        return DB::transaction(function () use ($adisyon, $hedefMasa): RestoranAdisyonu {
            $adisyon = $this->adisyonuKilitle($adisyon);
            $hedefMasa = $this->masayiKilitle($hedefMasa);

            $this->acikAdisyonDogrula($adisyon);
            $this->hedefMasaDogrula($adisyon, $hedefMasa);

            $eskiMasaId = $adisyon->masa_id;
            $adisyon->forceFill([
                'masa_id' => $hedefMasa->id,
                'sube_id' => $hedefMasa->sube_id ?: $adisyon->sube_id,
            ])->save();

            if ($eskiMasaId) {
                $this->masaDurumunuGuncelle((int) $adisyon->firma_id, (int) $eskiMasaId);
            }

            return $adisyon->refresh();
        });
    }

    /**
     * Kaynak masadaki açık adisyonu hedef masadaki açık adisyona aktarır.
     */
    public function masalariBirlestir(RestoranAdisyonu $kaynakAdisyon, RestoranAdisyonu $hedefAdisyon): RestoranAdisyonu
    {
        return DB::transaction(function () use ($kaynakAdisyon, $hedefAdisyon): RestoranAdisyonu {
            $kaynakAdisyon = $this->adisyonuKilitle($kaynakAdisyon);
            $hedefAdisyon = $this->adisyonuKilitle($hedefAdisyon);

            $this->acikAdisyonDogrula($kaynakAdisyon, 'Kaynak adisyon açık olmalıdır.');
            $this->acikAdisyonDogrula($hedefAdisyon, 'Hedef adisyon açık olmalıdır.');

            $this->ayniFirmaDogrula($kaynakAdisyon, $hedefAdisyon);
            $this->ayniParaBirimiDogrula($kaynakAdisyon, $hedefAdisyon);

            if ((int) $kaynakAdisyon->id === (int) $hedefAdisyon->id) {
                throw ValidationException::withMessages([
                    'adisyon_id' => 'Aynı adisyon birleştirilemez.',
                ]);
            }

            $kaynakMasaId = $kaynakAdisyon->masa_id;

            RestoranAdisyonKalemi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $kaynakAdisyon->firma_id)
                ->where('adisyon_id', $kaynakAdisyon->id)
                ->update([
                    'adisyon_id' => $hedefAdisyon->id,
                    'updated_at' => now(),
                ]);

            $this->adisyonToplamlariniGuncelle($kaynakAdisyon);
            $this->adisyonToplamlariniGuncelle($hedefAdisyon);

            $kaynakAdisyon->forceFill([
                'durum' => RestoranAdisyonu::DURUM_IPTAL,
                'kapanis_at' => now(),
                'notlar' => trim((string) $kaynakAdisyon->notlar."\nHedef adisyona birleştirildi: {$hedefAdisyon->adisyon_no}"),
            ])->save();

            if ($kaynakMasaId) {
                $this->masaDurumunuGuncelle((int) $kaynakAdisyon->firma_id, (int) $kaynakMasaId);
            }

            return $hedefAdisyon->refresh();
        });
    }

    /**
     * Seçili kalemleri yeni bir adisyona taşır. Hedef masa verilirse yeni adisyon o masada açılır.
     *
     * @param  array<int>  $kalemIdleri
     */
    public function adisyonuBol(RestoranAdisyonu $kaynakAdisyon, array $kalemIdleri, ?RestoranMasasi $hedefMasa = null): RestoranAdisyonu
    {
        return DB::transaction(function () use ($kaynakAdisyon, $kalemIdleri, $hedefMasa): RestoranAdisyonu {
            $kaynakAdisyon = $this->adisyonuKilitle($kaynakAdisyon);
            $hedefMasa = $hedefMasa ? $this->masayiKilitle($hedefMasa) : null;

            $this->acikAdisyonDogrula($kaynakAdisyon);

            if ($hedefMasa) {
                $this->hedefMasaDogrula($kaynakAdisyon, $hedefMasa);
            }

            $kalemIdleri = array_values(array_unique(array_map('intval', $kalemIdleri)));
            if ($kalemIdleri === []) {
                throw ValidationException::withMessages([
                    'kalemler' => 'Bölünecek en az bir adisyon kalemi seçilmelidir.',
                ]);
            }

            $toplamKalemSayisi = RestoranAdisyonKalemi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $kaynakAdisyon->firma_id)
                ->where('adisyon_id', $kaynakAdisyon->id)
                ->count();

            $tasinacakKalemSayisi = RestoranAdisyonKalemi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $kaynakAdisyon->firma_id)
                ->where('adisyon_id', $kaynakAdisyon->id)
                ->whereIn('id', $kalemIdleri)
                ->count();

            if ($tasinacakKalemSayisi !== count($kalemIdleri)) {
                throw ValidationException::withMessages([
                    'kalemler' => 'Seçilen kalemlerin tamamı kaynak adisyona ait olmalıdır.',
                ]);
            }

            if ($tasinacakKalemSayisi >= $toplamKalemSayisi) {
                throw ValidationException::withMessages([
                    'kalemler' => 'Adisyon bölme işleminde kaynak adisyonda en az bir kalem kalmalıdır.',
                ]);
            }

            $yeniAdisyon = RestoranAdisyonu::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->create([
                    'firma_id' => $kaynakAdisyon->firma_id,
                    'sube_id' => $hedefMasa?->sube_id ?: $kaynakAdisyon->sube_id,
                    'masa_id' => $hedefMasa?->id,
                    'cari_id' => $kaynakAdisyon->cari_id,
                    'garson_personel_id' => $kaynakAdisyon->garson_personel_id,
                    'siparis_tipi' => $kaynakAdisyon->siparis_tipi,
                    'musteri_sayisi' => 1,
                    'para_birimi' => $kaynakAdisyon->para_birimi,
                    'notlar' => 'Bölünen kaynak adisyon: '.$kaynakAdisyon->adisyon_no,
                ]);

            RestoranAdisyonKalemi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $kaynakAdisyon->firma_id)
                ->where('adisyon_id', $kaynakAdisyon->id)
                ->whereIn('id', $kalemIdleri)
                ->update([
                    'adisyon_id' => $yeniAdisyon->id,
                    'updated_at' => now(),
                ]);

            $this->adisyonToplamlariniGuncelle($kaynakAdisyon);
            $this->adisyonToplamlariniGuncelle($yeniAdisyon);

            return $yeniAdisyon->refresh();
        });
    }

    private function adisyonuKilitle(RestoranAdisyonu $adisyon): RestoranAdisyonu
    {
        $this->aktifFirmaDogrula((int) $adisyon->firma_id);

        return RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->whereKey($adisyon->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function masayiKilitle(RestoranMasasi $masa): RestoranMasasi
    {
        $this->aktifFirmaDogrula((int) $masa->firma_id);

        return RestoranMasasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $masa->firma_id)
            ->whereKey($masa->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function acikAdisyonDogrula(RestoranAdisyonu $adisyon, string $mesaj = 'Adisyon açık olmalıdır.'): void
    {
        if (! in_array((string) $adisyon->durum, [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE], true)) {
            throw ValidationException::withMessages([
                'adisyon_id' => $mesaj,
            ]);
        }

        if ($adisyon->finans_hareketi_id) {
            throw ValidationException::withMessages([
                'adisyon_id' => 'Tahsilatı yapılmış adisyonda masa operasyonu yapılamaz.',
            ]);
        }
    }

    private function hedefMasaDogrula(RestoranAdisyonu $adisyon, RestoranMasasi $hedefMasa): void
    {
        if ((int) $adisyon->firma_id !== (int) $hedefMasa->firma_id) {
            throw ValidationException::withMessages([
                'masa_id' => 'Hedef masa aynı firmaya ait olmalıdır.',
            ]);
        }

        if (! $hedefMasa->aktif_mi || $hedefMasa->durum === RestoranMasasi::DURUM_KAPALI) {
            throw ValidationException::withMessages([
                'masa_id' => 'Hedef masa aktif ve kullanılabilir olmalıdır.',
            ]);
        }

        if ($hedefMasa->sube_id && $adisyon->sube_id && (int) $hedefMasa->sube_id !== (int) $adisyon->sube_id) {
            throw ValidationException::withMessages([
                'masa_id' => 'Hedef masa adisyon şubesiyle uyumlu olmalıdır.',
            ]);
        }

        $acikVar = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->where('masa_id', $hedefMasa->id)
            ->whereIn('durum', [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE])
            ->when($adisyon->exists, fn ($query) => $query->whereKeyNot($adisyon->id))
            ->exists();

        if ($acikVar) {
            throw ValidationException::withMessages([
                'masa_id' => 'Hedef masada açık adisyon vardır.',
            ]);
        }
    }

    private function aktifFirmaDogrula(int $firmaId): void
    {
        $aktifFirmaId = app(TenantContextService::class)->aktifFirmaId();

        if ($aktifFirmaId && (int) $aktifFirmaId !== $firmaId) {
            throw ValidationException::withMessages([
                'firma_id' => 'Masa operasyonu sadece aktif firma için yapılabilir.',
            ]);
        }
    }

    private function ayniFirmaDogrula(RestoranAdisyonu $kaynakAdisyon, RestoranAdisyonu $hedefAdisyon): void
    {
        if ((int) $kaynakAdisyon->firma_id !== (int) $hedefAdisyon->firma_id) {
            throw ValidationException::withMessages([
                'adisyon_id' => 'Adisyonlar aynı firmaya ait olmalıdır.',
            ]);
        }
    }

    private function ayniParaBirimiDogrula(RestoranAdisyonu $kaynakAdisyon, RestoranAdisyonu $hedefAdisyon): void
    {
        if ((string) $kaynakAdisyon->para_birimi !== (string) $hedefAdisyon->para_birimi) {
            throw ValidationException::withMessages([
                'para_birimi' => 'Adisyon para birimleri aynı olmalıdır.',
            ]);
        }
    }

    private function adisyonToplamlariniGuncelle(RestoranAdisyonu $adisyon): void
    {
        app(RestoranAdisyonKalemKuralServisi::class)->adisyonToplamlariniGuncelle(new RestoranAdisyonKalemi([
            'firma_id' => $adisyon->firma_id,
            'adisyon_id' => $adisyon->id,
        ]));
    }

    private function masaDurumunuGuncelle(int $firmaId, int $masaId): void
    {
        $masa = RestoranMasasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($masaId)
            ->first();

        if (! $masa) {
            return;
        }

        $dummyAdisyon = new RestoranAdisyonu([
            'firma_id' => $firmaId,
            'masa_id' => $masaId,
            'durum' => RestoranAdisyonu::DURUM_KAPANDI,
        ]);

        app(RestoranAdisyonKuralServisi::class)->masaDurumunuGuncelle($dummyAdisyon);
    }
}
