<?php

namespace App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi;
use App\Models\Muhasebe\BankaHesabi;
use App\Muhasebe\Servisler\FirmaIciKodUretici;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateBankaHesabi extends CreateRecord
{
    protected static string $resource = BankaHesabiKaynagi::class;

    protected static ?string $title = 'Banka ekle';

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
                throw ValidationException::withMessages(['firma_id' => 'Firma secilmelidir.']);
            }
        }

        $data['kod'] = app(FirmaIciKodUretici::class)->sonraki((int) $data['firma_id'], BankaHesabi::class, 'BANKA');

        return $data;
    }
}
