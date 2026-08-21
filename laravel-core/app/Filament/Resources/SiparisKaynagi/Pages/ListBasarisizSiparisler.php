<?php

namespace App\Filament\Resources\SiparisKaynagi\Pages;

use App\Filament\Resources\SiparisKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBasarisizSiparisler extends ListRecords
{
    protected static string $resource = SiparisKaynagi::class;

    protected static ?string $title = 'Başarısız Siparişler';

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();
        if (! $query) {
            return null;
        }

        return SiparisKaynagi::basarisizOdemeSiparisSorgusuUygula($query);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('tum_siparisler')
                ->label('Tüm Siparişler')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(SiparisKaynagi::getUrl('index')),
        ];
    }
}

