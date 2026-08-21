<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewRole extends ViewRecord
{
    protected static string $resource = RoleResource::class;

    protected static string $view = 'filament.resources.role-resource.pages.view-role';

    protected function getHeaderActions(): array
    {
        $detayModu = RoleResource::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_gorunum' : 'detaylar')
                ->label($detayModu ? 'Hızlı Görünüm' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-list-bullet')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? request()->fullUrlWithoutQuery('detay')
                    : request()->fullUrlWithQuery(['detay' => 1])),
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('ad')->label('Ad'),
            TextEntry::make('kod')->label('Kod'),
            TextEntry::make('aciklama')->label('Açıklama')->placeholder('-'),
            IconEntry::make('sistem_rolu_mu')->label('Sistem rolü')->boolean(),
        ]);
    }
}
