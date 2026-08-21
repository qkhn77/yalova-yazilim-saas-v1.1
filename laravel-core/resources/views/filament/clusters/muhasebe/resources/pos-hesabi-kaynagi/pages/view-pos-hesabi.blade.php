<x-filament-panels::page>
    @php
        $record = $this->record;
        $relationManagers = $this->getRelationManagers();
        $combined = $this->hasCombinedRelationManagerTabsWithContent();
    @endphp
    <div class="yk-account-detail-page">
    <div class="yk-info-card-grid grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="yk-info-card yk-account-kpi-card min-w-0 rounded-xl p-3 sm:p-4"><x-heroicon-o-banknotes class="yk-info-card-icon" /><div class="text-xs text-gray-500">Güncel bakiye</div><div class="mt-1 text-xl font-semibold tabular-nums">{{ $this->aktifBakiyeMetni() }}</div><div class="mt-1 text-xs text-gray-500">Aktif POS hareketleri</div></div>
        <div class="yk-info-card yk-account-kpi-card min-w-0 rounded-xl p-3 sm:p-4"><x-heroicon-o-credit-card class="yk-info-card-icon" /><div class="text-xs text-gray-500">POS hesabı</div><div class="mt-1 text-base font-semibold">{{ $record->ad }}</div><div class="mt-1 text-xs text-gray-500">Kod: {{ $record->kod ?: '—' }}</div></div>
        <div class="yk-info-card yk-account-kpi-card min-w-0 rounded-xl p-3 sm:p-4"><x-heroicon-o-building-office-2 class="yk-info-card-icon" /><div class="text-xs text-gray-500">POS / sağlayıcı</div><div class="mt-1 text-base font-semibold">{{ $record->pos_tipi?->etiket() ?? '—' }}</div><div class="mt-1 text-xs text-gray-500">{{ $record->bankaVeyaSaglayiciGorunenAdi() }}</div></div>
        <div class="yk-info-card yk-account-kpi-card min-w-0 rounded-xl p-3 sm:p-4"><x-heroicon-o-check-badge class="yk-info-card-icon" /><div class="text-xs text-gray-500">Durum</div><div class="mt-1 text-base font-semibold">{{ $record->durum?->value === 'aktif' ? 'Aktif' : 'Pasif' }}</div><div class="mt-1 text-xs text-gray-500">Komisyon: %{{ $record->komisyon_orani ?? '0' }}</div></div>
    </div>
    @if ((! $combined) || (! count($relationManagers)))
        <details class="yk-account-details-toggle">
            <summary>Hesap bilgilerini göster/gizle</summary>
            <div class="yk-compact-account-details">{{ $this->infolist }}</div>
        </details>
    @endif
    @if (count($relationManagers))
        <x-filament-panels::resources.relation-managers :active-locale="isset($activeLocale) ? $activeLocale : null" :active-manager="$this->activeRelationManager ?? ($combined ? null : array_key_first($relationManagers))" :content-tab-label="$this->getContentTabLabel()" :content-tab-icon="$this->getContentTabIcon()" :content-tab-position="$this->getContentTabPosition()" :managers="$relationManagers" :owner-record="$record" :page-class="static::class">
            @if ($combined)
                <x-slot name="content"><details class="yk-account-details-toggle"><summary>Hesap bilgilerini göster/gizle</summary><div class="yk-compact-account-details">{{ $this->infolist }}</div></details></x-slot>
            @endif
        </x-filament-panels::resources.relation-managers>
    @endif
    </div>
</x-filament-panels::page>
