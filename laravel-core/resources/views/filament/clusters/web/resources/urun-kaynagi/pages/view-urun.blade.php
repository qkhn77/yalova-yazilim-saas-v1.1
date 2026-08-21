<x-filament-panels::page>
    <div class="ecommerce-web-cork-screen web-cork-screen web-cork-card p-4">
    @if (\App\Filament\Clusters\Web\Resources\UrunKaynagi::detayModu())
        {{ $this->infolist }}
    @else
        @php
            /** @var \App\Models\Muhasebe\StokKarti|null $urun */
            $urun = $this->record;
            $paraBirimi = strtoupper((string) ($urun?->para_birimi ?: 'TRY'));
        @endphp

        @if ($urun)
            <div class="space-y-1 text-sm text-gray-700 dark:text-gray-200">
                <div class="font-medium text-gray-950 dark:text-white">{{ $urun->ad }}</div>
                <div>{{ number_format((float) ($urun->satis_fiyati ?? 0), 2, ',', '.') }} {{ $paraBirimi }}</div>
            </div>
        @endif
    @endif
    </div>
</x-filament-panels::page>
