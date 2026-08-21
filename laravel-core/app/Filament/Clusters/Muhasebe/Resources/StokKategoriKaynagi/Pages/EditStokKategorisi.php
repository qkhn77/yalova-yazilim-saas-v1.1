<?php

namespace App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi;
use App\Models\Muhasebe\StokKategorisi as StokKategoriModel;
use App\Services\TenantContextService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditStokKategorisi extends EditRecord
{
    protected static string $resource = StokKategoriKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.stok-kategori-kaynagi.pages.edit-stok-kategorisi';

    protected static ?string $title = 'Kategori düzenle';

    protected function getHeaderActions(): array
    {
        $resource = static::getResource();
        $detayModu = $resource::detayModu();

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
        $resource = static::getResource();

        if ($resource::detayModu()) {
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
        $kullanici = Auth::user();
        $super = $kullanici && StokKategoriModel::kullaniciSuperAdminMi($kullanici);

        if (! $super) {
            if ($this->record->is_sabit) {
                abort(403);
            }
            $aktif = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
            if ($aktif < 1 || (int) $this->record->firma_id !== $aktif) {
                abort(403);
            }
            $data['firma_id'] = $aktif;
            $data['is_sabit'] = false;
        } else {
            $data['is_sabit'] = (bool) ($data['is_sabit'] ?? $this->record->is_sabit);
            if ($data['is_sabit']) {
                $data['firma_id'] = null;
            } else {
                $data['firma_id'] = (int) ($data['firma_id'] ?? $this->record->firma_id);
                if ($data['firma_id'] < 1) {
                    throw ValidationException::withMessages(['firma_id' => 'Firma geçersiz.']);
                }
            }
        }

        if (static::getResource()::hizliDuzenlemeModu()) {
            $alanlar = [
                'firma_id',
                'parent_id',
                'kod',
                'ad',
                'aciklama',
                'aktif_mi',
                'is_sabit',
            ];

            $mevcut = StokKategoriModel::query()
                ->whereKey($this->record->getKey())
                ->first($alanlar);

            if ($mevcut) {
                $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));
                $data = array_replace($mevcutVeri, $data);
            }
        }

        $kod = trim((string) ($data['kod'] ?? ''));
        $ad = trim((string) ($data['ad'] ?? ''));
        if ($kod === '' || $ad === '') {
            throw ValidationException::withMessages(['kod' => 'Kod ve ad zorunludur.']);
        }

        $kapsam = ($data['is_sabit'] ?? false) ? 0 : (int) $data['firma_id'];

        $varKod = StokKategoriModel::tenantScopeOlmadan(fn () => StokKategoriModel::query()
            ->where('tanim_firma_kapsami', $kapsam)
            ->where('kod', $kod)
            ->whereKeyNot((int) $this->record->getKey())
            ->exists());
        if ($varKod) {
            throw ValidationException::withMessages(['kod' => 'Bu kod bu kapsamda zaten var.']);
        }

        $varAd = StokKategoriModel::tenantScopeOlmadan(fn () => StokKategoriModel::query()
            ->where('tanim_firma_kapsami', $kapsam)
            ->where('ad', $ad)
            ->whereKeyNot((int) $this->record->getKey())
            ->exists());
        if ($varAd) {
            throw ValidationException::withMessages(['ad' => 'Bu ad bu kapsamda zaten var.']);
        }

        $data['kod'] = $kod;
        $data['ad'] = $ad;
        $data['parent_id'] = StokKategoriKaynagi::parentKategoriIdHazirla(
            ($data['is_sabit'] ?? false) ? null : (int) $data['firma_id'],
            (bool) ($data['is_sabit'] ?? false),
            (int) ($data['parent_id'] ?? 0),
            (int) $this->record->getKey()
        );

        return $data;
    }
}
