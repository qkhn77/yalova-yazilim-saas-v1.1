<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DovizKuruTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DovizKuruTanimKaynagi;
use App\Models\Muhasebe\DovizKuru;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EditDovizKuru extends EditRecord
{
    protected static string $resource = DovizKuruTanimKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.tanimlar.doviz-kuru-tanim-kaynagi.pages.edit-doviz-kuru';

    protected static ?string $title = 'Kur duzenle';

    protected function getHeaderActions(): array
    {
        $detayModu = DovizKuruTanimKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? request()->fullUrlWithoutQuery('detay')
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (DovizKuruTanimKaynagi::detayModu()) {
            return parent::getFormActions();
        }

        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! DovizKuruTanimKaynagi::detayModu()) {
            $data = array_replace($this->record->only([
                'firma_id',
                'is_sabit',
                'tarih',
                'kaynak_para_birimi',
                'hedef_para_birimi',
                'kur',
                'saglayici',
                'manuel_mi',
            ]), $data);
        }

        $superAdminMi = KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());

        if (! $superAdminMi) {
            if ((bool) $this->record->is_sabit) {
                abort(403);
            }
            $aktifFirmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
            if ($aktifFirmaId < 1 || (int) ($this->record->firma_id ?? 0) !== $aktifFirmaId) {
                abort(403);
            }
            $data['firma_id'] = $aktifFirmaId;
            $data['is_sabit'] = false;
        } else {
            $sabitMi = (bool) ($data['is_sabit'] ?? $this->record->is_sabit);
            $data['is_sabit'] = $sabitMi;
            if ($sabitMi) {
                $data['firma_id'] = null;
            } else {
                $firmaId = (int) ($data['firma_id'] ?? $this->record->firma_id);
                if ($firmaId < 1) {
                    throw ValidationException::withMessages(['firma_id' => 'Firma secilmelidir.']);
                }
                $data['firma_id'] = $firmaId;
            }
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
            ->whereKeyNot($this->record->getKey())
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
