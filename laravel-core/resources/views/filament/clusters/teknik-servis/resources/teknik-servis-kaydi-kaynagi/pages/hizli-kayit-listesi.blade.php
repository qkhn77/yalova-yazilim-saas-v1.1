@php
    use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;

    $satirSayisi = $kayitlar->count();
    $baslangic = $satirSayisi > 0 ? (($kayitlar->currentPage() - 1) * $kayitlar->perPage()) + 1 : 0;
    $bitis = $satirSayisi > 0 ? $baslangic + $satirSayisi - 1 : 0;
    $hucre = 'whitespace-nowrap px-3 py-2 align-middle';
    $badge = 'ts-cork-status-badge inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset';
@endphp

<x-filament-panels::page>
    <div class="ts-cork-screen ts-cork-service-list space-y-4">
        <div class="ts-cork-page-actions flex justify-end">
            <div class="flex flex-wrap gap-1.5">
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('create_arizali') }}" class="ts-cork-action ts-cork-action--primary rounded-md px-2.5 py-1.5 text-xs font-semibold">
                    Arızalı cihaz
                </a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('create_dis_servis') }}" class="ts-cork-action ts-cork-action--neutral rounded-md px-2.5 py-1.5 text-xs font-semibold">
                    Dış servis
                </a>
                <a href="{{ TeknikServisKaydiKaynagi::getUrl('create_bakim') }}" class="ts-cork-action ts-cork-action--neutral rounded-md px-2.5 py-1.5 text-xs font-semibold">
                    Bakım
                </a>
            </div>
        </div>

        <div
            x-data="{
                filtersOpen: false,
                columnsOpen: false,
                columns: {
                    telefon: true,
                    cihaz: true,
                    marka: true,
                    tip: true,
                    durum: true,
                    oncelik: true,
                    toplam: true,
                    odeme: true,
                    teslim: true,
                },
            }"
            class="ts-cork-table-shell overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950"
        >
            <form method="GET" action="{{ url()->current() }}" class="ts-cork-toolbar border-b border-gray-200 dark:border-white/10">
                <input type="hidden" name="sirala" value="{{ $sirala }}">
                <input type="hidden" name="yon" value="{{ $yon }}">

                <div class="flex flex-col gap-3 px-4 py-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="shrink-0">
                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">Servis kayıtları</div>
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
                            <label class="sr-only" for="servis-kayitlari-ara">Ara</label>
                            <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
                            <input
                                id="servis-kayitlari-ara"
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
                                @foreach ([
                                    'telefon' => 'Telefon',
                                    'cihaz' => 'Cihaz',
                                    'marka' => 'Marka',
                                    'tip' => 'Tip',
                                    'durum' => 'Durum',
                                    'oncelik' => 'Öncelik',
                                    'toplam' => 'Toplam',
                                    'odeme' => 'Ödeme',
                                    'teslim' => 'Teslim',
                                ] as $kolon => $etiket)
                                    <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">
                                        <input type="checkbox" x-model="columns.{{ $kolon }}" class="rounded border-gray-300 text-primary-600">
                                        <span>{{ $etiket }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <button type="submit" title="Ara" aria-label="Ara" class="ts-cork-action ts-cork-action--primary inline-flex h-9 w-9 items-center justify-center">
                            <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div x-cloak x-show="filtersOpen" class="ts-cork-filter-panel grid gap-2 border-t border-gray-200 px-4 py-3 dark:border-white/10 sm:grid-cols-2 lg:grid-cols-5">
                    <label class="sr-only" for="servis-kayitlari-tip">Servis tipi</label>
                    <select id="servis-kayitlari-tip" name="tip" class="h-9 rounded-md border-gray-200 text-sm shadow-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">Tüm tipler</option>
                        @foreach ($servisTipiFiltreleri as $deger => $etiket)
                            <option value="{{ $deger }}" @selected(request('tip') === $deger)>{{ $etiket }}</option>
                        @endforeach
                    </select>

                    <label class="sr-only" for="servis-kayitlari-oncelik">Öncelik</label>
                    <select id="servis-kayitlari-oncelik" name="oncelik" class="h-9 rounded-md border-gray-200 text-sm shadow-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">Tüm öncelikler</option>
                        @foreach ($oncelikFiltreleri as $deger => $etiket)
                            <option value="{{ $deger }}" @selected(request('oncelik') === $deger)>{{ $etiket }}</option>
                        @endforeach
                    </select>

                    <label class="sr-only" for="servis-kayitlari-odeme">Ödeme</label>
                    <select id="servis-kayitlari-odeme" name="odeme" class="h-9 rounded-md border-gray-200 text-sm shadow-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">Tüm ödemeler</option>
                        @foreach ($odemeFiltreleri as $deger => $etiket)
                            <option value="{{ $deger }}" @selected(request('odeme') === $deger)>{{ $etiket }}</option>
                        @endforeach
                    </select>

                    <label class="sr-only" for="servis-kayitlari-adet">Sayfa boyutu</label>
                    <select id="servis-kayitlari-adet" name="adet" class="h-9 rounded-md border-gray-200 text-sm shadow-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
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
                <table class="ts-cork-table w-full min-w-[1180px] table-fixed divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2 text-left">İşlem</th>
                            <th class="px-3 py-2"><a class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100" href="{{ $this->siralamaUrl('fis') }}">Fiş <span>{{ $this->siralamaIkonu('fis') }}</span></a></th>
                            <th class="px-3 py-2"><a class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100" href="{{ $this->siralamaUrl('kabul') }}">Kabul <span>{{ $this->siralamaIkonu('kabul') }}</span></a></th>
                            <th class="px-3 py-2"><a class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100" href="{{ $this->siralamaUrl('cari') }}">Cari <span>{{ $this->siralamaIkonu('cari') }}</span></a></th>
                            <th x-show="columns.telefon" class="px-3 py-2"><a class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100" href="{{ $this->siralamaUrl('telefon') }}">Telefon <span>{{ $this->siralamaIkonu('telefon') }}</span></a></th>
                            <th x-show="columns.cihaz" class="px-3 py-2"><a class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100" href="{{ $this->siralamaUrl('cihaz') }}">Cihaz <span>{{ $this->siralamaIkonu('cihaz') }}</span></a></th>
                            <th x-show="columns.marka" class="px-3 py-2"><a class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100" href="{{ $this->siralamaUrl('marka') }}">Marka <span>{{ $this->siralamaIkonu('marka') }}</span></a></th>
                            <th x-show="columns.tip" class="px-3 py-2"><a class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100" href="{{ $this->siralamaUrl('tip') }}">Tip <span>{{ $this->siralamaIkonu('tip') }}</span></a></th>
                            <th x-show="columns.durum" class="px-3 py-2"><a class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100" href="{{ $this->siralamaUrl('durum') }}">Durum <span>{{ $this->siralamaIkonu('durum') }}</span></a></th>
                            <th x-show="columns.oncelik" class="px-3 py-2"><a class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100" href="{{ $this->siralamaUrl('oncelik') }}">Öncelik <span>{{ $this->siralamaIkonu('oncelik') }}</span></a></th>
                            <th x-show="columns.toplam" class="px-3 py-2 text-right"><a class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100" href="{{ $this->siralamaUrl('toplam') }}">Toplam <span>{{ $this->siralamaIkonu('toplam') }}</span></a></th>
                            <th x-show="columns.odeme" class="px-3 py-2">Ödeme</th>
                            <th x-show="columns.teslim" class="px-3 py-2"><a class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100" href="{{ $this->siralamaUrl('teslim') }}">Teslim <span>{{ $this->siralamaIkonu('teslim') }}</span></a></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($kayitlar as $kayit)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="{{ $hucre }} text-left">
                                    <a href="{{ TeknikServisKaydiKaynagi::getUrl('edit', ['record' => $kayit->id]) }}" class="ts-cork-icon-action inline-flex rounded-md p-2" aria-label="Düzenle" title="Düzenle">
                                        <x-filament::icon icon="heroicon-o-pencil-square" class="h-5 w-5" />
                                    </a>
                                </td>
                                <td class="{{ $hucre }} font-semibold text-gray-900 dark:text-gray-100">{{ $kayit->fis_no }}</td>
                                <td class="{{ $hucre }} text-gray-600 dark:text-gray-300">{{ optional($kayit->kabul_tarihi)->format('d.m.Y H:i') ?: '-' }}</td>
                                <td class="{{ $hucre }} max-w-64 truncate text-gray-800 dark:text-gray-100" title="{{ $kayit->cari_adi ?: '-' }}">{{ $kayit->cari_adi ?: '-' }}</td>
                                <td x-show="columns.telefon" class="{{ $hucre }} text-gray-600 dark:text-gray-300">{{ $kayit->musteri_tel ?: '-' }}</td>
                                <td x-show="columns.cihaz" class="{{ $hucre }} max-w-48 truncate text-gray-600 dark:text-gray-300" title="{{ $kayit->cihaz_adi ?: '-' }}">{{ $kayit->cihaz_adi ?: '-' }}</td>
                                <td x-show="columns.marka" class="{{ $hucre }} max-w-40 truncate text-gray-600 dark:text-gray-300" title="{{ $kayit->marka_adi ?: '-' }}">{{ $kayit->marka_adi ?: '-' }}</td>
                                <td x-show="columns.tip" class="{{ $hucre }} text-gray-600 dark:text-gray-300">{{ $this->servisTipiEtiketi($kayit->servis_tipi) }}</td>
                                <td x-show="columns.durum" class="{{ $hucre }} text-gray-600 dark:text-gray-300">{{ $kayit->servis_durumu_adi ?: '-' }}</td>
                                <td x-show="columns.oncelik" class="{{ $hucre }}"><span class="{{ $badge }} {{ $this->oncelikRengi($kayit->oncelik) }}">{{ $this->oncelikEtiketi($kayit->oncelik) }}</span></td>
                                <td x-show="columns.toplam" class="{{ $hucre }} text-right font-medium text-gray-800 dark:text-gray-100">{{ $this->paraFormatla($kayit->toplam_tutar) }}</td>
                                <td x-show="columns.odeme" class="{{ $hucre }}"><span class="{{ $badge }} {{ $this->odemeRengi($kayit->odeme_durumu) }}">{{ $this->odemeEtiketi($kayit->odeme_durumu) }}</span></td>
                                <td x-show="columns.teslim" class="{{ $hucre }} text-gray-600 dark:text-gray-300">{{ optional($kayit->teslim_tarihi)->format('d.m.Y H:i') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="ts-cork-empty px-3 py-10 text-center text-gray-500 dark:text-gray-400">Kayıt bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ts-cork-pagination flex items-center justify-between gap-3">
            <a
                href="{{ $kayitlar->previousPageUrl() ?: '#' }}"
                class="ts-cork-page-link rounded-lg px-4 py-2 text-sm font-semibold ring-1 ring-gray-300 dark:ring-white/10 {{ $kayitlar->onFirstPage() ? 'pointer-events-none opacity-40' : 'hover:bg-gray-50 dark:hover:bg-white/5' }}"
            >
                Önceki
            </a>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Sayfa {{ $kayitlar->currentPage() }}
            </div>
            <a
                href="{{ $kayitlar->nextPageUrl() ?: '#' }}"
                class="ts-cork-page-link rounded-lg px-4 py-2 text-sm font-semibold ring-1 ring-gray-300 dark:ring-white/10 {{ $kayitlar->hasMorePages() ? 'hover:bg-gray-50 dark:hover:bg-white/5' : 'pointer-events-none opacity-40' }}"
            >
                Sonraki
            </a>
        </div>
    </div>
</x-filament-panels::page>
