<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeStokModeliTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimGuncelle;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeStokModeliTanimKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMuhasebeStokModeli extends EditRecord
{
    use MutatesStandartMuhasebeTanimGuncelle;

    protected static string $resource = MuhasebeStokModeliTanimKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.tanimlar.muhasebe-stok-modeli-tanim-kaynagi.pages.edit-muhasebe-stok-modeli';

    protected static ?string $title = 'Ürün modeli düzenle';

    protected function getHeaderActions(): array
    {
        $detayModu = request()->boolean('detay');

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? MuhasebeStokModeliTanimKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
        ];
    }

    protected function getFormActions(): array
    {
        if (request()->boolean('detay')) {
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
