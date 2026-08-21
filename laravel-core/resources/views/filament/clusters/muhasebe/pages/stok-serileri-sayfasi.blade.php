<x-filament-panels::page>
    <style>
        @media print {
            .stok-seri-filtreleri, .stok-seri-yazdirma-dugmesi, .fi-header, .fi-topbar, .fi-sidebar, .fi-breadcrumbs { display: none !important; }
            .fi-main, .fi-page, .fi-page-content { padding: 0 !important; margin: 0 !important; }
            .stok-seri-yazdirma-raporu { box-shadow: none !important; border: 0 !important; }
            .stok-seri-yazdirma-raporu table { font-size: 10px; }
        }
    </style>
    <div class="space-y-6">
        <div class="stok-seri-filtreleri">{{ $this->form }}</div>
        <div class="stok-seri-yazdirma-dugmesi flex justify-end">
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700">
                <x-filament::icon icon="heroicon-o-printer" class="h-4 w-4" />
                Yazdır
            </button>
        </div>

        <x-filament::section class="stok-seri-yazdirma-raporu" heading="Seri No Barkodu listesi" description="Ürünlerin seri numarası ve barkod kayıtlarını tek ekranda görüntüleyin.">
            <div class="mb-3 text-sm text-gray-500">Satılmış seri kayıtlarında son satış fiyatı ve seri bazlı gerçekleşen kâr gösterilir.</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left">
                        <th class="px-3 py-2">Ürün</th><th class="px-3 py-2">Seri No Barkodu</th><th class="px-3 py-2">Depo</th><th class="px-3 py-2">Durum</th><th class="px-3 py-2">Satış / Kâr</th><th class="px-3 py-2">Garanti bitişi</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($this->seriler as $seri)
                            <tr class="border-b last:border-0">
                                <td class="px-3 py-2"><a href="{{ $this->seriKartiUrl((int) $seri->stok_id) }}" class="font-medium text-primary-600 hover:underline">{{ $seri->stokKarti?->ad ?? '-' }}</a><div class="text-xs text-gray-500">{{ $seri->stokKarti?->kod }}</div></td>
                                <td class="px-3 py-2"><div class="font-medium">{{ $seri->seri_no }}</div><div class="text-xs text-gray-500">Barkod: {{ $seri->barkod ?: '—' }}</div></td>
                                <td class="px-3 py-2">{{ $seri->depo?->ad ?? 'Genel stok' }}</td>
                                <td class="px-3 py-2">
                                    @if ($seri->durum === 'stokta') <x-filament::badge color="success">Stokta</x-filament::badge>
                                    @elseif ($seri->durum === 'satildi') <x-filament::badge color="gray">Satıldı</x-filament::badge>
                                    @else <x-filament::badge color="warning">Çıkış yapıldı</x-filament::badge> @endif
                                </td>
                                <td class="px-3 py-2">
                                    @if ($this->seriSatisFiyati($seri) !== null)
                                        <div>{{ $this->seriSatisHareketi($seri)?->tarih?->format('d.m.Y H:i') }}</div>
                                        <div class="text-xs text-gray-500">Satış: {{ number_format($this->seriSatisFiyati($seri), 2, ',', '.') }} TRY</div>
                                        <div class="text-xs {{ $this->seriGerceklesenKari($seri) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">Kâr: {{ number_format($this->seriGerceklesenKari($seri), 2, ',', '.') }} TRY</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $seri->garanti_bitis_tarihi?->format('d.m.Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">Seçilen ölçütlere göre Seri No Barkodu bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
