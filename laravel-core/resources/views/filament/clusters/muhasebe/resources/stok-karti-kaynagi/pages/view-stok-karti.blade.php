<x-filament-panels::page>
    @php
        /** @var \App\Models\Muhasebe\StokKarti|null $stok */
        $stok = $this->record;
    @endphp

    @if ($stok)
        {{ $this->infolist }}
    @endif
</x-filament-panels::page>
