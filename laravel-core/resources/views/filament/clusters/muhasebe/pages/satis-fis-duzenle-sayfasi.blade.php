<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-3">
        <x-filament::section class="lg:col-span-1" heading="Sablonlar">
            <div class="mb-4">
                <x-filament::button
                    color="gray"
                    icon="heroicon-o-plus"
                    wire:click="yeniSablon"
                >
                    Yeni Sablon
                </x-filament::button>
                @if ($this->ayarGuncelleYetkisiVarMi())
                    <x-filament::button
                        color="warning"
                        icon="heroicon-o-arrow-path"
                        class="ml-2"
                        wire:click="demoSablonlariGeriYukle"
                    >
                        Demo Sablonlari Geri Yukle
                    </x-filament::button>
                @endif
            </div>

            <div class="space-y-3">
                @forelse ($this->sablonlar() as $sablon)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $sablon->ad }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ strtoupper((string) $sablon->sayfa_tipi) }} | {{ $sablon->kod }}
                                </div>
                            </div>
                            @if ($sablon->varsayilan_mi)
                                <span class="rounded bg-success-100 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-900/40 dark:text-success-300">
                                    Varsayilan
                                </span>
                            @endif
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-filament::button size="xs" color="gray" wire:click="duzenle({{ (int) $sablon->id }})">
                                Duzenle
                            </x-filament::button>
                            @if (! $sablon->varsayilan_mi)
                                <x-filament::button size="xs" color="success" wire:click="varsayilanYap({{ (int) $sablon->id }})">
                                    Varsayilan Yap
                                </x-filament::button>
                            @endif
                            @if ($this->ayarGuncelleYetkisiVarMi() && ! $sablon->varsayilan_mi)
                                <x-filament::button size="xs" color="danger" wire:click="sil({{ (int) $sablon->id }})">
                                    Sil
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 p-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Kayit bulunamadi.
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section class="lg:col-span-2" heading="Sablon Duzenleyici">
            <form wire:submit="kaydet">
                {{ $this->form }}

                @if ($this->ayarGuncelleYetkisiVarMi())
                    <div class="mt-6 flex justify-start">
                        <x-filament::button type="submit" icon="heroicon-o-check">
                            Kaydet
                        </x-filament::button>
                    </div>
                @endif
            </form>
        </x-filament::section>

        <x-filament::section class="lg:col-span-3" heading="Yazdirma On Izleme">
            <style>
                {!! $this->onizlemeCss() !!}

                .satis-fis-onizleme .fis-kapsayici {
                    box-sizing: border-box !important;
                    padding-right: {{ $this->onizlemeSagBosluk() }} !important;
                    max-width: calc(100% - {{ $this->onizlemeSagBosluk() }}) !important;
                    overflow-wrap: anywhere;
                    word-break: break-word;
                }
            </style>

            <div class="satis-fis-onizleme rounded-xl border border-gray-300 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/20">
                <div
                    class="mx-auto rounded-lg border border-gray-300 bg-white p-4 text-black shadow-sm dark:border-gray-700"
                    style="{{ $this->onizlemeKapsayiciStili() }}"
                >
                    {!! $this->onizlemeHtmlCikti() !!}
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
