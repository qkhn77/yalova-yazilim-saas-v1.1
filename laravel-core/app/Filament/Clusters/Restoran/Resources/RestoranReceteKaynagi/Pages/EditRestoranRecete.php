<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranReceteKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranReceteKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRestoranRecete extends EditRecord
{
    protected static string $resource = RestoranReceteKaynagi::class;

    protected static string $view = 'filament.clusters.restoran.resources.restoran-recete-kaynagi.pages.edit-restoran-recete';

    protected function getHeaderActions(): array
    {
        $detayModu = RestoranReceteKaynagi::detayModu();

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
        if (RestoranReceteKaynagi::detayModu()) {
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
