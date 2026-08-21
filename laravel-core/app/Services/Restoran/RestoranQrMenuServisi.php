<?php

namespace App\Services\Restoran;

use App\Models\Restoran\RestoranMenuKategorisi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Support\Collection;

final class RestoranQrMenuServisi
{
    /**
     * @return Collection<int, RestoranMenuKategorisi>
     */
    public function gorunurMenu(int $firmaId, ?int $subeId = null): Collection
    {
        return RestoranMenuKategorisi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->when($subeId, function ($query) use ($subeId): void {
                $query->where(function ($inner) use ($subeId): void {
                    $inner->whereNull('sube_id')
                        ->orWhere('sube_id', $subeId);
                });
            })
            ->whereHas('urunler', function ($query) use ($firmaId): void {
                $query
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('firma_id', $firmaId)
                    ->where('aktif_mi', true)
                    ->where('qr_menu_gorunur_mu', true)
                    ->where('stokta_var_mi', true);
            })
            ->with(['urunler' => function ($query) use ($firmaId): void {
                $query
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('firma_id', $firmaId)
                    ->where('aktif_mi', true)
                    ->where('qr_menu_gorunur_mu', true)
                    ->where('stokta_var_mi', true)
                    ->orderBy('siralama')
                    ->orderBy('ad');
            }])
            ->orderBy('siralama')
            ->orderBy('ad')
            ->get();
    }
}
