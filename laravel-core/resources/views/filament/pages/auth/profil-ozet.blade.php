@php
    $kullanici = filament()->auth()->user();
    $profilFotografi = (string) ($kullanici?->profil_fotografi ?? '');
    $avatarUrl = $profilFotografi !== ''
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($profilFotografi)
        : null;
    $ad = (string) ($kullanici?->ad_soyad ?: $kullanici?->name ?: 'Kullanıcı');
    $email = (string) ($kullanici?->email ?? '');
    $telefon = (string) ($kullanici?->telefon ?? '');
    $kullaniciAdi = (string) ($kullanici?->kullanici_adi ?? '');
    $profilFotografiAlaniAcik = (bool) ($this->profilFotografiAlaniAcik ?? false);
    $sonGiris = $kullanici?->son_giris_tarihi
        ? $kullanici->son_giris_tarihi->timezone(config('app.timezone'))->format('d.m.Y H:i')
        : null;
    $basHarfler = collect(explode(' ', trim($ad)))
        ->filter()
        ->take(2)
        ->map(fn (string $parca): string => mb_strtoupper(mb_substr($parca, 0, 1, 'UTF-8'), 'UTF-8'))
        ->implode('');
@endphp

<div class="yk-profile-summary overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="yk-profile-summary__body p-4">
        <div class="yk-profile-summary__identity flex min-w-0 flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4">
                <div class="yk-profile-summary__avatar flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-50 text-xl font-semibold text-primary-700 ring-1 ring-primary-100 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/20">
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $ad }}" class="h-full w-full object-cover">
                    @else
                        <span>{{ $basHarfler ?: 'K' }}</span>
                    @endif
                </div>

                <div class="yk-profile-summary__text min-w-0">
                    <div class="yk-profile-summary__name text-base font-semibold text-gray-950 dark:text-white">{{ $ad }}</div>
                    <div class="yk-profile-summary__meta mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($email !== '')
                        <span>{{ $email }}</span>
                    @endif

                    @if ($telefon !== '')
                        <span>{{ $telefon }}</span>
                    @endif

                    @if ($kullaniciAdi !== '')
                        <span>Kullanıcı adı: {{ $kullaniciAdi }}</span>
                    @endif

                    <span>Son giriş: {{ $sonGiris ?: 'Kayıt yok' }}</span>
                    </div>
                </div>
            </div>

            <x-filament::button
                type="button"
                size="sm"
                color="gray"
                icon="{{ $profilFotografiAlaniAcik ? 'heroicon-o-eye-slash' : 'heroicon-o-camera' }}"
                wire:click="profilFotografiAlaniniDegistir"
                wire:loading.attr="disabled"
                wire:target="profilFotografiAlaniniDegistir"
            >
                {{ $profilFotografiAlaniAcik ? 'Fotoğraf Alanını Gizle' : 'Fotoğrafı Değiştir' }}
            </x-filament::button>
        </div>
    </div>

</div>
