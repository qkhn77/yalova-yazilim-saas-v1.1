<x-filament-panels::page>
    @if (\App\Filament\Resources\RoleResource::detayModu())
        {{ $this->infolist }}
    @endif
</x-filament-panels::page>
