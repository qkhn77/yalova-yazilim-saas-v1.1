<?php

namespace App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi;
use App\Models\Muhasebe\PosHesabi;
use App\Muhasebe\Servisler\FirmaIciKodUretici;
use App\Services\TenantContextService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreatePosHesabi extends CreateRecord
{
    protected static string $resource = PosHesabiKaynagi::class;

    protected static ?string $title = 'POS ekle';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $kullanici = Auth::user();
        $super = $kullanici && PosHesabi::kullaniciSuperAdminMi($kullanici);

        if (! $super) {
            $fid = app(TenantContextService::class)->aktifFirmaId();
            if (! $fid) {
                throw ValidationException::withMessages(['firma_id' => 'Aktif firma oturumu yok.']);
            }
            $data['firma_id'] = $fid;
        } else {
            $data['firma_id'] = (int) ($data['firma_id'] ?? 0);
            if ($data['firma_id'] < 1) {
                throw ValidationException::withMessages(['firma_id' => 'Firma seçilmelidir.']);
            }
        }

        PosHesabiKaynagi::dogrulaBankaHesabiFirma((int) $data['firma_id'], $data);

        $data['kod'] = app(FirmaIciKodUretici::class)->sonraki((int) $data['firma_id'], PosHesabi::class, 'POS');

        return $data;
    }
}
