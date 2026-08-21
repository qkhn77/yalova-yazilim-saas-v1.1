<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\MasrafKategorisi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MasrafKategoriServisi
{
    public function __construct(
        private readonly MuhasebeFirmaErisimDenetleyicisi $firmaDenetleyicisi,
    ) {}

    /**
     * Kategori oluşturur veya mevcut firmanın kategorisini günceller.
     * Kategori silinmez; geçmiş masrafların bütünlüğü için pasifleştirilebilir.
     *
     * @param array{ad:string, ust_kategori_id?:int|string|null, sira?:int|string, aktif_mi?:bool} $alanlar
     */
    public function kaydet(int $firmaId, array $alanlar, ?int $kategoriId = null): MasrafKategorisi
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $ad = trim((string) ($alanlar['ad'] ?? ''));
        if ($ad === '') {
            throw new IsKuraliIstisnasi('Masraf türü adı zorunludur.');
        }

        $kategori = DB::transaction(function () use ($firmaId, $alanlar, $kategoriId, $ad): MasrafKategorisi {
            $kategori = $kategoriId === null
                ? null
                : MasrafKategorisi::query()
                    ->where('firma_id', $firmaId)
                    ->whereKey($kategoriId)
                    ->lockForUpdate()
                    ->first();

            if ($kategoriId !== null && ! $kategori) {
                throw new IsKuraliIstisnasi('Masraf türü bu firmaya ait değil.');
            }

            $ustKategoriId = $this->ustKategoriIdDogrula(
                $firmaId,
                array_key_exists('ust_kategori_id', $alanlar)
                    ? $alanlar['ust_kategori_id']
                    : ($kategori?->ust_kategori_id ?? null),
                $kategori,
            );

            if ($kategori?->sistem_mi && $ad !== $kategori->ad) {
                throw new IsKuraliIstisnasi('Sabit masraf kategorilerinin adı değiştirilemez.');
            }

            if ($kategori?->sistem_mi && $ustKategoriId !== (int) ($kategori->ust_kategori_id ?? 0)) {
                throw new IsKuraliIstisnasi('Sabit masraf kategorilerinin üst kategorisi değiştirilemez.');
            }

            $ayniAd = MasrafKategorisi::query()
                ->where('firma_id', $firmaId)
                ->whereRaw('LOWER(ad) = LOWER(?)', [$ad])
                ->when($kategori, fn ($query) => $query->whereKeyNot($kategori->getKey()))
                ->exists();

            if ($ayniAd) {
                throw new IsKuraliIstisnasi('Bu masraf türü zaten tanımlı.');
            }

            if ($kategori) {
                $kategori->update([
                    'ad' => $kategori->sistem_mi ? $kategori->ad : $ad,
                    'ust_kategori_id' => $kategori->sistem_mi ? $kategori->ust_kategori_id : $ustKategoriId,
                    'sira' => max(0, min(65535, (int) ($alanlar['sira'] ?? $kategori->sira))),
                    'secilir_mi' => $kategori->sistem_mi ? $kategori->secilir_mi : $ustKategoriId === null,
                    'aktif_mi' => (bool) ($alanlar['aktif_mi'] ?? $kategori->aktif_mi),
                ]);

                if ($ustKategoriId !== null) {
                    MasrafKategorisi::query()->whereKey($ustKategoriId)->update(['secilir_mi' => false]);
                }

                return $kategori->fresh();
            }

            $yeniKategori = MasrafKategorisi::query()->create([
                'firma_id' => $firmaId,
                'ust_kategori_id' => $ustKategoriId,
                'kod' => $this->benzersizKodUret($firmaId, $ad),
                'ad' => $ad,
                'sira' => max(0, min(65535, (int) ($alanlar['sira'] ?? 500))),
                'sistem_mi' => false,
                'secilir_mi' => $ustKategoriId === null,
                'aktif_mi' => (bool) ($alanlar['aktif_mi'] ?? true),
            ]);

            if ($ustKategoriId !== null) {
                MasrafKategorisi::query()->whereKey($ustKategoriId)->update(['secilir_mi' => false]);
            }

            return $yeniKategori;
        });

        $this->kategoriCacheleriniTemizle($firmaId);

        return $kategori;
    }

    public function durumDegistir(int $firmaId, int $kategoriId): MasrafKategorisi
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $kategori = DB::transaction(function () use ($firmaId, $kategoriId): MasrafKategorisi {
            $kategori = MasrafKategorisi::query()
                ->where('firma_id', $firmaId)
                ->whereKey($kategoriId)
                ->lockForUpdate()
                ->first();

            if (! $kategori) {
                throw new IsKuraliIstisnasi('Masraf türü bu firmaya ait değil.');
            }

            $kategori->update(['aktif_mi' => ! $kategori->aktif_mi]);

            return $kategori->fresh();
        });

        $this->kategoriCacheleriniTemizle($firmaId);

        return $kategori;
    }

    private function benzersizKodUret(int $firmaId, string $ad): string
    {
        $temelKod = Str::of($ad)->ascii()->slug('_')->limit(56, '')->toString();
        $temelKod = $temelKod !== '' ? $temelKod : 'masraf_turu';
        $kod = $temelKod;
        $sayac = 2;

        while (MasrafKategorisi::query()->where('firma_id', $firmaId)->where('kod', $kod)->exists()) {
            $sonEk = '_'.$sayac;
            $kod = Str::substr($temelKod, 0, 64 - Str::length($sonEk)).$sonEk;
            $sayac++;
        }

        return $kod;
    }

    private function ustKategoriIdDogrula(int $firmaId, mixed $ustKategoriId, ?MasrafKategorisi $mevcut): ?int
    {
        $ustKategoriId = (int) ($ustKategoriId ?: 0);
        if ($ustKategoriId < 1) {
            return null;
        }

        if ($mevcut && (int) $mevcut->getKey() === $ustKategoriId) {
            throw new IsKuraliIstisnasi('Masraf kategorisi kendisinin üst kategorisi olamaz.');
        }

        $ust = MasrafKategorisi::query()
            ->where('firma_id', $firmaId)
            ->whereKey($ustKategoriId)
            ->first();

        if (! $ust) {
            throw new IsKuraliIstisnasi('Üst masraf kategorisi bu firmaya ait değil.');
        }

        if ($ust->ust_kategori_id !== null) {
            throw new IsKuraliIstisnasi('Masraf kategorileri en fazla iki seviyeli olabilir.');
        }

        if (! $ust->aktif_mi) {
            throw new IsKuraliIstisnasi('Pasif üst kategori altına yeni masraf türü eklenemez.');
        }

        return (int) $ust->getKey();
    }

    private function kategoriCacheleriniTemizle(int $firmaId): void
    {
        Cache::forget(MasrafKategorisi::secenekCacheAnahtari($firmaId));
        Cache::forget(MasrafKategorisi::anaKategoriCacheAnahtari($firmaId));
        Cache::forget(MasrafKategorisi::varsayilanHazirlikCacheAnahtari($firmaId));
        Cache::forget('masraf:duzenli-fatura-kategorileri:v1:'.$firmaId);
    }
}
