@php
    $satirSayisi = $kayitlar->count();
    $baslangic = $satirSayisi > 0 ? (($kayitlar->currentPage() - 1) * $kayitlar->perPage()) + 1 : 0;
    $bitis = $satirSayisi > 0 ? $baslangic + $satirSayisi - 1 : 0;
    $hucre = 'whitespace-nowrap px-3 py-2 align-middle';
    $badge = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset';
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex justify-end">
            <a href="{{ $resourceClass::getUrl('create') }}" class="rounded-md bg-primary-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-primary-500">
                Yeni ekle
            </a>
        </div>

        <div
            x-data="{
                filtersOpen: false,
                columnsOpen: false,
                columns: {
                    kod: false,
                    aktif: true,
                    sira: true,
                },
            }"
            class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950"
        >
            <form method="GET" action="{{ url()->current() }}" class="border-b border-gray-200 dark:border-white/10">
                <div class="flex flex-col gap-3 px-4 py-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="shrink-0">
                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $baslik }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            @if ($satirSayisi > 0)
                                {{ $baslangic }}-{{ $bitis }} arası gösteriliyor
                            @else
                                Kayıt bulunamadı
                            @endif
                        </div>
                    </div>

                    <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                        <div class="relative w-64 max-w-full shrink-0">
                            <label class="sr-only" for="tanim-listesi-ara">Ara</label>
                            <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
                            <input
                                id="tanim-listesi-ara"
                                type="search"
                                name="q"
                                value="{{ $arama }}"
                                placeholder="Ara"
                                class="block h-9 w-full rounded-md border-gray-200 pl-8 pr-2 text-sm shadow-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-100"
                            >
                        </div>

                        <button type="button" x-on:click="filtersOpen = ! filtersOpen" class="inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-sm font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5">
                            <x-filament::icon icon="heroicon-m-funnel" class="h-4 w-4" />
                            <span>Filtrele</span>
                            <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $aktifFiltreSayisi }}</span>
                        </button>

                        <div class="relative">
                            <button type="button" x-on:click="columnsOpen = ! columnsOpen" class="inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-sm font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5">
                                <x-filament::icon icon="heroicon-m-view-columns" class="h-4 w-4" />
                                <span>Sütunlar</span>
                            </button>
                            <div x-cloak x-show="columnsOpen" x-on:click.outside="columnsOpen = false" class="absolute right-0 z-20 mt-2 w-48 rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-lg dark:border-white/10 dark:bg-gray-950">
                                @foreach (['kod' => 'Kod', 'aktif' => 'Aktif', 'sira' => 'Sıra'] as $kolon => $etiket)
                                    <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">
                                        <input type="checkbox" x-model="columns.{{ $kolon }}" class="rounded border-gray-300 text-primary-600">
                                        <span>{{ $etiket }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <button type="submit" title="Ara" aria-label="Ara" class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-primary-600 text-white hover:bg-primary-500">
                            <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div x-cloak x-show="filtersOpen" class="grid gap-2 border-t border-gray-200 px-4 py-3 dark:border-white/10 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="sr-only" for="tanim-listesi-aktif">Durum</label>
                    <select id="tanim-listesi-aktif" name="aktif" class="h-9 rounded-md border-gray-200 text-sm shadow-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">Tüm durumlar</option>
                        <option value="1" @selected(request('aktif') === '1')>Aktif</option>
                        <option value="0" @selected(request('aktif') === '0')>Pasif</option>
                    </select>

                    <label class="sr-only" for="tanim-listesi-adet">Sayfa boyutu</label>
                    <select id="tanim-listesi-adet" name="adet" class="h-9 rounded-md border-gray-200 text-sm shadow-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                        @foreach ($sayfaBoyutlari as $adet)
                            <option value="{{ $adet }}" @selected($sayfaBoyutu === $adet)>{{ $adet }} kayıt</option>
                        @endforeach
                    </select>

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">Uygula</button>
                        @if ($aktifFiltreVarMi)
                            <a href="{{ url()->current() }}" class="rounded-md px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5">Temizle</a>
                        @endif
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] table-fixed divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Ad</th>
                            <th x-show="columns.kod" class="px-3 py-2">Kod</th>
                            <th x-show="columns.aktif" class="px-3 py-2">Aktif</th>
                            <th x-show="columns.sira" class="px-3 py-2">Sıra</th>
                            <th class="px-3 py-2 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($kayitlar as $kayit)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="{{ $hucre }} font-semibold text-gray-900 dark:text-gray-100">{{ $kayit->ad }}</td>
                                <td x-show="columns.kod" class="{{ $hucre }} text-gray-600 dark:text-gray-300">{{ $kayit->kod ?: '-' }}</td>
                                <td x-show="columns.aktif" class="{{ $hucre }}">
                                    <span class="{{ $badge }} {{ $kayit->aktif ? 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/30' : 'bg-gray-50 text-gray-600 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' }}">
                                        {{ $kayit->aktif ? 'Aktif' : 'Pasif' }}
                                    </span>
                                </td>
                                <td x-show="columns.sira" class="{{ $hucre }} text-gray-600 dark:text-gray-300">{{ $kayit->siralama }}</td>
                                <td class="{{ $hucre }} text-right">
                                    <a href="{{ $resourceClass::getUrl('edit', ['record' => $kayit->id]) }}" class="inline-flex rounded-md p-2 text-primary-600 hover:bg-primary-50 hover:text-primary-700 dark:hover:bg-primary-500/10" aria-label="Düzenle" title="Düzenle">
                                        <x-filament::icon icon="heroicon-o-pencil-square" class="h-5 w-5" />
                                    </a>
                                    <button
                                        type="button"
                                        wire:click="silmeModaliniAc({{ $kayit->id }})"
                                        class="inline-flex rounded-md p-2 text-danger-600 hover:bg-danger-50 hover:text-danger-700 dark:hover:bg-danger-500/10"
                                        aria-label="Sil"
                                        title="Sil"
                                    >
                                        <x-filament::icon icon="heroicon-o-trash" class="h-5 w-5" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">Kayıt bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div
            x-cloak
            x-show="$wire.silmeModalAcik"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4"
            x-on:keydown.escape="$wire.silmeIptal()"
        >
            <div x-on:click.outside="$wire.silmeIptal()" class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Tanımı sil</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Bağlı kayıtlar varsa aktarılacak hedef tanımı seçin.
                </p>

                @if ($silmeBagliKayitSayisi > 0)
                    <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                        {{ $silmeBagliKayitSayisi }} bağlı servis/arıza kaydı bulundu.
                    </p>
                @endif

                <label for="silme-hedefi" class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-200">Hedef tanım</label>
                <select id="silme-hedefi" wire:model="silmeHedefId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-gray-950 dark:text-gray-100">
                    <option value="">Hedef seçin</option>
                    @foreach ($silmeHedefleri as $hedefId => $hedefAd)
                        <option value="{{ $hedefId }}">{{ $hedefAd }}</option>
                    @endforeach
                </select>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="silmeIptal" class="rounded-md px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5">Vazgeç</button>
                    <button type="button" wire:click="kayitSil" @disabled($silmeBagliKayitSayisi > 0 && ! $silmeHedefId) class="rounded-md bg-danger-600 px-3 py-2 text-sm font-semibold text-white hover:bg-danger-500 disabled:cursor-not-allowed disabled:opacity-50">Aktar ve sil</button>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3">
            <a
                href="{{ $kayitlar->previousPageUrl() ?: '#' }}"
                class="rounded-lg px-4 py-2 text-sm font-semibold ring-1 ring-gray-300 dark:ring-white/10 {{ $kayitlar->onFirstPage() ? 'pointer-events-none opacity-40' : 'hover:bg-gray-50 dark:hover:bg-white/5' }}"
            >
                Önceki
            </a>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Sayfa {{ $kayitlar->currentPage() }}
            </div>
            <a
                href="{{ $kayitlar->nextPageUrl() ?: '#' }}"
                class="rounded-lg px-4 py-2 text-sm font-semibold ring-1 ring-gray-300 dark:ring-white/10 {{ $kayitlar->hasMorePages() ? 'hover:bg-gray-50 dark:hover:bg-white/5' : 'pointer-events-none opacity-40' }}"
            >
                Sonraki
            </a>
        </div>
    </div>
</x-filament-panels::page>
