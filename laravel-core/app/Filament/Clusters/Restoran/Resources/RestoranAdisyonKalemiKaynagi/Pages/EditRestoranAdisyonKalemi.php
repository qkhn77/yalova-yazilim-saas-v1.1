<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKalemiKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKalemiKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRestoranAdisyonKalemi extends EditRecord
{
    protected static string $resource = RestoranAdisyonKalemiKaynagi::class;

    protected static string $view = 'filament.clusters.restoran.resources.restoran-adisyon-kalemi-kaynagi.pages.edit-restoran-adisyon-kalemi';

    protected function getHeaderActions(): array
    {
        $detayModu = RestoranAdisyonKalemiKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? RestoranAdisyonKalemiKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (RestoranAdisyonKalemiKaynagi::detayModu()) {
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
