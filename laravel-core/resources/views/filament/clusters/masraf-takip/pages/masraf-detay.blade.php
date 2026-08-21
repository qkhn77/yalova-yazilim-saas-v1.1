@php
    /** @var \App\Filament\Clusters\MasrafTakip\Pages\MasrafDetaySayfasi $this */
    $masraf = $this->masraf;
    $para = strtoupper($masraf->para_birimi ?: 'TRY');
    $durumIptal = $masraf->durum === \App\Models\Muhasebe\Masraf::DURUM_IPTAL;
@endphp

<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-filament::section class="lg:col-span-2">
            <x-slot name="heading">Masraf bilgileri</x-slot>
            <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div><dt class="text-sm text-gray-500">Tarih</dt><dd class="mt-1 font-medium">{{ optional($masraf->tarih)->format('d.m.Y') }}</dd></div>
                <div><dt class="text-sm text-gray-500">Tutar</dt><dd class="mt-1 text-lg font-semibold text-danger-700 dark:text-danger-400">{{ number_format((float) $masraf->tutar, 2, ',', '.') }} {{ $para }}</dd></div>
                <div><dt class="text-sm text-gray-500">Ana kategori</dt><dd class="mt-1 font-medium">{{ $masraf->kategori?->ustKategori?->ad ?: '—' }}</dd></div>
                <div><dt class="text-sm text-gray-500">Masraf türü</dt><dd class="mt-1 font-medium">{{ $masraf->kategori?->ad ?: '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm text-gray-500">Açıklama</dt><dd class="mt-1">{{ $masraf->aciklama ?: '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm text-gray-500">Not</dt><dd class="mt-1 whitespace-pre-line">{{ $masraf->notlar ?: '—' }}</dd></div>
            </dl>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Bağlantılar</x-slot>
            <dl class="space-y-5">
                <div><dt class="text-sm text-gray-500">Proje</dt><dd class="mt-1 font-medium">{{ $masraf->isletmeProjesi?->ad ?: 'Projesiz' }}</dd>@if ($masraf->isletmeProjesi)<dd class="text-xs text-gray-500">{{ $masraf->isletmeProjesi->kod }}</dd>@endif</div>
                <div><dt class="text-sm text-gray-500">Durum</dt><dd class="mt-1"><x-filament::badge :color="$durumIptal ? 'danger' : 'success'">{{ $durumIptal ? 'İptal' : 'Aktif' }}</x-filament::badge></dd></div>
                <div><dt class="text-sm text-gray-500">Oluşturan</dt><dd class="mt-1">{{ $masraf->olusturanKullanici?->name ?: '—' }}</dd></div>
            </dl>
        </x-filament::section>

        @if ($masraf->belge_yolu)
            <x-filament::section class="lg:col-span-3">
                <x-slot name="heading">Belge</x-slot>
                <a href="{{ route('masraf.belge', ['masraf' => $masraf->getKey()]) }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline">{{ $masraf->belge_adi ?: 'Belgeyi görüntüle' }}</a>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
