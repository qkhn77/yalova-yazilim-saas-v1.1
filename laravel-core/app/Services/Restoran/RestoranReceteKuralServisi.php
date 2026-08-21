<?php

namespace App\Services\Restoran;

use App\Models\Muhasebe\StokKarti;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Restoran\RestoranReceteKalemi;
use App\Models\Restoran\RestoranRecetesi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Validation\ValidationException;

final class RestoranReceteKuralServisi
{
    public function receteDogrula(RestoranRecetesi $recete): void
    {
        if (! $recete->firma_id) {
            return;
        }

        $menuUrunu = RestoranMenuUrunu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $recete->firma_id)
            ->whereKey($recete->menu_urunu_id)
            ->first();

        if (! $menuUrunu) {
            throw ValidationException::withMessages([
                'menu_urunu_id' => 'Secilen menu urunu bu firmaya ait degil.',
            ]);
        }

        if (trim((string) $recete->ad) === '') {
            $recete->ad = $menuUrunu->ad.' recetesi';
        }
    }

    public function kalemDogrula(RestoranReceteKalemi $kalem): void
    {
        if (! $kalem->firma_id) {
            return;
        }

        $recete = RestoranRecetesi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $kalem->firma_id)
            ->whereKey($kalem->recete_id)
            ->first();

        $stok = StokKarti::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $kalem->firma_id)
            ->whereKey($kalem->stok_karti_id)
            ->first();

        $hatalar = [];

        if (! $recete) {
            $hatalar['recete_id'][] = 'Secilen recete bu firmaya ait degil.';
        }

        if (! $stok) {
            $hatalar['stok_karti_id'][] = 'Secilen stok karti bu firmaya ait degil.';
        }

        if ((float) ($kalem->miktar ?? 0) <= 0) {
            $hatalar['miktar'][] = 'Recete miktari sifirdan buyuk olmalidir.';
        }

        if ((float) ($kalem->fire_orani ?? 0) < 0) {
            $hatalar['fire_orani'][] = 'Fire orani negatif olamaz.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }
}
