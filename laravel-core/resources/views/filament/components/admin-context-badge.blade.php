@php
    $aktifFirma = rescue(
        fn () => app(\App\Services\TenantContextService::class)->aktifFirma(),
        null,
        report: false,
    );

    $aktifFirmaAdi = $aktifFirma
        ? trim((string) ($aktifFirma->kisa_ad ?: $aktifFirma->ad ?: $aktifFirma->firma_kodu))
        : null;

    $aktifKullanici = rescue(
        fn () => \Illuminate\Support\Facades\Auth::user(),
        null,
        report: false,
    );

    $aktifKullaniciAdi = $aktifKullanici
        ? trim((string) ($aktifKullanici->ad_soyad ?: $aktifKullanici->kullanici_adi))
        : null;

@endphp

@if(filled($aktifFirmaAdi))
    <div
        class="saas-admin-context"
        title="{{ $aktifFirmaAdi }} — {{ $aktifKullaniciAdi }}"
        aria-label="Firma: {{ $aktifFirmaAdi }}; Kullanıcı: {{ $aktifKullaniciAdi }}"
    >
        <span class="saas-admin-context__icon" aria-hidden="true">
            <x-filament::icon icon="heroicon-o-building-office-2" />
        </span>
        <span class="saas-admin-context__copy">
            <span class="saas-admin-context__eyebrow">{{ $aktifFirmaAdi }}</span>
            <span class="saas-admin-context__name">{{ $aktifKullaniciAdi }}</span>
        </span>
    </div>
@endif
