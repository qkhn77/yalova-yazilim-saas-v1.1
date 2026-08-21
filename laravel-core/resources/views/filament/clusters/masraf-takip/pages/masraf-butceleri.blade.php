<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Kategori bütçeleri</x-slot>
        <x-slot name="description">Bütçeler masraf türü ve dönem bazında tutulur. Gerçekleşen karşılaştırması Masraf Raporları sayfasında seçilen tarihlere göre yapılır.</x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
