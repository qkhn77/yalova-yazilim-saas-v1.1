<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Aylık fatura tanımları</x-slot>
        <x-slot name="description">Bu sayfa yalnızca tanım kartlarını tutar. Her ay tutar, tarih ve varsa gider faturası Masraflar ekranından kullanıcı tarafından girilir.</x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
