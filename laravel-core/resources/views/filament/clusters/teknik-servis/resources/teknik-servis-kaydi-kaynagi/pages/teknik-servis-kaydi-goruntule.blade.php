<x-filament-panels::page>
    <div class="ts-cork-screen ts-cork-service-detail">
        @if (request()->boolean('detay'))
            <div class="ts-cork-document-shell">{{ $this->infolist }}</div>
        @else
            <dl class="ts-cork-card grid max-w-2xl gap-4 p-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Fiş no</dt>
                <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $record->fis_no ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Servis durumu</dt>
                <dd class="mt-1 text-sm text-gray-950 dark:text-white">
                    {{ (int) ($record->servis_durumu_id ?? 0) ?: '—' }}
                </dd>
            </div>
            </dl>
        @endif
    </div>
</x-filament-panels::page>
