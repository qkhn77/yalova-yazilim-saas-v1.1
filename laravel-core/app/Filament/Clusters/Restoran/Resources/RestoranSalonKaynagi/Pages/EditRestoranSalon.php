<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranSalonKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranSalonKaynagi;
use App\Models\Restoran\RestoranSalonu;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRestoranSalon extends EditRecord
{
    protected static string $resource = RestoranSalonKaynagi::class;

    protected static string $view = 'filament.clusters.restoran.resources.restoran-salon-kaynagi.pages.edit-restoran-salon';

    protected function getHeaderActions(): array
    {
        $detayModu = RestoranSalonKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? RestoranSalonKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (RestoranSalonKaynagi::detayModu()) {
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
        if (RestoranSalonKaynagi::detayModu()) {
            return $data;
        }

        $alanlar = [
            'firma_id',
            'sube_id',
            'ad',
            'kod',
            'aktif_mi',
            'siralama',
        ];

        $mevcut = RestoranSalonu::query()
            ->whereKey($this->record->getKey())
            ->first($alanlar);

        if (! $mevcut) {
            return $data;
        }

        $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));

        return array_replace($mevcutVeri, $data);
    }
}
