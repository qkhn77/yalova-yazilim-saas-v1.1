@php
    $adminAssetExists = function (string $path): bool {
        $fullPath = public_path($path);

        return \Illuminate\Support\Facades\Cache::remember(
            'admin.asset-exists.'.md5($fullPath),
            600,
            fn (): bool => is_file($fullPath)
        );
    };
    $adminVersionedAsset = function (string $path) use ($adminAssetExists): string {
        $fullPath = public_path($path);
        $version = \Illuminate\Support\Facades\Cache::remember(
            'admin.asset-version.'.md5($fullPath),
            600,
            fn (): int => is_file($fullPath) ? (int) filemtime($fullPath) : time()
        );

        return asset($path).'?v='.$version;
    };
    $adminVersionedAssetWithPath = function (string $assetPath, string $fullPath): string {
        $version = \Illuminate\Support\Facades\Cache::remember(
            'admin.asset-version.'.md5($fullPath),
            600,
            fn (): int => is_file($fullPath) ? (int) filemtime($fullPath) : time()
        );

        return asset($assetPath).'?v='.$version;
    };
@endphp

{{--
    Fatura referans şablonunun CSS'i ekleme, düzenleme ve görüntüleme
    sayfalarının tamamında yüklenir. Böylece sayfa türüne göre tasarım
    farkı oluşmaz.
--}}
@if(str_contains(request()->path(), 'fatura-kaynagis'))
    <link rel="stylesheet" href="{{ $adminVersionedAsset('css/fatura-giden-invoice.css') }}">
@endif
@if((str_contains(request()->path(), 'teklif-yonetimi') || str_contains(request()->path(), 'teklifler')) && $adminAssetExists('css/teklif-yonetimi.css'))
    <link rel="stylesheet" href="{{ $adminVersionedAsset('css/teklif-yonetimi.css') }}">
@endif
@if(str_contains(request()->path(), 'profil') && $adminAssetExists('css/profil-duzenle.css'))
    <link rel="stylesheet" href="{{ $adminVersionedAsset('css/profil-duzenle.css') }}">
@endif
@if(str_contains(request()->path(), 'ayarlar/mesaj-merkezi') && $adminAssetExists('css/mesaj-merkezi.css'))
    <link rel="stylesheet" href="{{ $adminVersionedAsset('css/mesaj-merkezi.css') }}">
@endif
@php
$teknikServisCompactCssYukle = str_contains(request()->path(), 'teknik-servis/servis-kayitlari/olustur/')
        || (
            str_contains(request()->path(), 'teknik-servis/servis-kayitlari/')
            && str_contains(request()->path(), '/duzenle')
        )
        || str_contains(request()->path(), 'masraf-takip/masraflar');
@endphp
@if($teknikServisCompactCssYukle && $adminAssetExists('css/teknik-servis-create-compact.css'))
    <link rel="stylesheet" href="{{ $adminVersionedAsset('css/teknik-servis-create-compact.css') }}">
@endif

@php
    $hizliSatisCssYukle = str_contains(request()->path(), 'muhasebe/satis/hizli-satis');
@endphp
@if($hizliSatisCssYukle && $adminAssetExists('theme/yalovakamera/css/hizli-satis.css'))
    <link rel="stylesheet" href="{{ $adminVersionedAssetWithPath('theme/yalovakamera/css/hizli-satis.css', public_path('theme/yalovakamera/css/hizli-satis.css')) }}">
@endif

<link rel="stylesheet" href="{{ $adminVersionedAssetWithPath('theme/yalovakamera/css/admin-panel-bundle.css', public_path('theme/yalovakamera/css/admin-panel-bundle.css')) }}">
@vite('resources/css/filament/cork-admin-shell.css')
@vite('resources/css/filament/cork-admin-layouts.css')
@vite('resources/css/filament/cork-admin-forms.css')
@vite('resources/css/filament/cork-admin-tables.css')
@vite('resources/css/filament/cork-admin-widgets.css')
@vite('resources/css/filament/cork-admin-personnel.css')
@vite('resources/css/filament/cork-admin-ecommerce-web.css')
@vite('resources/css/filament/cork-admin-offers.css')
@vite('resources/css/filament/cork-admin-restaurant.css')
@vite('resources/css/filament/cork-admin-accounting.css')
@vite('resources/css/filament/cork-admin-technical-service.css')
@vite('resources/css/filament/cork-admin-actions.css')
@vite('resources/css/filament/cork-admin-overlays.css')
@vite('resources/css/filament/cork-admin-sales-operations.css')
<script defer src="{{ $adminVersionedAssetWithPath('theme/yalovakamera/js/admin-panel-bundle.js', public_path('theme/yalovakamera/js/admin-panel-bundle.js')) }}"></script>

{{-- Filament Async Alpine bileşenleri Livewire Alpine başlangıcından önce hazır olsun. --}}
<script type="module" data-navigate-once>
        import tableComponent from @js(\Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('table', 'filament/tables'));
        import selectComponent from @js(\Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('select', 'filament/forms'));
        import textareaComponent from @js(\Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('textarea', 'filament/forms'));

    document.addEventListener('alpine:init', () => {
            Alpine.data('table', tableComponent)
            Alpine.data('selectFormComponent', selectComponent)
            Alpine.data('textareaFormComponent', textareaComponent)
        }, { once: true })
</script>
