<?php

namespace App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi;
use App\Models\Muhasebe\BankaHesabi;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditBankaHesabi extends EditRecord
{
    protected static string $resource = BankaHesabiKaynagi::class;

    protected static ?string $title = 'Banka duzenle';

    protected function getHeaderActions(): array
    {
        $detayModu = BankaHesabiKaynagi::detayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hizli Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? BankaHesabiKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (BankaHesabiKaynagi::hizliDuzenlemeModu()) {
            foreach ([
                'firma_id',
                'ad',
                'kod',
                'banka_adi',
                'hesap_sahibi_unvan',
                'sube',
                'hesap_no',
                'iban',
                'para_birimi',
                'aciklama',
            ] as $alan) {
                $data[$alan] = $data[$alan] ?? $this->record->{$alan};
            }
        }

        $super = KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());

        if (! $super) {
            $aktif = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
            if ($aktif < 1 || (int) $this->record->firma_id !== $aktif) {
                abort(403);
            }
            $data['firma_id'] = $aktif;
        } else {
            $data['firma_id'] = (int) ($data['firma_id'] ?? $this->record->firma_id);
            if ($data['firma_id'] < 1) {
                throw ValidationException::withMessages(['firma_id' => 'Firma gecersiz.']);
            }
        }

        /** @var BankaHesabi $kayit */
        $kayit = $this->record;

        $kod = (string) ($data['kod'] ?? '');
        if ($kod === '') {
            throw ValidationException::withMessages(['kod' => 'Kod zorunludur.']);
        }
        if (! BankaHesabiKaynagi::kodBenzersizMi((int) $data['firma_id'], $kod, (int) $kayit->getKey())) {
            throw ValidationException::withMessages(['kod' => 'Bu kod bu firma icin zaten kullaniliyor.']);
        }

        return $data;
    }
}
