<x-filament-panels::page>
    <x-filament::section
        heading="Transfer kayıtları"
        description="Her satır tek bir kaynaktan hedef depoya tamamlanan stok transferini gösterir."
    >
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
