<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\SubeKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\SubeKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSube extends EditRecord
{
    protected static string $resource = SubeKaynagi::class;

    protected static string $view = 'filament.clusters.personel-takip.resources.sube-kaynagi.pages.edit-sube';

    protected function getHeaderActions(): array
    {
        $detayModu = SubeKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hizli Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? SubeKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (SubeKaynagi::detayModu()) {
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
