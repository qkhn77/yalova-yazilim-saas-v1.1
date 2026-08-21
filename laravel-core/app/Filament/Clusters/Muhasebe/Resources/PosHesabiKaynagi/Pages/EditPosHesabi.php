<?php

namespace App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi;
use App\Models\Muhasebe\PosHesabi;
use App\Services\TenantContextService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditPosHesabi extends EditRecord
{
    protected static string $resource = PosHesabiKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.pos-hesabi-kaynagi.pages.edit-pos-hesabi';

    protected static ?string $title = 'POS düzenle';

    protected function getHeaderActions(): array
    {
        $detayModu = PosHesabiKaynagi::detayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? PosHesabiKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
            : request()->fullUrlWithQuery(['detay' => 1])),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $kullanici = Auth::user();
        $super = $kullanici && PosHesabi::kullaniciSuperAdminMi($kullanici);

        if (! $super) {
            $aktif = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
            if ($aktif < 1 || (int) $this->record->firma_id !== $aktif) {
                abort(403);
            }
            $data['firma_id'] = $aktif;
        } else {
            $data['firma_id'] = (int) ($data['firma_id'] ?? $this->record->firma_id);
            if ($data['firma_id'] < 1) {
                throw ValidationException::withMessages(['firma_id' => 'Firma geçersiz.']);
            }
        }

        PosHesabiKaynagi::dogrulaBankaHesabiFirma((int) $data['firma_id'], $data);

        $kod = (string) ($data['kod'] ?? '');
        if ($kod === '') {
            throw ValidationException::withMessages(['kod' => 'Kod zorunludur.']);
        }
        if (! PosHesabiKaynagi::kodBenzersizMi((int) $data['firma_id'], $kod, (int) $this->record->getKey())) {
            throw ValidationException::withMessages(['kod' => 'Bu kod bu firma için zaten kullanılıyor.']);
        }

        return $data;
    }
}
