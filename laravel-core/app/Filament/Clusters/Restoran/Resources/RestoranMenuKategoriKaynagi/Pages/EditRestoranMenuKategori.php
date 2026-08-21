<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranMenuKategoriKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranMenuKategoriKaynagi;
use App\Models\Restoran\RestoranMenuKategorisi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRestoranMenuKategori extends EditRecord
{
    protected static string $resource = RestoranMenuKategoriKaynagi::class;

    protected static string $view = 'filament.clusters.restoran.resources.restoran-menu-kategori-kaynagi.pages.edit-restoran-menu-kategori';

    protected function getHeaderActions(): array
    {
        $detayModu = RestoranMenuKategoriKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? RestoranMenuKategoriKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (RestoranMenuKategoriKaynagi::detayModu()) {
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
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (RestoranMenuKategoriKaynagi::detayModu()) {
            return $data;
        }

        $alanlar = [
            'firma_id',
            'sube_id',
            'ad',
            'slug',
            'aktif_mi',
            'siralama',
        ];

        $mevcut = RestoranMenuKategorisi::query()
            ->whereKey($this->record->getKey())
            ->first($alanlar);

        if (! $mevcut) {
            return $data;
        }

        $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));

        return array_replace($mevcutVeri, $data);
    }
}
