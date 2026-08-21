@php
    $rapor = $this->raporYuklendi ? $this->rapor() : [
        'kartlar' => [],
        'tablolar' => [],
    ];
    $kartlar = $rapor['kartlar'] ?? [];
    $tablolar = $rapor['tablolar'] ?? [];
@endphp

<x-filament-panels::page>
    <div class="ts-cork-screen ts-cork-report" wire:init="raporuYukle">
    <form wire:submit.prevent="raporuGuncelle" class="ts-cork-toolbar mb-6 grid gap-3 md:grid-cols-3">
        <x-filament::input.wrapper>
            <x-filament::input type="date" wire:model.defer="baslangicTarihi" />
        </x-filament::input.wrapper>

        <x-filament::input.wrapper>
            <x-filament::input type="date" wire:model.defer="bitisTarihi" />
        </x-filament::input.wrapper>

        <x-filament::button type="submit" icon="heroicon-o-arrow-path" wire:loading.attr="disabled" wire:target="raporuGuncelle,raporuYukle">
            <span wire:loading.remove wire:target="raporuGuncelle,raporuYukle">Raporu güncelle</span>
            <span wire:loading wire:target="raporuGuncelle,raporuYukle">Yükleniyor...</span>
        </x-filament::button>
    </form>

    @if(! empty($rapor['uyari']))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
            {{ $rapor['uyari'] }}
        </div>
    @endif

    @if(! $this->raporYuklendi)
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @for($i = 0; $i < 4; $i++)
                <div class="ts-cork-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="h-4 w-24 rounded bg-gray-200 dark:bg-gray-800"></div>
                    <div class="mt-3 h-8 w-32 rounded bg-gray-100 dark:bg-gray-800"></div>
                    <div class="mt-3 h-3 w-40 rounded bg-gray-100 dark:bg-gray-800"></div>
                </div>
            @endfor
        </div>
    @else
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @forelse($kartlar as $kart)
            <div class="ts-cork-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $kart['etiket'] ?? '-' }}</div>
                <div class="mt-1 text-2xl font-semibold tracking-normal text-gray-950 dark:text-white">{{ $kart['deger'] ?? '-' }}</div>
                @if(! empty($kart['alt']))
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $kart['alt'] }}</div>
                @endif
            </div>
        @empty
            <div class="ts-cork-card rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-500 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                Rapor kartı için veri bulunamadı.
            </div>
        @endforelse
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        @foreach($tablolar as $tablo)
            @php
                $kolonlar = $tablo['kolonlar'] ?? [];
                $satirlar = $tablo['satirlar'] ?? [];
            @endphp

            <div class="ts-cork-card overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $tablo['baslik'] ?? 'Rapor' }}</h2>
                    @if(! empty($tablo['aciklama']))
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $tablo['aciklama'] }}</p>
                    @endif
                </div>

                <div class="ts-cork-table-wrap overflow-x-auto">
                    <table class="ts-cork-table w-full min-w-[640px] divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase text-gray-500 dark:bg-gray-950 dark:text-gray-400">
                            <tr>
                                @foreach($kolonlar as $kolon)
                                    <th class="px-4 py-3 {{ ($kolon['align'] ?? '') === 'right' ? 'text-right' : '' }}">{{ $kolon['label'] ?? '' }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($satirlar as $satir)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5">
                                    @foreach($kolonlar as $kolon)
                                        @php
                                            $key = $kolon['key'] ?? '';
                                            $deger = $satir[$key] ?? '-';
                                            $linkVer = $loop->first && ! empty($satir['_url']);
                                        @endphp
                                        <td class="whitespace-nowrap px-4 py-3 {{ ($kolon['align'] ?? '') === 'right' ? 'text-right font-medium text-gray-950 dark:text-gray-100' : 'text-gray-700 dark:text-gray-200' }}">
                                            @if($linkVer)
                                                <a href="{{ $satir['_url'] }}" class="font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                                    {{ $deger }}
                                                </a>
                                            @else
                                                {{ $deger }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ max(1, count($kolonlar)) }}" class="ts-cork-empty px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        {{ $tablo['bos'] ?? 'Kayıt yok.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
    @endif
    </div>
</x-filament-panels::page>
