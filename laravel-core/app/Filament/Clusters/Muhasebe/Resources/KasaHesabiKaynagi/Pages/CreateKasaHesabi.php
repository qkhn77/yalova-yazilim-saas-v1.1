<?php

namespace App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi;
use App\Models\Muhasebe\KasaHesabi;
use App\Muhasebe\Servisler\FirmaIciKodUretici;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateKasaHesabi extends CreateRecord
{
    protected static string $resource = KasaHesabiKaynagi::class;

    protected static ?string $title = 'Kasa ekle';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $super = KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());

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

        $data['kod'] = app(FirmaIciKodUretici::class)->sonraki((int) $data['firma_id'], KasaHesabi::class, 'KASA');

        return $data;
    }
}
