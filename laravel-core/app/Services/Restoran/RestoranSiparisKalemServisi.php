<?php

namespace App\Services\Restoran;

use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMenuKategorisi;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RestoranSiparisKalemServisi
{
    public function menuUrunuEkle(
        RestoranAdisyonu $adisyon,
        RestoranMenuUrunu $menuUrunu,
        float $miktar = 1,
        ?string $mutfakNotu = null
    ): RestoranAdisyonKalemi {
        return DB::transaction(function () use ($adisyon, $menuUrunu, $miktar, $mutfakNotu): RestoranAdisyonKalemi {
            $adisyon = $this->adisyonuKilitle($adisyon);
            $menuUrunu = $this->menuUrununuKilitle($menuUrunu);
            $kategori = $this->kategori($menuUrunu);

            $this->adisyonUygunMu($adisyon);
            $this->menuUrunuUygunMu($adisyon, $menuUrunu, $kategori);

            return RestoranAdisyonKalemi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->create([
                    'firma_id' => $adisyon->firma_id,
                    'adisyon_id' => $adisyon->id,
                    'menu_urunu_id' => $menuUrunu->id,
                    'stok_karti_id' => $menuUrunu->stok_karti_id,
                    'urun_adi' => $menuUrunu->ad,
                    'miktar' => $miktar,
                    'birim_fiyat' => $menuUrunu->fiyat,
                    'kdv_orani' => $menuUrunu->kdv_orani,
                    'mutfak_notu' => $mutfakNotu,
                    'durum' => RestoranAdisyonKalemi::DURUM_YENI,
                ]);
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

    private function menuUrununuKilitle(RestoranMenuUrunu $menuUrunu): RestoranMenuUrunu
    {
        return RestoranMenuUrunu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $menuUrunu->firma_id)
            ->whereKey($menuUrunu->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function kategori(RestoranMenuUrunu $menuUrunu): ?RestoranMenuKategorisi
    {
        return RestoranMenuKategorisi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $menuUrunu->firma_id)
            ->whereKey($menuUrunu->kategori_id)
            ->first();
    }

    private function adisyonUygunMu(RestoranAdisyonu $adisyon): void
    {
        if (! in_array((string) $adisyon->durum, [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE], true)) {
            throw ValidationException::withMessages([
                'adisyon_id' => 'Kapali veya iptal adisyona urun eklenemez.',
            ]);
        }

        if ($adisyon->finans_hareketi_id) {
            throw ValidationException::withMessages([
                'adisyon_id' => 'Tahsilati yapilmis adisyona urun eklenemez.',
            ]);
        }
    }

    private function menuUrunuUygunMu(
        RestoranAdisyonu $adisyon,
        RestoranMenuUrunu $menuUrunu,
        ?RestoranMenuKategorisi $kategori
    ): void {
        $hatalar = [];

        if ((int) $adisyon->firma_id !== (int) $menuUrunu->firma_id || ! $kategori) {
            $hatalar['menu_urunu_id'][] = 'Secilen menu urunu bu firmaya ait degil.';
        }

        if (! $menuUrunu->aktif_mi || ! $menuUrunu->stokta_var_mi) {
            $hatalar['menu_urunu_id'][] = 'Secilen menu urunu aktif ve stokta olmalidir.';
        }

        if ($kategori && ! $kategori->aktif_mi) {
            $hatalar['kategori_id'][] = 'Menu urununun kategorisi aktif olmalidir.';
        }

        if ($kategori?->sube_id && $adisyon->sube_id && (int) $kategori->sube_id !== (int) $adisyon->sube_id) {
            $hatalar['sube_id'][] = 'Menu urunu adisyon subesiyle uyumlu degil.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }

    private function aktifFirmaDogrula(int $firmaId): void
    {
        $aktifFirmaId = app(TenantContextService::class)->aktifFirmaId();

        if ($aktifFirmaId && (int) $aktifFirmaId !== $firmaId) {
            throw ValidationException::withMessages([
                'firma_id' => 'Sipariş kalemi sadece aktif firma adisyonuna eklenebilir.',
            ]);
        }
    }
}
