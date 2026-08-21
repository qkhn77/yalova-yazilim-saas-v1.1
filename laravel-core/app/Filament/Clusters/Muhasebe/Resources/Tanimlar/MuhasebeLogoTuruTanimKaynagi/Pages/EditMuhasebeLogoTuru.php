<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeLogoTuruTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimGuncelle;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeLogoTuruTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMuhasebeLogoTuru extends EditRecord
{
    use MutatesStandartMuhasebeTanimGuncelle;

    protected static string $resource = MuhasebeLogoTuruTanimKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.tanimlar.muhasebe-logo-turu-tanim-kaynagi.pages.edit-muhasebe-logo-turu';

    protected static ?string $title = 'Logo türü düzenle';

    protected function getHeaderActions(): array
    {
        $detayModu = MuhasebeLogoTuruTanimKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hizli Form' : 'Detaylar')
                ->url(fn (): string => $detayModu
                    ? MuhasebeLogoTuruTanimKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (MuhasebeLogoTuruTanimKaynagi::detayModu()) {
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
