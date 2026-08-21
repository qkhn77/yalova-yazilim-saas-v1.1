<?php

namespace App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi;
use App\Models\Muhasebe\KasaHesabi;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditKasaHesabi extends EditRecord
{
    protected static string $resource = KasaHesabiKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.kasa-hesabi-kaynagi.pages.edit-kasa-hesabi';

    protected static ?string $title = 'Kasa düzenle';

    protected function getHeaderActions(): array
    {
        $detayModu = KasaHesabiKaynagi::detayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? KasaHesabiKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
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
                throw ValidationException::withMessages(['firma_id' => 'Firma geçersiz.']);
            }
        }

        if (KasaHesabiKaynagi::hizliDuzenlemeModu()) {
            $alanlar = [
                'firma_id',
                'kod',
                'ad',
                'para_birimi',
                'sorumlu',
                'aciklama',
                'durum',
            ];

            $mevcut = KasaHesabi::query()
                ->whereKey($this->record->getKey())
                ->first($alanlar);

            if ($mevcut) {
                $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));
                $data = array_replace($mevcutVeri, $data);
            }
        }

        /** @var KasaHesabi $kayit */
        $kayit = $this->record;

        $kod = (string) ($data['kod'] ?? '');
        if ($kod === '') {
            throw ValidationException::withMessages(['kod' => 'Kod zorunludur.']);
        }
        if (! KasaHesabiKaynagi::kodBenzersizMi((int) $data['firma_id'], $kod, (int) $kayit->getKey())) {
            throw ValidationException::withMessages(['kod' => 'Bu kod bu firma için zaten kullanılıyor.']);
        }

        return $data;
    }
}
