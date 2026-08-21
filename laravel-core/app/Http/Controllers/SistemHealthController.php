<?php

namespace App\Http\Controllers;

use App\Models\Ecommerce\Siparis;
use App\Models\Muhasebe\StokKarti;
use App\Models\SistemOlayi;
use App\Services\TenantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SistemHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $scope = static fn ($q) => $firmaId ? $q->where('firma_id', $firmaId) : $q;

        $kritikler = $scope(SistemOlayi::query()->withoutGlobalScopes())
            ->where('seviye', 'critical')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'tip', 'mesaj', 'firma_id', 'created_at']);

        $odemeBekleyen = $scope(Siparis::query()->withoutGlobalScopes())
            ->whereIn('durum', [Siparis::DURUM_ONAY_BEKLIYOR, Siparis::DURUM_ODEME_BEKLENIYOR])
            ->count();

        $timeoutSayisi = $scope(Siparis::query()->withoutGlobalScopes())
            ->whereIn('durum', [Siparis::DURUM_ONAY_BEKLIYOR, Siparis::DURUM_ODEME_BEKLENIYOR])
            ->whereNotNull('odeme_suresi_bitis_at')
            ->where('odeme_suresi_bitis_at', '<', Carbon::now())
            ->count();

        $negatifStok = $scope(StokKarti::query()->withoutGlobalScopes())
            ->where('negative_flag', true)
            ->count();

        return response()->json([
            'ok' => true,
            'firma_id' => $firmaId,
            'kritik_hatalar' => $kritikler,
            'odeme_bekleyen_siparis_sayisi' => $odemeBekleyen,
            'timeout_olmus_siparis_sayisi' => $timeoutSayisi,
            'negatif_stok_sayisi' => $negatifStok,
        ]);
    }
}
