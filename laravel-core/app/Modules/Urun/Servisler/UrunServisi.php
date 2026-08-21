<?php

namespace App\Modules\Urun\Servisler;

use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokKategorisi;
use App\Models\Muhasebe\Depo;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\TenantContextService;
use App\Services\FirmaAyarDeposu;
use App\Support\SaaSemaYardimcisi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UrunServisi
{
    private const LISTE_CACHE_VERSION_KEY = 'urun_liste_cache_version';

    /**
     * @param  array<string, mixed>  $filtreler
     * @return LengthAwarePaginator<int, StokKarti>
     */
    public function listele(array $filtreler): LengthAwarePaginator
    {
        $aktifFirmaId = app(TenantContextService::class)->aktifFirmaId();
        $cacheVersiyonu = (int) Cache::get(self::LISTE_CACHE_VERSION_KEY, 1);

        $islem = function () use ($filtreler, $aktifFirmaId): LengthAwarePaginator {
            $with = ['kategori:id,ad,kod,slug'];
            if (SaaSemaYardimcisi::tabloVarMi('stok_karti_gorselleri')) {
                $with[] = 'gorseller';
            }

            $query = $this->yayinaUygunStokKayitlariSorgusu()->with($with);

            $kategori = $filtreler['kategori'] ?? null;
            $kategoriId = $filtreler['kategori_id'] ?? null;
            $kategoriIdleri = [];
            if (is_array($filtreler['kategori_ids'] ?? null)) {
                $kategoriIdleri = array_values(array_unique(array_filter(
                    array_map(static fn ($id): int => (int) $id, (array) $filtreler['kategori_ids']),
                    static fn (int $id): bool => $id > 0
                )));
            }

            if ($kategoriId !== null && (int) $kategoriId > 0) {
                $kategoriIdleri = $this->kategoriVeAltKategoriIdleri((int) $kategoriId);
            } elseif ($kategori !== null && $kategori !== '') {
                if (is_numeric($kategori)) {
                    $kategoriIdleri = $this->kategoriVeAltKategoriIdleri((int) $kategori);
                } else {
                    $kat = StokKategorisi::tenantScopeOlmadan(fn () => StokKategorisi::query()
                        ->where('slug', (string) $kategori)
                        ->first());
                    if ($kat) {
                        $kategoriIdleri = $this->kategoriVeAltKategoriIdleri((int) $kat->getKey());
                    } else {
                        return new LengthAwarePaginator(
                            items: [],
                            total: 0,
                            perPage: max(1, (int) ($filtreler['per_page'] ?? 12)),
                            currentPage: max(1, (int) ($filtreler['page'] ?? 1)),
                            options: ['path' => request()->url(), 'query' => request()->query()]
                        );
                    }
                }
            }

            if ($kategoriIdleri !== []) {
                $query->whereIn('kategori_id', $kategoriIdleri);
            }

            $arama = $filtreler['arama'] ?? null;
            if (is_string($arama) && trim($arama) !== '') {
                $terim = trim($arama);
                $query->where(function (Builder $q) use ($terim): void {
                    $q->where('ad', 'like', '%'.$terim.'%')
                        ->orWhere('barkod', 'like', '%'.$terim.'%')
                        ->orWhere('kod', 'like', '%'.$terim.'%');
                });
            }

            $fiyatMin = $filtreler['fiyat_min'] ?? null;
            if ($fiyatMin !== null && $fiyatMin !== '' && is_numeric($fiyatMin)) {
                $query->where('satis_fiyati', '>=', (float) $fiyatMin);
            }

            $fiyatMax = $filtreler['fiyat_max'] ?? null;
            if ($fiyatMax !== null && $fiyatMax !== '' && is_numeric($fiyatMax)) {
                $query->where('satis_fiyati', '<=', (float) $fiyatMax);
            }

            $stoktaVar = $filtreler['stokta_var'] ?? null;
            if ($stoktaVar === true || $stoktaVar === '1' || $stoktaVar === 1) {
                // stok_takip false ise her zaman stokta var kabul edilir.
                $query->where(function (Builder $q): void {
                    $q->where('stok_takip', false)
                        ->orWhere(function (Builder $q2): void {
                            $q2->where('stok_takip', true)
                                ->where('stok_miktari', '>', 0);
                        });
                });

                $depoId = $this->eTicaretStokDepoId($aktifFirmaId);
                if ($depoId !== null) {
                    $query->whereExists(function ($altSorgu) use ($depoId): void {
                        $altSorgu->selectRaw('1')
                            ->from('stok_depo_bakiyeleri as sdb')
                            ->whereColumn('sdb.stok_id', 'stok_kartlari.id')
                            ->where('sdb.depo_id', $depoId)
                            ->whereRaw('COALESCE(sdb.miktar, 0) - COALESCE(sdb.rezerve_miktar, 0) > 0');
                    });
                }
            }

            $siralama = $filtreler['siralama'] ?? 'yeni';
            $query->orderBy(match ((string) $siralama) {
                'fiyat' => 'satis_fiyati',
                'cok_satan' => 'satis_adedi',
                default => 'created_at',
            }, match ((string) $siralama) {
                'fiyat' => 'asc',
                default => 'desc',
            });
            if ((string) $siralama === 'yeni') {
                $query->orderByDesc('id');
            }

            $perPage = max(1, min(60, (int) ($filtreler['per_page'] ?? 12)));

            return $query
                ->select([
                    'id',
                    'slug',
                    'ad',
                    'satis_fiyati',
                    'indirimli_fiyat',
                    'para_birimi',
                    'kdv_orani',
                    'stok_takip',
                    'stok_miktari',
                    'satis_adedi',
                    'created_at',
                    'kategori_id',
                ])
                ->paginate($perPage)
                ->withQueryString();
        };

        $firmaPart = $aktifFirmaId !== null ? 'firma:'.$aktifFirmaId : 'firma:all';
        $cacheKey = 'urun_liste:v'.$cacheVersiyonu.':'.$firmaPart.':'.md5((string) json_encode($filtreler));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($aktifFirmaId, $islem): LengthAwarePaginator {
            return $aktifFirmaId !== null
                ? $islem()
                : StokKarti::tenantScopeOlmadan($islem);
        });
    }

    private function eTicaretStokDepoId(?int $firmaId): ?int
    {
        if ($firmaId === null || $firmaId < 1) {
            return null;
        }

        $ayar = app(FirmaAyarDeposu::class);
        if (! (bool) $ayar->oku($firmaId, 'stok_depo_modulu_aktif_mi', false)) {
            return null;
        }

        $depoId = (int) ($ayar->oku($firmaId, 'stok_varsayilan_depo_id', 0) ?? 0);
        if ($depoId > 0 && Depo::tenantScopeOlmadan(fn () => Depo::query()
            ->where('firma_id', $firmaId)
            ->whereKey($depoId)
            ->where('aktif_mi', true)
            ->exists())) {
            return $depoId;
        }

        return Depo::tenantScopeOlmadan(fn () => Depo::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->where('varsayilan_mi', true)
            ->value('id'));
    }

    /**
     * @throws NotFoundHttpException
     */
    public function detay(string $slug): StokKarti
    {
        $slug = trim($slug);
        if ($slug === '') {
            throw new NotFoundHttpException;
        }

        $aktifFirmaId = app(TenantContextService::class)->aktifFirmaId();
        $firmaPart = $aktifFirmaId !== null ? 'firma:'.$aktifFirmaId : 'firma:all';
        $cacheVersiyonu = (int) Cache::get(self::LISTE_CACHE_VERSION_KEY, 1);

        $islem = function () use ($slug): StokKarti {
            $query = $this->yayinaUygunStokKayitlariSorgusu();

            $with = [
                'kategori:id,ad,kod,slug',
                'marka:id,ad,kod',
                'model:id,ad,kod',
                'varyant:id,ad,kod',
                'tasarim:id,ad,kod',
                'malzemeTuru:id,ad,kod',
                'logoTuru:id,ad,kod',
            ];
            if (SaaSemaYardimcisi::tabloVarMi('stok_karti_gorselleri')) {
                $with[] = 'gorseller';
            }

            $urun = $query
                ->with($with)
                ->where('slug', $slug)
                ->first();

            if (! $urun) {
                throw new NotFoundHttpException;
            }

            return $urun;
        };

        return Cache::remember("urun_detay:v{$cacheVersiyonu}:{$firmaPart}:{$slug}", now()->addMinutes(10), function () use ($aktifFirmaId, $islem): StokKarti {
            return $aktifFirmaId !== null
                ? $islem()
                : StokKarti::tenantScopeOlmadan($islem);
        });
    }

    /**
     * @return Collection<int, StokKarti>
     */
    public function sitemapUrunleriGetir(): Collection
    {
        $aktifFirmaId = app(TenantContextService::class)->aktifFirmaId();

        $islem = function (): Collection {
            // Sitemap icin sadece yayinlanabilirleri cek; slug yoksa sirayla uret.
            $urunler = $this->yayinaUygunStokKayitlariSorgusu()
                ->select([
                    'id',
                    'slug',
                    'ad',
                    'updated_at',
                    'tur',
                    'durum',
                    'satis_fiyati',
                    'stok_takip',
                    'stok_miktari',
                    'firma_id',
                ])
                ->get();

            return $urunler;
        };

        return $aktifFirmaId !== null
            ? $islem()
            : StokKarti::tenantScopeOlmadan($islem);
    }

    /**
     * @return Collection<int, StokKarti>
     */
    public function kategoriyeGoreListele(int $kategoriId): Collection
    {
        return $this->listele([
            'kategori_ids' => $this->kategoriVeAltKategoriIdleri($kategoriId),
            'siralama' => 'yeni',
            'per_page' => 12,
        ])->getCollection();
    }

    /**
     * @return array<int, int>
     */
    public function kategoriVeAltKategoriIdleri(int $kategoriId): array
    {
        if ($kategoriId < 1) {
            return [];
        }

        $kategoriler = $this->aktifKategorileriGetir();
        if ($kategoriler->isEmpty()) {
            return [$kategoriId];
        }

        $childrenByParent = [];
        foreach ($kategoriler as $kategori) {
            $parentId = $kategori->parent_id ? (int) $kategori->parent_id : 0;
            $childrenByParent[$parentId] ??= [];
            $childrenByParent[$parentId][] = (int) $kategori->id;
        }

        $ids = [];
        $stack = [$kategoriId];
        while ($stack !== []) {
            $currentId = array_pop($stack);
            if (! is_int($currentId) || $currentId < 1 || in_array($currentId, $ids, true)) {
                continue;
            }

            $ids[] = $currentId;

            foreach ($childrenByParent[$currentId] ?? [] as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $stack[] = $childId;
                }
            }
        }

        return $ids;
    }

    /**
     * @return SupportCollection<int, StokKategorisi>
     */
    public function aktifKategorileriGetir(): SupportCollection
    {
        $islem = fn (): SupportCollection => StokKategorisi::query()
            ->select(['id', 'parent_id', 'ad', 'slug', 'aktif_mi'])
            ->where('aktif_mi', true)
            ->orderBy('ad')
            ->get();

        $aktifFirmaId = app(TenantContextService::class)->aktifFirmaId();
        $cacheVersiyonu = (int) Cache::get(self::LISTE_CACHE_VERSION_KEY, 1);
        $firmaPart = $aktifFirmaId !== null ? 'firma:'.$aktifFirmaId : 'firma:all';

        return Cache::remember("urun_kategoriler:v{$cacheVersiyonu}:{$firmaPart}", now()->addMinutes(30), function () use ($aktifFirmaId, $islem): SupportCollection {
            return $aktifFirmaId !== null
                ? $islem()
                : StokKategorisi::tenantScopeOlmadan($islem);
        });
    }

    private function yayinaUygunStokKayitlariSorgusu(): Builder
    {
        return StokKarti::query()
            ->where('tur', StokKartiTuru::ETicaret->value)
            ->where('durum', HesapDurumu::Aktif->value)
            ->whereRaw('TRIM(ad) <> \'\'')
            ->whereNotNull('satis_fiyati')
            ->where('satis_fiyati', '>', 0);
    }

    public static function cacheTemizle(): void
    {
        $mevcut = (int) Cache::get(self::LISTE_CACHE_VERSION_KEY, 1);
        Cache::forever(self::LISTE_CACHE_VERSION_KEY, $mevcut + 1);
    }
}
