<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelAvansKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelAvansKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPersonelAvans extends EditRecord
{
    protected static string $resource = PersonelAvansKaynagi::class;

    protected static string $view = 'filament.clusters.personel-takip.resources.personel-avans-kaynagi.pages.edit-personel-avans';

    protected function getHeaderActions(): array
    {
        $detayModu = PersonelAvansKaynagi::detayModu();

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
        if (PersonelAvansKaynagi::detayModu()) {
            return parent::getFormActions();
        }

        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }
}
