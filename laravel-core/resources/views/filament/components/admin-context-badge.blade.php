@php
    $aktifFirma = rescue(
        fn () => app(\App\Services\TenantContextService::class)->aktifFirma(),
        null,
        report: false,
    );

    $aktifFirmaAdi = $aktifFirma
        ? trim((string) ($aktifFirma->kisa_ad ?: $aktifFirma->ad ?: $aktifFirma->firma_kodu))
        : null;

@endphp

@if(filled($aktifFirmaAdi))
    <div
        class="saas-admin-context"
        title="Aktif firma: {{ $aktifFirmaAdi }}"
        aria-label="Aktif firma: {{ $aktifFirmaAdi }}"
    >
        <span class="saas-admin-context__icon" aria-hidden="true">
            <x-filament::icon icon="heroicon-o-building-office-2" />
        </span>
        <span class="saas-admin-context__copy">
            <span class="saas-admin-context__eyebrow">Aktif firma</span>
            <span class="saas-admin-context__name">{{ $aktifFirmaAdi }}</span>
        </span>
    </div>
@endif
