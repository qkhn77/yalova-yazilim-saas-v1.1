<x-filament-panels::page>
    @if($this->kpiKartlariYuklendi)
        @php
            $kpiKartlari = $this->faturaListeKpiKartlari();

            $renkSiniflari = [
                'primary' => 'fi-color fi-color-primary fi-text-color-600 dark:fi-text-color-400',
                'success' => 'fi-color fi-color-success fi-text-color-700 dark:fi-text-color-400',
                'warning' => 'fi-color fi-color-warning fi-text-color-700 dark:fi-text-color-400',
                'danger' => 'fi-color fi-color-danger fi-text-color-700 dark:fi-text-color-400',
            ];
        @endphp

        <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
            @foreach ($kpiKartlari as $kart)
                @php
                    $renk = $renkSiniflari[$kart['color']] ?? $renkSiniflari['primary'];
                @endphp

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $kart['label'] }}
                        </p>
                        <x-filament::icon
                            :icon="$kart['icon']"
                            class="h-5 w-5 shrink-0 {{ $renk }}"
                            aria-hidden="true"
                        />
                    </div>

                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">
                        {{ $kart['value'] }}
                    </p>

                    <div class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">
                        {{ $kart['description'] }}
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="mb-6 flex justify-end">
            <x-filament::button
                color="gray"
                icon="heroicon-m-chart-bar"
                wire:click="kpiKartlariniYukle"
                wire:loading.attr="disabled"
                wire:target="kpiKartlariniYukle"
            >
                Özetleri yükle
            </x-filament::button>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
