<x-filament-panels::page>
    <style>
        @media print {
            .fi-header, .fi-topbar, .fi-sidebar, .fi-breadcrumbs, .fi-header-actions, .fi-page-header-main-ctn { display: none !important; }
            .fi-main, .fi-page, .fi-page-content { padding: 0 !important; margin: 0 !important; }
            .fi-section, .fi-tabs, .fi-infolist { box-shadow: none !important; }
        }
    </style>
    <div class="ecommerce-web-cork-screen ecommerce-cork-screen ecommerce-cork-order" x-data x-on:siparis-yazdir.window="window.print()">
    @if (request()->boolean('detay'))
        {{ $this->infolist }}
    @else
        <dl class="grid max-w-2xl gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Müşteri</dt>
                <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $record->musteri_ad_soyad ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Durum</dt>
                <dd class="mt-1 text-sm text-gray-950 dark:text-white">
                    {{ \App\Models\Ecommerce\Siparis::durumEtiketleri()[$record->durum ?? ''] ?? ($record->durum ?: '—') }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Genel toplam</dt>
                <dd class="mt-1 text-sm text-gray-950 dark:text-white">
                    {{ number_format((float) $record->genel_toplam, 2, ',', '.') }} {{ $record->para_birimi ?: 'TRY' }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tarih</dt>
                <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $record->created_at?->format('d.m.Y H:i') ?? '—' }}</dd>
            </div>
        </dl>
    @endif
    </div>
</x-filament-panels::page>
