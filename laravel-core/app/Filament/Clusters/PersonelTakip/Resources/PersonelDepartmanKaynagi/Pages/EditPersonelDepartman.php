<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelDepartmanKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelDepartmanKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPersonelDepartman extends EditRecord
{
    protected static string $resource = PersonelDepartmanKaynagi::class;

    protected function getHeaderActions(): array
    {
        $detayModu = PersonelDepartmanKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? PersonelDepartmanKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (PersonelDepartmanKaynagi::detayModu()) {
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
