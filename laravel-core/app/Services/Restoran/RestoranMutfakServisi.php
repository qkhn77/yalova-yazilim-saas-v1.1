<?php

namespace App\Services\Restoran;

use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Services\TenantContextService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class RestoranMutfakServisi
{
    private const AKTIF_DURUMLAR = [
        RestoranAdisyonKalemi::DURUM_YENI,
        RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR,
        RestoranAdisyonKalemi::DURUM_HAZIR,
    ];

    private const SIPARIS_TIPLERI = ['masa', 'qr', 'paket', 'online', 'gel-al'];

    /**
     * @return Collection<int, RestoranAdisyonKalemi>
     */
    public function mutfakKuyrugu(
        int $firmaId,
        string $durumFiltresi = 'aktif',
        string $siparisTipiFiltresi = 'tum',
        int $limit = 100,
        Carbon|string|null $referansZamani = null,
        int $gecikmeDakikasi = 15
    ): Collection {
        $referans = Carbon::parse($referansZamani ?? now());
        $durumFiltresi = $this->durumFiltresiniNormalizeEt($durumFiltresi);
        $siparisTipiFiltresi = $this->siparisTipiniNormalizeEt($siparisTipiFiltresi);
        $limit = max(1, min($limit, 200));

        $kalemler = RestoranAdisyonKalemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->select([
                'id',
                'firma_id',
                'adisyon_id',
                'hazirlayan_personel_id',
                'urun_adi',
                'miktar',
                'durum',
                'mutfak_notu',
                'created_at',
            ])
            ->with([
                'adisyon:id,firma_id,masa_id,adisyon_no,siparis_tipi,durum',
                'adisyon.masa:id,ad',
                'hazirlayan:id,ad_soyad',
            ])
            ->where('firma_id', $firmaId)
            ->when(
                $durumFiltresi === 'aktif',
                fn ($query) => $query->whereIn('durum', self::AKTIF_DURUMLAR),
                fn ($query) => $query->where('durum', $durumFiltresi)
            )
            ->whereHas('adisyon', function ($query) use ($firmaId, $siparisTipiFiltresi): void {
                $query
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('firma_id', $firmaId)
                    ->whereIn('durum', [
                        RestoranAdisyonu::DURUM_ACIK,
                        RestoranAdisyonu::DURUM_ODEMEDE,
                    ])
                    ->when($siparisTipiFiltresi !== 'tum', fn ($inner) => $inner->where('siparis_tipi', $siparisTipiFiltresi));
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        return $kalemler->each(function (RestoranAdisyonKalemi $kalem) use ($referans, $gecikmeDakikasi): void {
            $beklemeDakika = Carbon::parse($kalem->created_at)->diffInMinutes($referans);

            $kalem->setAttribute('bekleme_dakika', $beklemeDakika);
            $kalem->setAttribute('gecikti_mi', $beklemeDakika >= $gecikmeDakikasi && in_array((string) $kalem->durum, self::AKTIF_DURUMLAR, true));
        });
    }

    /**
     * @return array<string, int>
     */
    public function durumOzeti(int $firmaId, Carbon|string|null $referansZamani = null, int $gecikmeDakikasi = 15): array
    {
        $referans = Carbon::parse($referansZamani ?? now());
        $gecikmeEsigi = $referans->copy()->subMinutes($gecikmeDakikasi);

        $temelSorgu = RestoranAdisyonKalemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereIn('durum', self::AKTIF_DURUMLAR)
            ->whereHas('adisyon', function ($query) use ($firmaId): void {
                $query
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('firma_id', $firmaId)
                    ->whereIn('durum', [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE]);
            });

        $satirlar = (clone $temelSorgu)
            ->select('durum')
            ->selectRaw('COUNT(*) as adet')
            ->groupBy('durum')
            ->pluck('adet', 'durum')
            ->all();

        return [
            RestoranAdisyonKalemi::DURUM_YENI => (int) ($satirlar[RestoranAdisyonKalemi::DURUM_YENI] ?? 0),
            RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR => (int) ($satirlar[RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR] ?? 0),
            RestoranAdisyonKalemi::DURUM_HAZIR => (int) ($satirlar[RestoranAdisyonKalemi::DURUM_HAZIR] ?? 0),
            'aktif_toplam' => (int) array_sum($satirlar),
            'geciken' => (int) (clone $temelSorgu)->where('created_at', '<=', $gecikmeEsigi)->count(),
        ];
    }

    public function hazirlamayaAl(RestoranAdisyonKalemi $kalem, ?int $personelId = null): RestoranAdisyonKalemi
    {
        return $this->durumDegistir($kalem, RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR, $personelId);
    }

    public function hazirIsaretle(RestoranAdisyonKalemi $kalem, ?int $personelId = null): RestoranAdisyonKalemi
    {
        return $this->durumDegistir($kalem, RestoranAdisyonKalemi::DURUM_HAZIR, $personelId);
    }

    public function servisEdildiIsaretle(RestoranAdisyonKalemi $kalem, ?int $personelId = null): RestoranAdisyonKalemi
    {
        return $this->durumDegistir($kalem, RestoranAdisyonKalemi::DURUM_SERVIS_EDILDI, $personelId);
    }

    public function iptalEt(RestoranAdisyonKalemi $kalem, ?string $neden = null): RestoranAdisyonKalemi
    {
        $kalem = $this->kalemiYenile($kalem);
        $this->adisyonAcikDogrula($kalem);

        if ($kalem->durum === RestoranAdisyonKalemi::DURUM_SERVIS_EDILDI) {
            throw ValidationException::withMessages([
                'durum' => 'Servis edilmiş kalem mutfak ekranından iptal edilemez.',
            ]);
        }

        $not = trim((string) $kalem->mutfak_notu);
        if ($neden !== null && trim($neden) !== '') {
            $not = trim($not."\nİptal nedeni: ".trim($neden));
        }

        $kalem->fill([
            'durum' => RestoranAdisyonKalemi::DURUM_IPTAL,
            'mutfak_notu' => $not !== '' ? $not : null,
        ])->save();

        return $kalem->refresh();
    }

    private function durumDegistir(RestoranAdisyonKalemi $kalem, string $hedefDurum, ?int $personelId): RestoranAdisyonKalemi
    {
        $kalem = $this->kalemiYenile($kalem);
        $this->adisyonAcikDogrula($kalem);
        $this->durumGecisiDogrula((string) $kalem->durum, $hedefDurum);

        $veri = ['durum' => $hedefDurum];
        if ($personelId) {
            $veri['hazirlayan_personel_id'] = $personelId;
        }

        $kalem->fill($veri)->save();

        return $kalem->refresh();
    }

    private function kalemiYenile(RestoranAdisyonKalemi $kalem): RestoranAdisyonKalemi
    {
        $this->aktifFirmaDogrula((int) $kalem->firma_id);

        return RestoranAdisyonKalemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $kalem->firma_id)
            ->whereKey($kalem->id)
            ->firstOrFail();
    }

    private function adisyonAcikDogrula(RestoranAdisyonKalemi $kalem): void
    {
        $adisyon = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $kalem->firma_id)
            ->whereKey($kalem->adisyon_id)
            ->first();

        if (! $adisyon || ! in_array((string) $adisyon->durum, [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE], true)) {
            throw ValidationException::withMessages([
                'adisyon_id' => 'Kapalı veya iptal adisyonun mutfak durumu değiştirilemez.',
            ]);
        }

        if ($adisyon->finans_hareketi_id) {
            throw ValidationException::withMessages([
                'adisyon_id' => 'Tahsilatı yapılmış adisyonun mutfak durumu değiştirilemez.',
            ]);
        }
    }

    private function durumGecisiDogrula(string $mevcutDurum, string $hedefDurum): void
    {
        $izinliGecisler = [
            RestoranAdisyonKalemi::DURUM_YENI => [
                RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR,
                RestoranAdisyonKalemi::DURUM_HAZIR,
            ],
            RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR => [
                RestoranAdisyonKalemi::DURUM_HAZIR,
            ],
            RestoranAdisyonKalemi::DURUM_HAZIR => [
                RestoranAdisyonKalemi::DURUM_SERVIS_EDILDI,
            ],
            RestoranAdisyonKalemi::DURUM_SERVIS_EDILDI => [],
            RestoranAdisyonKalemi::DURUM_IPTAL => [],
        ];

        if (! in_array($hedefDurum, $izinliGecisler[$mevcutDurum] ?? [], true)) {
            throw ValidationException::withMessages([
                'durum' => 'Mutfak durum geçişi geçerli değil.',
            ]);
        }
    }

    private function aktifFirmaDogrula(int $firmaId): void
    {
        $aktifFirmaId = app(TenantContextService::class)->aktifFirmaId();

        if ($aktifFirmaId && (int) $aktifFirmaId !== $firmaId) {
            throw ValidationException::withMessages([
                'firma_id' => 'Mutfak işlemi sadece aktif firma için yapılabilir.',
            ]);
        }
    }

    private function durumFiltresiniNormalizeEt(string $durumFiltresi): string
    {
        if ($durumFiltresi === 'aktif') {
            return 'aktif';
        }

        $tumDurumlar = [
            ...self::AKTIF_DURUMLAR,
            RestoranAdisyonKalemi::DURUM_SERVIS_EDILDI,
            RestoranAdisyonKalemi::DURUM_IPTAL,
        ];

        return in_array($durumFiltresi, $tumDurumlar, true) ? $durumFiltresi : 'aktif';
    }

    private function siparisTipiniNormalizeEt(string $siparisTipiFiltresi): string
    {
        return in_array($siparisTipiFiltresi, self::SIPARIS_TIPLERI, true) ? $siparisTipiFiltresi : 'tum';
    }
}
