<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="text-xl font-semibold">{{ $baslik }}</h2><p class="text-sm text-gray-500">Görev ve randevularınız.</p></div>
        <div class="flex gap-1 rounded-lg border p-1">
            @foreach (['gun' => 'Gün', 'hafta' => 'Hafta', 'ay' => 'Ay'] as $kod => $etiket)
                <a href="{{ request()->fullUrlWithQuery(['gorunum' => $kod]) }}" class="rounded px-3 py-1 text-sm {{ $gorunum === $kod ? 'bg-primary-600 text-white' : 'text-gray-600' }}">{{ $etiket }}</a>
            @endforeach
        </div>
        <div class="flex gap-2"><x-filament::button tag="a" :href="$gorevUrl">Görev ekle</x-filament::button><x-filament::button tag="a" :href="$randevuUrl" color="gray">Randevu ekle</x-filament::button></div>
    </div>
    <x-filament::section>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($gorevler as $gorev)
                @php($kayit = $gorev['kayit'])
                <a href="{{ \App\Filament\Clusters\Sekreter\Resources\GorevKaynagi::getUrl('edit', ['record' => $kayit]) }}" class="block rounded-lg border p-3 transition hover:border-primary-500"><div class="flex items-center justify-between"><span class="font-medium">{{ $kayit->baslik }}</span><span class="text-xs text-gray-500">{{ $gorev['zaman']->format('d.m.Y') }}</span></div><div class="mt-1 text-sm text-gray-500">Görev · {{ $kayit->durum === 'tamamlandi' ? 'Tamamlandı' : 'Bekliyor' }}</div></a>
            @empty
                <p class="text-sm text-gray-500">Bu ay için görev yok.</p>
            @endforelse
            @foreach ($randevular as $randevu)
                @php($kayit = $randevu['kayit'])
                <a href="{{ \App\Filament\Clusters\Sekreter\Resources\RandevuKaynagi::getUrl('edit', ['record' => $kayit]) }}" class="block rounded-lg border border-primary-200 bg-primary-50 p-3 transition hover:border-primary-500 dark:border-primary-800 dark:bg-primary-950"><div class="flex items-center justify-between"><span class="font-medium">{{ $kayit->baslik }}</span><span class="text-xs">{{ $randevu['zaman']->format('d.m.Y') }}</span></div><div class="mt-1 text-sm">Randevu · {{ $kayit->baslangic_saati?->format('H:i') }}</div></a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
