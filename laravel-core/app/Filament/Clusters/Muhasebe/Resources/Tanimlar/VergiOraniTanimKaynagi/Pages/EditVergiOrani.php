<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\VergiOraniTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimGuncelle;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\VergiOraniTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVergiOrani extends EditRecord
{
    use MutatesStandartMuhasebeTanimGuncelle;

    protected static string $resource = VergiOraniTanimKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.tanimlar.vergi-orani-tanim-kaynagi.pages.edit-vergi-orani';

    protected static ?string $title = 'Vergi oranı düzenle';
    protected function getHeaderActions(): array
    {
        if (VergiOraniTanimKaynagi::detayModu()) {
            return parent::getHeaderActions();
        }

        return [];
    }

    protected function getFormActions(): array
    {
        if (VergiOraniTanimKaynagi::detayModu()) {
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
