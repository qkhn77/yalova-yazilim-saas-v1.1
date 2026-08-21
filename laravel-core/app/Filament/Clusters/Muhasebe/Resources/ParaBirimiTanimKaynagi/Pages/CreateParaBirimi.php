<?php

namespace App\Filament\Clusters\Muhasebe\Resources\ParaBirimiTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\ParaBirimiTanimKaynagi;
use App\Models\Muhasebe\ParaBirimi;
use App\Services\TenantContextService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateParaBirimi extends CreateRecord
{
    protected static string $resource = ParaBirimiTanimKaynagi::class;

    protected static ?string $title = 'Para birimi ekle';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $kullanici = Auth::user();
        $super = $kullanici && ParaBirimi::kullaniciSuperAdminMi($kullanici);

        $data['is_sabit'] = $super ? (bool) ($data['is_sabit'] ?? false) : false;

        if ($data['is_sabit']) {
            $data['firma_id'] = null;
        } elseif (! $super) {
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

        $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
        if (strlen($kod) !== 3 || ! ctype_alpha($kod)) {
            throw ValidationException::withMessages(['kod' => 'Kod tam 3 harf olmalıdır (örn. TRY).']);
        }

        $kapsam = $data['is_sabit'] ? 0 : (int) $data['firma_id'];

        $var = ParaBirimi::tenantScopeOlmadan(fn () => ParaBirimi::query()
            ->where('tanim_firma_kapsami', $kapsam)
            ->whereRaw('UPPER(kod) = ?', [$kod])
            ->exists());
        if ($var) {
            throw ValidationException::withMessages(['kod' => 'Bu kod bu kapsamda zaten tanımlı.']);
        }

        $data['kod'] = $kod;
        $data['aktif_mi'] = (bool) ($data['aktif_mi'] ?? true);

        return $data;
    }
}
