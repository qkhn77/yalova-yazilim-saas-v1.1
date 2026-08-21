<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Araç takip kayıtları</x-slot>
        <x-slot name="description">Araç masraflarında plaka bazlı takip için önce araç kartını oluşturun. Yakıt litre, litre fiyatı ve kilometre bilgileri masraf girişinden ayrıca tutulabilir.</x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
