<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaSablonuKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaSablonuKaynagi;
use App\Models\Personel\PersonelVardiyaSablonu;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPersonelVardiyaSablonu extends EditRecord
{
    protected static string $resource = PersonelVardiyaSablonuKaynagi::class;

    protected function getHeaderActions(): array
    {
        $detayModu = PersonelVardiyaSablonuKaynagi::detayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? PersonelVardiyaSablonuKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (PersonelVardiyaSablonuKaynagi::detayModu()) {
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
        if (PersonelVardiyaSablonuKaynagi::detayModu()) {
            return $data;
        }

        $alanlar = [
            'firma_id',
            'sube_id',
            'ad',
            'baslangic_saati',
            'bitis_saati',
            'mola_dakika',
            'renk',
            'aktif_mi',
        ];

        $mevcut = PersonelVardiyaSablonu::query()
            ->whereKey($this->record->getKey())
            ->first($alanlar);

        if (! $mevcut) {
            return $data;
        }

        $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));

        return array_replace($mevcutVeri, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
