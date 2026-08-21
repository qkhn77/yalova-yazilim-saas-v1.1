<x-filament-panels::page>
    <div class="flex flex-wrap justify-end gap-3">
        <x-filament::button tag="a" :href="$gorevUrl" icon="heroicon-o-plus">Görev ekle</x-filament::button>
        <x-filament::button tag="a" :href="$randevuUrl" color="gray" icon="heroicon-o-calendar-days">Randevu ekle</x-filament::button>
        <x-filament::button tag="a" :href="$notUrl" color="gray" icon="heroicon-o-document-text">Not ekle</x-filament::button>
    </div>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($kartlar as $kart)
            <x-filament::section :icon="match ($kart['renk']) { 'danger' => 'heroicon-o-exclamation-circle', 'success' => 'heroicon-o-check-circle', 'warning' => 'heroicon-o-clock', default => 'heroicon-o-calendar-days' }">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $kart['baslik'] }}</div>
                <div class="mt-1 text-3xl font-semibold">{{ $kart['deger'] }}</div>
            </x-filament::section>
        @endforeach
    </div>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-filament::section heading="Bugün">
            <div class="space-y-2">
                @forelse ($gorevler as $gorev)
                    <a href="{{ \App\Filament\Clusters\Sekreter\Resources\GorevKaynagi::getUrl('edit', ['record' => $gorev]) }}" class="block rounded-lg border p-3 transition hover:border-primary-500"><div class="font-medium">{{ $gorev->baslik }}</div><div class="text-sm text-gray-500">Görev · {{ $gorev->saat?->format('H:i') ?? 'Saat yok' }}</div></a>
                @empty
                    <p class="text-sm text-gray-500">Bugün için bekleyen görev yok.</p>
                @endforelse
                @foreach ($randevular as $randevu)
                    <a href="{{ \App\Filament\Clusters\Sekreter\Resources\RandevuKaynagi::getUrl('edit', ['record' => $randevu]) }}" class="block rounded-lg border p-3 transition hover:border-primary-500"><div class="font-medium">{{ $randevu->baslik }}</div><div class="text-sm text-gray-500">Randevu · {{ $randevu->baslangic_saati?->format('H:i') }}</div></a>
                @endforeach
            </div>
        </x-filament::section>
        <x-filament::section heading="Yaklaşanlar">
            <div class="space-y-2">
                @forelse ($yaklasan as $gorev)
                    <a href="{{ \App\Filament\Clusters\Sekreter\Resources\GorevKaynagi::getUrl('edit', ['record' => $gorev]) }}" class="block rounded-lg border p-3 transition hover:border-primary-500"><div class="font-medium">{{ $gorev->baslik }}</div><div class="text-sm text-gray-500">{{ $gorev->tarih?->format('d.m.Y') }}</div></a>
                @empty
                    <p class="text-sm text-gray-500">Yaklaşan görev yok.</p>
                @endforelse
                @foreach ($yaklasanRandevular as $randevu)
                    <a href="{{ \App\Filament\Clusters\Sekreter\Resources\RandevuKaynagi::getUrl('edit', ['record' => $randevu]) }}" class="block rounded-lg border border-primary-200 bg-primary-50 p-3 transition hover:border-primary-500 dark:border-primary-800 dark:bg-primary-950"><div class="font-medium">{{ $randevu->baslik }}</div><div class="text-sm">{{ $randevu->baslangic_tarihi?->format('d.m.Y') }} · {{ $randevu->baslangic_saati?->format('H:i') }}</div></a>
                @endforeach
            </div>
        </x-filament::section>
    </div>
    @if ($entegrasyonlar !== [])
        <x-filament::section heading="Yaklaşan önemli tarihler">
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($entegrasyonlar as $kayit)
                    <div class="rounded-lg border p-3"><div class="text-sm font-medium">{{ $kayit['baslik'] }}</div><div class="text-sm text-gray-500">{{ $kayit['metin'] }}</div></div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
