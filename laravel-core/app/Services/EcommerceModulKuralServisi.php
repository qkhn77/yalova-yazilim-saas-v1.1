<?php

namespace App\Services;

use App\Models\Ecommerce\SepetKalemi;
use App\Models\Ecommerce\Siparis;
use App\Models\Muhasebe\StokKarti;
use App\Support\DenetimYardimcisi;
use Illuminate\Http\Request;

class EcommerceModulKuralServisi
{
    /** @var array<string, bool> */
    private array $erisimCache = [];

    public function __construct(
        private readonly TenantContextService $tenantContextService,
        private readonly ModulErisimService $modulErisimService,
        private readonly EcommerceFirmaAyarServisi $ecommerceFirmaAyarServisi,
    ) {}

    public function firmaIdBelirle(Request $request): ?int
    {
        $aktifFirmaId = $this->tenantContextService->aktifFirmaId();
        if ($aktifFirmaId !== null && $aktifFirmaId > 0) {
            return $aktifFirmaId;
        }

        $siparis = $request->route('siparis');
        if ($siparis instanceof Siparis) {
            $id = (int) ($siparis->firma_id ?? 0);
            if ($id > 0) {
                return $id;
            }
        } elseif (is_numeric($siparis) && (int) $siparis > 0) {
            $id = (int) Siparis::query()->whereKey((int) $siparis)->value('firma_id');
            if ($id > 0) {
                return $id;
            }
        }

        $slug = (string) ($request->route('slug') ?? '');
        if ($slug !== '') {
            $id = (int) StokKarti::tenantScopeOlmadan(fn (): int => (int) StokKarti::query()
                ->where('slug', $slug)
                ->value('firma_id'));
            if ($id > 0) {
                return $id;
            }
        }

        $kalemId = (int) ($request->route('kalemId') ?? 0);
        if ($kalemId > 0) {
            $id = (int) SepetKalemi::query()
                ->whereKey($kalemId)
                ->join('stok_kartlari', 'stok_kartlari.id', '=', 'sepet_kalemleri.stok_karti_id')
                ->value('stok_kartlari.firma_id');
            if ($id > 0) {
                return $id;
            }
        }

        if ($request->hasSession()) {
            $sessionSepetId = (int) $request->session()->get('aktif_sepet_id', 0);
            if ($sessionSepetId > 0) {
                $id = (int) SepetKalemi::query()
                    ->where('sepet_id', $sessionSepetId)
                    ->join('stok_kartlari', 'stok_kartlari.id', '=', 'sepet_kalemleri.stok_karti_id')
                    ->value('stok_kartlari.firma_id');
                if ($id > 0) {
                    return $id;
                }
            }
        }

        return null;
    }

    public function erisimAcikMi(?int $firmaId): bool
    {
        if ($firmaId === null || $firmaId <= 0) {
            return true;
        }

        $cacheKey = $firmaId.'|'.$this->ecommerceFirmaAyarServisi->runtimeCacheSurumu();

        if (array_key_exists($cacheKey, $this->erisimCache)) {
            return $this->erisimCache[$cacheKey];
        }

        if (! $this->modulErisimService->modulErisilebilirMi($firmaId, 'e_ticaret')) {
            return $this->erisimCache[$cacheKey] = false;
        }

        if (! $this->ecommerceFirmaAyarServisi->ayarVarMi($firmaId)) {
            // Firma ayarı oluşturulmamışsa, modül aktifliğini esas al.
            return $this->erisimCache[$cacheKey] = true;
        }

        return $this->erisimCache[$cacheKey] = $this->ecommerceFirmaAyarServisi->firmaEtkinMi($firmaId, false);
    }

    public function engelliErisimiKaydet(Request $request, ?int $firmaId): void
    {
        DenetimYardimcisi::kaydet(
            olay: 'ecommerce.kapali_modul_erisim_engellendi',
            konuTipi: Siparis::class,
            konuId: null,
            firmaId: $firmaId,
            eskiVeri: null,
            yeniVeri: [
                'yol' => '/'.ltrim($request->path(), '/'),
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'firma_id' => $firmaId,
            ],
        );
    }
}
