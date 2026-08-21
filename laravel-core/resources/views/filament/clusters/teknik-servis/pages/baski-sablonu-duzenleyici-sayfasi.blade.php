<x-filament-panels::page>
    <div class="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Şablonlar</h2>
                <button type="button" wire:click="yeniSablon" class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900">
                    Yeni
                </button>
            </div>

            <div class="space-y-2">
                @forelse ($this->sablonlar() as $sablon)
                    <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $sablon->ad }}</div>
                                <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ strtoupper((string) $sablon->sayfa_tipi) }} | {{ $sablon->kod }}</div>
                            </div>
                            @if ($sablon->varsayilan_mi)
                                <span class="rounded bg-success-100 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-900/40 dark:text-success-300">Varsayılan</span>
                            @endif
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" wire:click="duzenle({{ (int) $sablon->id }})" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                                Düzenle
                            </button>
                            <button type="button" wire:click="kopyala({{ (int) $sablon->id }})" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                                Kopyala
                            </button>
                            @if (! $sablon->varsayilan_mi)
                                <button type="button" wire:click="varsayilanYap({{ (int) $sablon->id }})" class="rounded-md border border-success-300 px-2 py-1 text-xs font-semibold text-success-700 hover:bg-success-50 dark:border-success-700 dark:text-success-300 dark:hover:bg-success-950/30">
                                    Varsayılan
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-md border border-dashed border-gray-300 p-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Kayıt bulunamadı.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            @if ($duzenleyiciAcik)
                <h2 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">Şablon Düzenleyici</h2>
                <form wire:submit="kaydet">
                    {{ $this->form }}

                    <button type="submit" class="mt-4 rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                        Kaydet
                    </button>
                </form>
            @else
                <div class="rounded-md border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Düzenlemek için soldan şablon seçin.
                </div>
            @endif
        </section>

        @if ($duzenleyiciAcik && trim((string) data_get($this->data, 'sablon_html', '')) !== '')
            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 lg:col-span-2">
                <style>
                    {!! $this->onizlemeCss() !!}

                    .teknik-servis-sablon-onizleme .servis-kapsayici,
                    .teknik-servis-sablon-onizleme .mini-kapsayici {
                        box-sizing: border-box !important;
                        padding-right: {{ $this->onizlemeSagBosluk() }} !important;
                        max-width: calc(100% - {{ $this->onizlemeSagBosluk() }}) !important;
                        overflow-wrap: anywhere;
                        word-break: break-word;
                    }
                </style>

                <div class="teknik-servis-sablon-onizleme rounded-lg border border-gray-300 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/20">
                    <div class="mx-auto rounded-lg border border-gray-300 bg-white p-4 text-black shadow-sm dark:border-gray-700" style="{{ $this->onizlemeKapsayiciStili() }}">
                        {!! $this->onizlemeHtmlCikti() !!}
                    </div>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
