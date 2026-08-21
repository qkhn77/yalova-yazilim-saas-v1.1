@php
    $turu = $senet->turu?->value ?? $senet->turu;
    $durum = $senet->durum?->value ?? $senet->durum;
    $durumEtiketi = match ($durum) {
        'portfoyde' => 'Portföyde',
        'verildi' => 'Verildi',
        'odendi' => 'Ödendi',
        'iade_edildi' => 'İade edildi',
        'imha_edildi' => 'İmha edildi',
        'iptal' => 'İptal',
        default => '—',
    };
    $kapanmaEtiketi = match ($senet->kapanma_sekli) {
        'odendi_iade' => 'Ödendi, geri verildi',
        'odendi_imha' => 'Ödendi, imha edildi',
        'iade_edildi' => 'İade edildi',
        'imha_edildi' => 'İmha edildi',
        default => '—',
    };
@endphp

<div class="space-y-5 text-sm">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs text-gray-500">Tür</div>
            <div class="mt-1 font-medium">{{ $turu === 'alinan' ? 'Alınan senet' : 'Verilen senet' }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs text-gray-500">Senet no</div>
            <div class="mt-1 font-medium">{{ $senet->senet_no }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs text-gray-500">Tutar</div>
            <div class="mt-1 font-medium">{{ number_format((float) $senet->tutar, 2, ',', '.') }} {{ strtoupper((string) ($senet->para_birimi ?: 'TRY')) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs text-gray-500">Durum / vade</div>
            <div class="mt-1 font-medium">{{ $durumEtiketi }} · {{ $vadeDurumu }}</div>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <h3 class="mb-3 font-semibold">Taraflar ve belge bilgileri</h3>
            <dl class="grid gap-3 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-gray-500">Senedi veren cari</dt>
                    <dd class="mt-1">{{ $senet->girisHareketi?->cari?->ad ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Verildiği cari</dt>
                    <dd class="mt-1">{{ $senet->cikisHareketi?->cari?->ad ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Düzenleme yeri</dt>
                    <dd class="mt-1">{{ $senet->duzenleme_yeri ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Ödeme yeri</dt>
                    <dd class="mt-1">{{ $senet->odeme_yeri ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Avalist / kefil</dt>
                    <dd class="mt-1">{{ $senet->avalist_adi ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Vade tarihi</dt>
                    <dd class="mt-1">{{ optional($senet->vade_tarihi)->format('d.m.Y') ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <h3 class="mb-3 font-semibold">Kayıt ve kapanış bilgileri</h3>
            <dl class="grid gap-3 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-gray-500">Düzenleme tarihi</dt>
                    <dd class="mt-1">{{ optional($senet->duzenleme_tarihi)->format('d.m.Y') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Kapanış şekli</dt>
                    <dd class="mt-1">{{ $kapanmaEtiketi }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Oluşturan kullanıcı</dt>
                    <dd class="mt-1">{{ $senet->olusturanKullanici?->name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Kapatma yapan</dt>
                    <dd class="mt-1">{{ $senet->kapatmaKullanici?->name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Kapanış tarihi</dt>
                    <dd class="mt-1">{{ optional($senet->kapanma_tarihi)->format('d.m.Y H:i') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Sorumlu kullanıcı</dt>
                    <dd class="mt-1">{{ $senet->sorumluKullanici?->name ?: '—' }}</dd>
                </div>
            </dl>
            @if ($senet->aciklama || $senet->kapatma_aciklama)
                <div class="mt-4 space-y-2 border-t border-gray-200 pt-3 dark:border-gray-700">
                    @if ($senet->aciklama)
                        <div><span class="text-xs text-gray-500">Açıklama:</span> {{ $senet->aciklama }}</div>
                    @endif
                    @if ($senet->kapatma_aciklama)
                        <div><span class="text-xs text-gray-500">Kapanış açıklaması:</span> {{ $senet->kapatma_aciklama }}</div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if ($onGorselUrl || $arkaGorselUrl)
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <h3 class="mb-3 font-semibold">Belge görselleri</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                @if ($onGorselUrl)
                    <a href="{{ $onGorselUrl }}" target="_blank" rel="noopener" class="group">
                        <div class="mb-2 text-xs text-gray-500">Ön yüz</div>
                        <img src="{{ $onGorselUrl }}" alt="Senet ön yüz" class="h-56 w-full rounded-lg border border-gray-200 object-contain transition group-hover:border-primary-500 dark:border-gray-700" loading="lazy">
                    </a>
                @endif
                @if ($arkaGorselUrl)
                    <a href="{{ $arkaGorselUrl }}" target="_blank" rel="noopener" class="group">
                        <div class="mb-2 text-xs text-gray-500">Arka yüz</div>
                        <img src="{{ $arkaGorselUrl }}" alt="Senet arka yüz" class="h-56 w-full rounded-lg border border-gray-200 object-contain transition group-hover:border-primary-500 dark:border-gray-700" loading="lazy">
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
