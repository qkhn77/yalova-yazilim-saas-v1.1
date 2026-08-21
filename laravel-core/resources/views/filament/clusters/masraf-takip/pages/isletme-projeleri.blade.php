<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">İşletme proje kartları</x-slot>
        <x-slot name="description">Projeler masraf girişinden seçilebilir. Gerçekleşen tutar yalnızca aynı para birimindeki aktif masraflardan hesaplanır.</x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
