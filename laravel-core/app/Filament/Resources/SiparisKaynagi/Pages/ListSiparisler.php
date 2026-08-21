<?php

namespace App\Filament\Resources\SiparisKaynagi\Pages;

use App\Filament\Resources\SiparisKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSiparisler extends ListRecords
{
    protected static string $resource = SiparisKaynagi::class;

    protected static ?string $title = 'Siparişler';

    protected function getHeaderWidgets(): array
    {
        // Geçici: KPI widget bileşen adı eşleşme hatası nedeniyle canlıda 500 üretiyor.
        // Sayfa stabilitesi için header widget kapatıldı; KPI paneli ayrı stabilizasyon adımında geri açılacak.
        return [];
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();
        if (! $query) {
            return null;
        }

        return SiparisKaynagi::basarisizOdemeDisiSiparisSorgusuUygula($query);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('basarisiz_siparisler')
                ->label('Başarısız Siparişler')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->url(SiparisKaynagi::getUrl('failed')),
        ];
    }
}

