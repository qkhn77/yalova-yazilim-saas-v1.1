<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DovizKuruTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DovizKuruTanimKaynagi;
use App\Models\Muhasebe\DovizKuru;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateDovizKuru extends CreateRecord
{
    protected static string $resource = DovizKuruTanimKaynagi::class;

    protected static ?string $title = 'Kur ekle';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $superAdminMi = KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());
        $sabitMi = $superAdminMi ? (bool) ($data['is_sabit'] ?? false) : false;

        if ($sabitMi) {
            $data['firma_id'] = null;
            $data['is_sabit'] = true;
        } elseif (! $superAdminMi) {
            $firmaId = (int) (($data['firma_id'] ?? app(TenantContextService::class)->aktifFirmaId()) ?? 0);
            if ($firmaId < 1) {
                throw ValidationException::withMessages(['firma_id' => 'Aktif firma bulunamadi.']);
            }
            $data['firma_id'] = $firmaId;
            $data['is_sabit'] = false;
        } else {
            $firmaId = (int) ($data['firma_id'] ?? 0);
            if ($firmaId < 1) {
                throw ValidationException::withMessages(['firma_id' => 'Firma secilmelidir.']);
            }
            $data['firma_id'] = $firmaId;
            $data['is_sabit'] = false;
        }

        $kaynak = Str::upper(trim((string) ($data['kaynak_para_birimi'] ?? '')));
        $hedef = Str::upper(trim((string) ($data['hedef_para_birimi'] ?? '')));
        if (strlen($kaynak) !== 3 || strlen($hedef) !== 3) {
            throw ValidationException::withMessages(['kaynak_para_birimi' => 'Para birimi kodlari 3 karakter olmalidir.']);
        }

        $tarih = (string) ($data['tarih'] ?? now()->toDateString());
        $kur = number_format((float) ($data['kur'] ?? 0), 8, '.', '');
        if ((float) $kur <= 0) {
            throw ValidationException::withMessages(['kur' => 'Kur sifirdan buyuk olmalidir.']);
        }

        $kapsam = ($data['is_sabit'] ?? false) ? 0 : (int) ($data['firma_id'] ?? 0);
        $var = DovizKuru::tenantScopeOlmadan(fn () => DovizKuru::query()
            ->where('tanim_firma_kapsami', $kapsam)
            ->where('kaynak_para_birimi', $kaynak)
            ->where('hedef_para_birimi', $hedef)
            ->whereDate('tarih', $tarih)
            ->exists());
        if ($var) {
            throw ValidationException::withMessages(['tarih' => 'Bu parite ve tarih icin zaten kur kaydi var.']);
        }

        $data['tanim_firma_kapsami'] = $kapsam;
        $data['kaynak_para_birimi'] = $kaynak;
        $data['hedef_para_birimi'] = $hedef;
        $data['kur'] = $kur;
        $data['manuel_mi'] = (bool) ($data['manuel_mi'] ?? true);
        $data['saglayici'] = strtolower((string) ($data['saglayici'] ?? ($data['manuel_mi'] ? 'manuel' : 'tcmb')));

        return $data;
    }
}
