<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Kullanıcı';

    protected static string $view = 'filament.resources.user-resource.pages.view-user';

    protected function getHeaderActions(): array
    {
        return UserResource::detayModu() ? [
            Actions\EditAction::make(),
        ] : [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('name')->label('Ad'),
            TextEntry::make('kullanici_adi')->label('Kullanıcı adı'),
            TextEntry::make('email')->label('E-posta'),
            IconEntry::make('super_admin_mi')->label('Süper yönetici')->boolean(),
        ]);
    }
}
