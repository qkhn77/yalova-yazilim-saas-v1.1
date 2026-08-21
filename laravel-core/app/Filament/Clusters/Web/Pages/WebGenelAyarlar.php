<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\Setting;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebGenelAyarlar extends Page implements HasForms
{
    use InteractsWithForms;
    use \Filament\Pages\Concerns\InteractsWithFormActions;

    private const BOLUMLER = [
        'url' => 'URL',
        'seo' => 'SEO',
        'tema' => 'Tema Ayarları',
        'logolar' => 'Logolar',
        'footer' => 'Footer',
    ];

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Genel Ayarlar';

    protected static ?string $slug = 'web-ayarlar/web-genel-ayarlar';

    protected static string $view = 'filament.clusters.web.pages.web-genel-ayarlar';

    public ?array $data = [];

    public string $aktifBolum = 'url';

    private static function defaultSiteUrl(): string
    {
        return rtrim(config('app.url'), '/');
    }

    private static function defaultAdminUrl(): string
    {
        $path = Setting::get('admin_path', 'admin');

        return self::defaultSiteUrl() . '/' . ltrim($path, '/');
    }

    public function mount(): void
    {
        $this->data = $this->bolumVarsayilanlari($this->aktifBolum);
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        $defaultSiteUrl = self::defaultSiteUrl();
        $defaultAdminPath = 'admin';
        $defaultAdminUrl = self::defaultAdminUrl();

        return $form
            ->schema(match ($this->aktifBolum) {
                'url' => [
                Forms\Components\Section::make('URL Ayarları')
                    ->description('Site ve admin panel adresleri. Boş bırakılırsa varsayılan değerler kullanılır.')
                    ->icon('heroicon-o-link')
                    ->schema([
                        Forms\Components\TextInput::make('site_url')
                            ->label('Site URL')
                            ->default($defaultSiteUrl)
                            ->placeholder($defaultSiteUrl)
                            ->url()
                            ->helperText('Varsayılan: ' . $defaultSiteUrl)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('admin_path')
                            ->label('Admin panel giriş URL')
                            ->default($defaultAdminPath)
                            ->placeholder($defaultAdminPath)
                            ->helperText('Varsayılan: ' . $defaultAdminPath . ' -> Giriş adresi: ' . $defaultAdminUrl)
                            ->maxLength(64)
                            ->regex('/^[a-z0-9_-]+$/i'),
                    ])
                    ->columns(1),

                ],
                'seo' => [
                Forms\Components\Section::make('SEO')
                    ->description('Google ve sosyal medya önizlemelerinde kullanılacak temel SEO alanları.')
                    ->icon('heroicon-o-magnifying-glass')
                    ->schema([
                        Forms\Components\TextInput::make('site_title')
                            ->label('Genel site başlığı')
                            ->placeholder(config('app.name'))
                            ->helperText('Özel SEO tanımı olmayan sayfalarda varsayılan title olarak kullanılır.')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('meta_description')
                            ->label('Genel meta açıklama')
                            ->rows(3)
                            ->placeholder('Kısa site açıklaması...')
                            ->helperText('Özel SEO tanımı olmayan sayfalarda varsayılan description olarak kullanılır.')
                            ->maxLength(500),
                        Forms\Components\Textarea::make('meta_keywords')
                            ->label('Genel meta anahtar kelimeler')
                            ->rows(2)
                            ->placeholder('kelime1, kelime2, kelime3')
                            ->helperText('Virgülle ayırarak girin.')
                            ->maxLength(500),
                        Forms\Components\TextInput::make('site_author')
                            ->label('Meta author')
                            ->placeholder('Yalova Kamera Sistemleri')
                            ->helperText('Head içindeki author meta alanında kullanılır.')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('default_meta_robots')
                            ->label('Genel robots meta')
                            ->placeholder('index, follow')
                            ->helperText('Örnek: index, follow')
                            ->maxLength(100),
                        Forms\Components\FileUpload::make('default_og_image')
                            ->label('Genel sosyal paylaşım görseli')
                            ->image()
                            ->disk('public')
                            ->directory('settings/logos')
                            ->visibility('public')
                            ->imagePreviewHeight(80)
                            ->helperText('Open Graph ve Twitter kartlarında varsayılan görsel olarak kullanılır.'),
                        Forms\Components\TextInput::make('homepage_meta_title')
                            ->label('Ana sayfa SEO başlığı')
                            ->placeholder('Yalova Kamera Sistemleri | Güvenlik Kamerası Kurulum')
                            ->helperText('Ana sayfanın title alanı burada yönetilir.')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('homepage_meta_description')
                            ->label('Ana sayfa meta açıklama')
                            ->rows(3)
                            ->placeholder('Ana sayfa için Google açıklaması...')
                            ->helperText('Ana sayfanın description alanı burada yönetilir.')
                            ->maxLength(500),
                        Forms\Components\Textarea::make('homepage_meta_keywords')
                            ->label('Ana sayfa meta anahtar kelimeler')
                            ->rows(2)
                            ->placeholder('yalova kamera kurulumu, güvenlik kamerası, alarm sistemi')
                            ->helperText('Ana sayfa için özel keyword alanı.')
                            ->maxLength(500),
                        Forms\Components\TextInput::make('homepage_og_title')
                            ->label('Ana sayfa sosyal paylaşım başlığı')
                            ->placeholder('Yalova Kamera Sistemleri | Güvenlik Kamerası Kurulum')
                            ->helperText('Open Graph ve Twitter title için kullanılır.')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('homepage_og_description')
                            ->label('Ana sayfa sosyal paylaşım açıklaması')
                            ->rows(3)
                            ->placeholder('Sosyal medyada görünen kısa açıklama...')
                            ->helperText('Open Graph ve Twitter description için kullanılır.')
                            ->maxLength(500),
                        Forms\Components\FileUpload::make('homepage_og_image')
                            ->label('Ana sayfa sosyal paylaşım görseli')
                            ->image()
                            ->disk('public')
                            ->directory('settings/logos')
                            ->visibility('public')
                            ->imagePreviewHeight(80)
                            ->helperText('Ana sayfa sosyal paylaşım görseli için özel alan.'),
                    ])
                    ->columns(1),

                ],
                'tema' => [
                Forms\Components\Section::make('Ön yüz teması')
                    ->description('Web sitesinin renklerini, tipografisini, menü düzenini ve genel görünümünü seçin.')
                    ->icon('heroicon-o-swatch')
                    ->schema([
                        Forms\Components\Select::make('front_theme')
                            ->label('Aktif tema')
                            ->options(fn (): array => collect(app(\App\Services\FrontThemeService::class)->themes())
                                ->mapWithKeys(fn (array $theme, string $key): array => [$key => $theme['name']])
                                ->all())
                            ->default(\App\Services\FrontThemeService::DEFAULT_THEME)
                            ->required()
                            ->native(false)
                            ->helperText('Tema değişikliği kaydedildikten sonra web sitesinde tüm ziyaretçiler için uygulanır.'),
                        Forms\Components\Placeholder::make('tema_aciklamalari')
                            ->label('Kullanılabilir temalar')
                            ->content(fn (): string => collect(app(\App\Services\FrontThemeService::class)->themes())
                                ->map(fn (array $theme): string => $theme['name'] . ': ' . $theme['description'])
                                ->implode(' | ')),
                    ])
                    ->columns(1),

                ],
                'logolar' => [
                Forms\Components\Section::make('Logolar')
                    ->description('Header, footer, favicon ve loader logoları.')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        Forms\Components\FileUpload::make('site_logo')
                            ->label('Header logo')
                            ->image()
                            ->disk('public')
                            ->directory('settings/logos')
                            ->visibility('public')
                            ->imagePreviewHeight(80)
                            ->helperText('Varsayılan logo: theme/yalovakamera/images/yalova_kamera.png'),
                        Forms\Components\TextInput::make('site_logo_filename')
                            ->label('Header logo dosya adı')
                            ->placeholder('header-logo.png')
                            ->helperText('Uzantı yazabilirsin. Kaydet sırasında dosya adı buna göre güncellenir.')
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('footer_logo')
                            ->label('Footer logo')
                            ->image()
                            ->disk('public')
                            ->directory('settings/logos')
                            ->visibility('public')
                            ->imagePreviewHeight(60)
                            ->helperText('Varsayılan logo: theme/yalovakamera/images/footer-logo.svg'),
                        Forms\Components\TextInput::make('footer_logo_filename')
                            ->label('Footer logo dosya adı')
                            ->placeholder('footer-logo.svg')
                            ->helperText('Uzantı yazabilirsin. Kaydet sırasında dosya adı buna göre güncellenir.')
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('favicon_logo')
                            ->label('Favicon')
                            ->image()
                            ->disk('public')
                            ->directory('settings/logos')
                            ->visibility('public')
                            ->imagePreviewHeight(48)
                            ->helperText('Tarayıcı sekmesindeki ikon. Varsayılan: theme/yalovakamera/images/favicon.png'),
                        Forms\Components\TextInput::make('favicon_logo_filename')
                            ->label('Favicon dosya adı')
                            ->placeholder('favicon.png')
                            ->helperText('Uzantı yazabilirsin. Kaydet sırasında dosya adı buna göre güncellenir.')
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('loader_logo')
                            ->label('Loader / preloader logosu')
                            ->image()
                            ->disk('public')
                            ->directory('settings/logos')
                            ->visibility('public')
                            ->imagePreviewHeight(60)
                            ->helperText('Sayfa yüklenirken kullanılan logo. Varsayılan: theme/yalovakamera/images/loader.svg'),
                        Forms\Components\TextInput::make('loader_logo_filename')
                            ->label('Loader logo dosya adı')
                            ->placeholder('loader-logo.svg')
                            ->helperText('Uzantı yazabilirsin. Kaydet sırasında dosya adı buna göre güncellenir.')
                            ->maxLength(255),
                    ])
                    ->columns(1),

                ],
                'footer' => [
                Forms\Components\Section::make('Footer')
                    ->description('Footer alanında görünen metinler.')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\TextInput::make('footer_title')
                            ->label('Footer başlığı')
                            ->placeholder('Yalova Kamera Sistemleri')
                            ->helperText('Footer sol blokta görünen ana başlık.')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('footer_description')
                            ->label('Footer açıklama metni')
                            ->rows(3)
                            ->placeholder('Güvenlik kamerası ve alarm sistemlerinde...')
                            ->helperText('Footer sol blokta görünen açıklama metni.')
                            ->maxLength(600),
                        Forms\Components\TextInput::make('newsletter_title')
                            ->label('Abonelik başlığı')
                            ->placeholder('Abone Ol')
                            ->helperText('Footer sağ blokta görünen abonelik başlığı.')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('newsletter_description')
                            ->label('Abonelik açıklama metni')
                            ->rows(2)
                            ->placeholder('Kampanya ve duyuruları almak için...')
                            ->helperText('Footer sağ blokta görünen abonelik açıklaması.')
                            ->maxLength(500),
                        Forms\Components\Textarea::make('copyright_text')
                            ->label('Copyright metni')
                            ->rows(2)
                            ->placeholder('© ' . date('Y') . ' Site Adı. Tüm hakları saklıdır.')
                            ->helperText('Ana footer alanında görünecek telif metni.')
                            ->maxLength(500),
                        Forms\Components\Textarea::make('footer_bottom_text')
                            ->label('Alt footer kısa telif metni')
                            ->rows(2)
                            ->placeholder('© ' . date('Y') . ' Yalova Kamera')
                            ->helperText('Sayfanın en altında, koyu zemindeki ek footer satırında görünen kısa metin.')
                            ->maxLength(300),
                    ])
                    ->columns(1),
                ],
                default => [],
            })
            ->statePath('data');
    }

    /**
     * @return array<string, string>
     */
    public function bolumler(): array
    {
        return self::BOLUMLER;
    }

    public function bolumSec(string $bolum): void
    {
        if (array_key_exists($bolum, self::BOLUMLER)) {
            $this->aktifBolum = $bolum;
            $this->data = array_replace($this->bolumVarsayilanlari($bolum), $this->data ?? []);
            $this->form->fill($this->data);
        }
    }

    public function save(): void
    {
        $this->form->getState();
        $data = $this->data ?? [];

        foreach ([
            'site_logo',
            'footer_logo',
            'favicon_logo',
            'loader_logo',
            'default_og_image',
            'homepage_og_image',
        ] as $key) {
            if (array_key_exists($key, $data) && ! empty($data[$key]) && is_string($data[$key])) {
                $data[$key] = ltrim($data[$key], '/');
            }
        }

        foreach ([
            'site_logo' => 'site_logo_filename',
            'footer_logo' => 'footer_logo_filename',
            'favicon_logo' => 'favicon_logo_filename',
            'loader_logo' => 'loader_logo_filename',
        ] as $logoKey => $filenameKey) {
            if (array_key_exists($logoKey, $data)) {
                $data[$logoKey] = $this->renameUploadedFile($data[$logoKey] ?? null, $data[$filenameKey] ?? null);
            }
        }

        unset(
            $data['site_logo_filename'],
            $data['footer_logo_filename'],
            $data['favicon_logo_filename'],
            $data['loader_logo_filename'],
        );

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '', 'general');
        }

        Notification::make()->title('Ayarlar kaydedildi.')->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    private function bolumVarsayilanlari(string $bolum): array
    {
        return match ($bolum) {
            'tema' => [
                'front_theme' => Setting::get('front_theme', \App\Services\FrontThemeService::DEFAULT_THEME),
            ],
            'seo' => [
                'site_title' => Setting::get('site_title', config('app.name')),
                'meta_description' => Setting::get('meta_description'),
                'meta_keywords' => Setting::get('meta_keywords'),
                'site_author' => Setting::get('site_author', 'Yalova Kamera Sistemleri'),
                'default_meta_robots' => Setting::get('default_meta_robots', 'index, follow'),
                'default_og_image' => Setting::get('default_og_image'),
                'homepage_meta_title' => Setting::get('homepage_meta_title'),
                'homepage_meta_description' => Setting::get('homepage_meta_description'),
                'homepage_meta_keywords' => Setting::get('homepage_meta_keywords'),
                'homepage_og_title' => Setting::get('homepage_og_title'),
                'homepage_og_description' => Setting::get('homepage_og_description'),
                'homepage_og_image' => Setting::get('homepage_og_image'),
            ],
            'logolar' => [
                'site_logo' => Setting::get('site_logo'),
                'site_logo_filename' => self::extractFilename(Setting::get('site_logo')),
                'footer_logo' => Setting::get('footer_logo'),
                'footer_logo_filename' => self::extractFilename(Setting::get('footer_logo')),
                'favicon_logo' => Setting::get('favicon_logo'),
                'favicon_logo_filename' => self::extractFilename(Setting::get('favicon_logo')),
                'loader_logo' => Setting::get('loader_logo'),
                'loader_logo_filename' => self::extractFilename(Setting::get('loader_logo')),
            ],
            'footer' => [
                'footer_title' => Setting::get('footer_title', 'Yalova Kamera Sistemleri'),
                'footer_description' => Setting::get('footer_description', 'Güvenlik kamerası ve alarm sistemlerinde keşif, kurulum, servis ve bakım hizmetleri.'),
                'newsletter_title' => Setting::get('newsletter_title', 'Abone Ol'),
                'newsletter_description' => Setting::get('newsletter_description', 'Kampanya ve duyuruları almak için e-posta adresinizi bırakın.'),
                'copyright_text' => Setting::get('copyright_text', '© ' . date('Y') . ' Yalova Kamera Sistemleri. Tüm hakları saklıdır.'),
                'footer_bottom_text' => Setting::get('footer_bottom_text', '© ' . date('Y') . ' Yalova Kamera'),
            ],
            default => [
                'site_url' => Setting::get('site_url', self::defaultSiteUrl()),
                'admin_path' => Setting::get('admin_path', 'admin'),
            ],
        };
    }

    private static function extractFilename(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return basename($path);
    }

    private function renameUploadedFile(mixed $path, mixed $targetFilename): mixed
    {
        if (! is_string($path) || trim($path) === '') {
            return $path;
        }

        if (! is_string($targetFilename) || trim($targetFilename) === '') {
            return ltrim($path, '/');
        }

        $disk = Storage::disk('public');
        $path = ltrim($path, '/');
        $directory = trim(pathinfo($path, PATHINFO_DIRNAME), './\\');
        $directory = $directory === '' ? '' : $directory . '/';

        $resolvedPath = $this->resolveExistingPath($disk, $path, $directory);
        if ($resolvedPath === null) {
            return $path;
        }

        $path = $resolvedPath;

        $currentExtension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $requestedBase = pathinfo(trim($targetFilename), PATHINFO_FILENAME);
        $requestedExtension = strtolower((string) pathinfo(trim($targetFilename), PATHINFO_EXTENSION));
        $extension = $requestedExtension !== '' ? $requestedExtension : $currentExtension;

        $safeBase = Str::of($requestedBase)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9_-]+/', '-')
            ->trim('-')
            ->value();

        if ($safeBase === '') {
            $safeBase = 'dosya';
        }

        $newFilename = $safeBase . ($extension !== '' ? '.' . $extension : '');
        $newPath = $directory . $newFilename;

        if (strcasecmp($newPath, $path) === 0) {
            return $path;
        }

        $counter = 1;
        while ($disk->exists($newPath)) {
            $newFilename = $safeBase . '-' . $counter . ($extension !== '' ? '.' . $extension : '');
            $newPath = $directory . $newFilename;
            $counter++;
        }

        $disk->move($path, $newPath);

        return $newPath;
    }

    private function resolveExistingPath($disk, string $path, string $directory): ?string
    {
        if ($disk->exists($path)) {
            return $path;
        }

        $basename = basename($path);
        $matched = collect($disk->files(rtrim($directory, '/')))
            ->first(fn (string $file): bool => strcasecmp(basename($file), $basename) === 0);

        if (is_string($matched)) {
            return $matched;
        }

        $stem = pathinfo($basename, PATHINFO_FILENAME);
        $extension = pathinfo($basename, PATHINFO_EXTENSION);
        $pattern = '/^' . preg_quote($stem, '/') . '(?:-\d+)?' . ($extension !== '' ? '\.' . preg_quote($extension, '/') : '') . '$/i';

        $matched = collect($disk->files(rtrim($directory, '/')))
            ->first(fn (string $file): bool => preg_match($pattern, basename($file)) === 1);

        return is_string($matched) ? $matched : null;
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')->label('Kaydet')->action('save')->color('primary'),
        ];
    }
}
