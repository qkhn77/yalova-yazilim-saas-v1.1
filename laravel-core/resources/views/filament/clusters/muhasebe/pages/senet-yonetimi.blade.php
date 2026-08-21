<x-filament-panels::page>
    <div class="muhasebe-cork-screen muhasebe-senet-yonetimi">
    <div class="senet-ozet-row mb-4 flex flex-col gap-3">
        <div class="yk-info-card-grid senet-ozet-grid grid w-full min-w-0 gap-3">
            @foreach ($this->senetOzetleri() as $ozet)
                <div class="yk-info-card muhasebe-cork-kpi-card rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $ozet['etiket'] }}</div>
                        <x-filament::icon :icon="$ozet['icon']" class="h-8 w-8 shrink-0 rounded-md bg-primary-50 p-1.5 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300" aria-hidden="true" />
                    </div>
                    <div class="mt-1 text-xl font-semibold leading-6 text-gray-900 dark:text-gray-100">{{ $ozet['adet'] }}</div>
                    <div class="mt-1 text-xs leading-4 text-gray-500 dark:text-gray-400">{{ $ozet['aciklama'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="senet-actions flex flex-wrap items-center justify-end gap-2 pt-1">
            <x-filament::actions :actions="$this->getCachedHeaderActions()" />
        </div>
    </div>

    {{ $this->table }}
    </div>
</x-filament-panels::page>
