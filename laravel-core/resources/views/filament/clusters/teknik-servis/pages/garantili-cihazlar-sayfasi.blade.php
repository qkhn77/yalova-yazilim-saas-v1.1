@php
    $satirSayisi = $kayitlar->count();
    $baslangic = $satirSayisi > 0 ? (($kayitlar->currentPage() - 1) * $kayitlar->perPage()) + 1 : 0;
    $bitis = $satirSayisi > 0 ? $baslangic + $satirSayisi - 1 : 0;
    $hucre = 'whitespace-nowrap px-3 py-2 align-middle';
    $badge = 'ts-cork-status-badge inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset';
@endphp

<x-filament-panels::page>
    <div class="ts-cork-screen ts-cork-guarantee-list space-y-4">
        <div
            x-data="{
                filtersOpen: false,
                columnsOpen: false,
                columns: {
                    telefon: true,
                    cihaz: true,
                    bitis: true,
                    durum: true,
                },
            }"
            class="ts-cork-table-shell overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950"
        >
            <form method="GET" action="{{ url()->current() }}" class="ts-cork-toolbar border-b border-gray-200 dark:border-white/10">
                <div class="flex flex-col gap-3 px-4 py-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="shrink-0">
                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">Garantili cihazlar</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            @if ($satirSayisi > 0)
                                {{ $baslangic }}-{{ $bitis }} arası gösteriliyor
                            @else
                                Kayıt bulunamadı
                            @endif
                        </div>
                    </div>

                    <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                        <div class="ts-cork-search relative w-64 max-w-full shrink-0">
                            <label class="sr-only" for="garantili-cihazlar-ara">Ara</label>
                            <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
                            <input
                                id="garantili-cihazlar-ara"
                                type="search"
                                name="q"
                                value="{{ $arama }}"
                                placeholder="Ara"
                                class="block h-9 w-full rounded-md border-gray-200 pl-8 pr-2 text-sm shadow-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-100"
                            >
                        </div>

                        <button type="button" x-on:click="filtersOpen = ! filtersOpen" class="ts-cork-toggle inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-sm font-medium">
                            <x-filament::icon icon="heroicon-m-funnel" class="h-4 w-4" />
                            <span>Filtrele</span>
                            <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $aktifFiltreSayisi }}</span>
                        </button>

                        <div class="relative">
                            <button type="button" x-on:click="columnsOpen = ! columnsOpen" class="ts-cork-toggle inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-sm font-medium">
                                <x-filament::icon icon="heroicon-m-view-columns" class="h-4 w-4" />
                                <span>Sütunlar</span>
                            </button>
                            <div x-cloak x-show="columnsOpen" x-on:click.outside="columnsOpen = false" class="ts-cork-menu absolute right-0 z-20 mt-2 w-48 rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-lg dark:border-white/10 dark:bg-gray-950">
                                @foreach (['telefon' => 'Telefon', 'cihaz' => 'Cihaz', 'bitis' => 'Bitiş', 'durum' => 'Durum'] as $kolon => $etiket)
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

                <div x-cloak x-show="filtersOpen" class="ts-cork-filter-panel grid gap-2 border-t border-gray-200 px-4 py-3 dark:border-white/10 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="sr-only" for="garantili-cihazlar-durum">Garanti durumu</label>
                    <select id="garantili-cihazlar-durum" name="durum" class="h-9 rounded-md border-gray-200 text-sm shadow-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">Tüm durumlar</option>
                        <option value="aktif" @selected(request('durum') === 'aktif')>Aktif</option>
                        <option value="suresi-doldu" @selected(request('durum') === 'suresi-doldu')>Süresi doldu</option>
                        <option value="tarih-yok" @selected(request('durum') === 'tarih-yok')>Tarih yok</option>
                    </select>

                    <label class="sr-only" for="garantili-cihazlar-adet">Sayfa boyutu</label>
                    <select id="garantili-cihazlar-adet" name="adet" class="h-9 rounded-md border-gray-200 text-sm shadow-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                        @foreach ($sayfaBoyutlari as $adet)
                            <option value="{{ $adet }}" @selected($sayfaBoyutu === $adet)>{{ $adet }} kayıt</option>
                        @endforeach
                    </select>

                    <div class="flex gap-2">
                        <button type="submit" class="ts-cork-action ts-cork-action--primary rounded-md px-3 py-2 text-sm font-semibold">Uygula</button>
                        @if ($aktifFiltreVarMi)
                            <a href="{{ url()->current() }}" class="ts-cork-action ts-cork-action--neutral rounded-md px-3 py-2 text-sm font-semibold">Temizle</a>
                        @endif
                    </div>
                </div>
            </form>

            <div class="ts-cork-table-wrap overflow-x-auto">
                <table class="ts-cork-table w-full min-w-[980px] table-fixed divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Cihaz no</th>
                            <th class="px-3 py-2">Cari</th>
                            <th x-show="columns.telefon" class="px-3 py-2">Telefon</th>
                            <th x-show="columns.cihaz" class="px-3 py-2">Cihaz</th>
                            <th x-show="columns.bitis" class="px-3 py-2">Bitiş</th>
                            <th x-show="columns.durum" class="px-3 py-2">Durum</th>
                            <th class="px-3 py-2 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($kayitlar as $kayit)
                            @php($durum = $this->garantiDurumu($kayit))
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="{{ $hucre }} font-semibold text-gray-900 dark:text-gray-100">{{ $this->cihazNoMetni($kayit) }}</td>
                                <td class="{{ $hucre }} max-w-64 truncate text-gray-800 dark:text-gray-100" title="{{ $kayit->cari_adi ?: '-' }}">{{ $kayit->cari_adi ?: '-' }}</td>
                                <td x-show="columns.telefon" class="{{ $hucre }} text-gray-600 dark:text-gray-300">{{ $this->telefonMetni($kayit) }}</td>
                                <td x-show="columns.cihaz" class="{{ $hucre }} max-w-72 truncate text-gray-600 dark:text-gray-300" title="{{ $this->cihazMetni($kayit) }}">{{ $this->cihazMetni($kayit) }}</td>
                                <td x-show="columns.bitis" class="{{ $hucre }} text-gray-600 dark:text-gray-300">{{ $this->tarihMetni($kayit->garanti_bitis_tarihi) }}</td>
                                <td x-show="columns.durum" class="{{ $hucre }}">
                                    <span class="{{ $badge }} {{ $durum === 'Aktif' ? 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/30' : 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30' }}">
                                        {{ $durum }}
                                    </span>
                                </td>
                                <td class="{{ $hucre }} text-right">
                                    <a href="{{ $this->duzenleUrl($kayit) }}" class="ts-cork-icon-action inline-flex rounded-md p-2" aria-label="Düzenle" title="Düzenle">
                                        <x-filament::icon icon="heroicon-o-pencil-square" class="h-5 w-5" />
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="ts-cork-empty px-3 py-8 text-center text-gray-500 dark:text-gray-400">Kayıt bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ts-cork-pagination flex items-center justify-between gap-3">
            <a href="{{ $kayitlar->previousPageUrl() ?: '#' }}" class="ts-cork-page-link rounded-lg px-4 py-2 text-sm font-semibold ring-1 ring-gray-300 dark:ring-white/10 {{ $kayitlar->onFirstPage() ? 'pointer-events-none opacity-40' : 'hover:bg-gray-50 dark:hover:bg-white/5' }}">Önceki</a>
            <div class="text-xs text-gray-500 dark:text-gray-400">Sayfa {{ $kayitlar->currentPage() }}</div>
            <a href="{{ $kayitlar->nextPageUrl() ?: '#' }}" class="ts-cork-page-link rounded-lg px-4 py-2 text-sm font-semibold ring-1 ring-gray-300 dark:ring-white/10 {{ $kayitlar->hasMorePages() ? 'hover:bg-gray-50 dark:hover:bg-white/5' : 'pointer-events-none opacity-40' }}">Sonraki</a>
        </div>
    </div>
</x-filament-panels::page>
