<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaUreticiTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimGuncelle;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaUreticiTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMuhasebeMarkaUretici extends EditRecord
{
    use MutatesStandartMuhasebeTanimGuncelle;

    protected static string $resource = MuhasebeMarkaUreticiTanimKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.tanimlar.muhasebe-marka-uretici-tanim-kaynagi.pages.edit-muhasebe-marka-uretici';

    protected static ?string $title = 'Marka üretici düzenle';

    protected function getHeaderActions(): array
    {
        $detayModu = MuhasebeMarkaUreticiTanimKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hizli Form' : 'Detaylar')
                ->url(fn (): string => $detayModu
                    ? MuhasebeMarkaUreticiTanimKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (MuhasebeMarkaUreticiTanimKaynagi::detayModu()) {
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
