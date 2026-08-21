<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMalzemeTuruTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimGuncelle;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMalzemeTuruTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMuhasebeMalzemeTuru extends EditRecord
{
    use MutatesStandartMuhasebeTanimGuncelle;

    protected static string $resource = MuhasebeMalzemeTuruTanimKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.tanimlar.muhasebe-malzeme-turu-tanim-kaynagi.pages.edit-muhasebe-malzeme-turu';

    protected static ?string $title = 'Malzeme türü düzenle';

    protected function getHeaderActions(): array
    {
        $detayModu = MuhasebeMalzemeTuruTanimKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hizli Form' : 'Detaylar')
                ->url(fn (): string => $detayModu
                    ? MuhasebeMalzemeTuruTanimKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (MuhasebeMalzemeTuruTanimKaynagi::detayModu()) {
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
