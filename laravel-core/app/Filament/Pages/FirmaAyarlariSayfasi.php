<?php

namespace App\Filament\Pages;

use App\Models\Firma;
use App\Models\Muhasebe\Depo;
use App\Models\User;
use App\Services\EcommerceBildirimAyarServisi;
use App\Services\EcommerceFirmaAyarServisi;
use App\Services\EcommerceOdemeFirmaAyarServisi;
use App\Services\EcommerceUlkeServisi;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use App\Support\EcommerceBildirimTanimlari;
use App\Support\KullaniciRolYardimcisi;
use App\Support\SaaSemaYardimcisi;
use App\Support\TablePaginationDefaults;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class FirmaAyarlariSayfasi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static string $view = 'filament.pages.firma-ayarlari-sayfasi';

    protected static ?string $title = 'Firma ayarları';

    protected static ?string $slug = 'firma-ayarlari';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    private ?bool $odemeSecretGoruntulebilirMiCache = null;

    private ?int $odemeSecretGoruntulebilirMiKullaniciId = null;

    /** @var array<int, Firma|null> */
    private static array $firmaCache = [];

    public static function canAccess(): bool
    {
        if (! SaaSemaYardimcisi::firmalarTablosuVarMi()) {
            return false;
        }

        /** @var User|null $kullanici */
        $kullanici = Auth::user();
        if (! $kullanici) {
            return false;
        }

        $fid = app(TenantContextService::class)->aktifFirmaId();
        if (! $fid) {
            return false;
        }

        $firma = static::firmaGetir((int) $fid);

        return $firma !== null && $kullanici->can('update', $firma);
    }

    public function mount(): void
    {
        $fid = $this->aktifFirmaId();
        if (! $fid) {
            abort(403);
        }

        $firma = static::firmaGetir((int) $fid);
        if (! $firma) {
            abort(404);
        }

        $depo = app(FirmaAyarDeposu::class);
        $this->odemeSecretGoruntulebilirMiCache = $this->odemeSecretGoruntulebilirMiForFirma($firma);

        $this->data = [
            'firma_ayar_ad' => $firma->ad,
            'telefon' => $firma->telefon,
            'eposta' => $firma->eposta,
            'adres' => $firma->adres,
            'logo' => $depo->oku($fid, 'logo'),
            'para_birimi' => $depo->oku($fid, 'para_birimi', 'TRY'),
            'varsayilan_dil' => $depo->oku($fid, 'varsayilan_dil', 'tr'),
            'zaman_dilimi' => $depo->oku($fid, 'zaman_dilimi', 'Europe/Istanbul'),
            'default_table_page_size' => TablePaginationDefaults::normalize(
                $depo->oku($fid, TablePaginationDefaults::SETTING_KEY, 10),
            ),
            'stok_depo_modulu_aktif_mi' => (bool) $depo->oku($fid, 'stok_depo_modulu_aktif_mi', false),
            'stok_varsayilan_depo_id' => $depo->oku($fid, 'stok_varsayilan_depo_id', null),
            'stok_depo_secimi_zorunlu_mu' => (bool) $depo->oku($fid, 'stok_depo_secimi_zorunlu_mu', false),
            'stok_deposuz_izinli_mi' => (bool) $depo->oku($fid, 'stok_deposuz_izinli_mi', true),
            'stok_depo_bildirimleri_aktif_mi' => (bool) $depo->oku($fid, 'stok_depo_bildirimleri_aktif_mi', true),
            'stok_son_kullanma_tarihi_kurali' => (string) $depo->oku($fid, 'stok_son_kullanma_tarihi_kurali', 'uyar'),
            'stok_parti_telegram_aktif_mi' => (bool) $depo->oku($fid, 'stok_parti_telegram_aktif_mi', false),
            'stok_parti_telegram_uyari_gun' => (int) $depo->oku($fid, 'stok_parti_telegram_uyari_gun', 30),
            'stok_son_kullanma_tarihi_kurali' => (string) $depo->oku($fid, 'stok_son_kullanma_tarihi_kurali', 'uyar'),
            'telegram_bot_token' => (string) $depo->oku($fid, 'telegram_bot_token', ''),
            'telegram_chat_id' => (string) $depo->oku($fid, 'telegram_chat_id', ''),
        ];

        if ($this->eTicaretDetayModu()) {
            $checkoutUlkeKodlari = $depo->oku($fid, 'ecommerce_checkout_ulke_kodlari', '');

            $this->data = array_merge($this->data, [
                'ecommerce_varsayilan_checkout_ulke' => (string) $depo->oku($fid, 'ecommerce_varsayilan_checkout_ulke', 'TR'),
                'ecommerce_checkout_ulke_kodlari' => is_array($checkoutUlkeKodlari)
                    ? implode(', ', (array) $checkoutUlkeKodlari)
                    : (string) $checkoutUlkeKodlari,

                // E-ticaret tahsilat ayarları
                'ecommerce_etkin_mi' => (bool) $depo->oku($fid, 'ecommerce_etkin_mi', false),
                'ecommerce_tahsilat_cari_id' => $depo->oku($fid, 'ecommerce_tahsilat_cari_id', null),
                'ecommerce_tahsilat_kasa_id' => $depo->oku($fid, 'ecommerce_tahsilat_kasa_id', null),
                'ecommerce_tahsilat_pos_id' => $depo->oku($fid, 'ecommerce_tahsilat_pos_id', null),
                'ecommerce_odeme_dakika' => (int) $depo->oku($fid, 'ecommerce_odeme_dakika', config('ecommerce.odeme_dakika', 15)),
                'ecommerce_otomatik_genel_kasa_kullan' => (bool) $depo->oku($fid, 'ecommerce_otomatik_genel_kasa_kullan', true),
                'ecommerce_cron_fallback_etkin_mi' => (bool) $depo->oku($fid, 'ecommerce_cron_fallback_etkin_mi', true),

                // E-ticaret ödeme sağlayıcı ayarları
                'ecommerce_odeme_aktif_mi' => (bool) $depo->oku($fid, 'ecommerce_odeme_aktif_mi', false),
                'ecommerce_odeme_provider' => (string) $depo->oku($fid, 'ecommerce_odeme_provider', 'paytr'),
                'test_modu' => (bool) $depo->oku($fid, 'test_modu', false),
                'odeme_aciklama_sablonu' => (string) $depo->oku($fid, 'odeme_aciklama_sablonu', ''),
                'callback_url' => (string) $depo->oku($fid, 'callback_url', ''),

                'paytr_merchant_id' => (string) $depo->oku($fid, 'paytr_merchant_id', ''),
                'paytr_merchant_key' => (string) $depo->oku($fid, 'paytr_merchant_key', ''),
                'paytr_merchant_salt' => (string) $depo->oku($fid, 'paytr_merchant_salt', ''),

                'iyzico_api_key' => (string) $depo->oku($fid, 'iyzico_api_key', ''),
                'iyzico_secret_key' => (string) $depo->oku($fid, 'iyzico_secret_key', ''),
                'iyzico_base_url' => (string) $depo->oku($fid, 'iyzico_base_url', 'https://sandbox-api.iyzipay.com'),
            ]);

            $this->data = array_merge(
                $this->data,
                app(EcommerceBildirimAyarServisi::class)->ayarlariGetir($fid)
            );

            if (! $this->odemeSecretGoruntulebilirMiCache) {
                $this->data['paytr_merchant_key'] = '';
                $this->data['paytr_merchant_salt'] = '';
                $this->data['iyzico_api_key'] = '';
                $this->data['iyzico_secret_key'] = '';
            }
        }

        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        $fid = (int) ($this->aktifFirmaId() ?? 0);
        $ayarServisi = app(EcommerceFirmaAyarServisi::class);
        $eTicaretDetayModu = $this->eTicaretDetayModu();

        return $form
            ->schema([
                Forms\Components\Section::make('Genel')
                    ->schema([
                        Forms\Components\TextInput::make('firma_ayar_ad')
                            ->label('Firma adı')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('logo')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('firma-logolari/'.max(0, $fid))
                            ->visibility('public'),
                        Forms\Components\TextInput::make('telefon')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('eposta')
                            ->label('E-posta')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('adres')
                            ->label('Adres')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Bölgesel')
                    ->schema([
                        Forms\Components\TextInput::make('para_birimi')
                            ->label('Para birimi')
                            ->maxLength(8)
                            ->default('TRY')
                            ->helperText('Örn. TRY, USD'),
                        Forms\Components\TextInput::make('varsayilan_dil')
                            ->label('Varsayılan dil')
                            ->maxLength(12)
                            ->default('tr')
                            ->helperText('Örn. tr, en'),
                        Forms\Components\Select::make('zaman_dilimi')
                            ->label('Zaman dilimi')
                            ->searchable()
                            ->options(static::zamanDilimiBaslangicSecenekleri())
                            ->getSearchResultsUsing(fn (string $search): array => static::zamanDilimiAramaSonuclari($search))
                            ->getOptionLabelUsing(fn (?string $value): ?string => $value)
                            ->default('Europe/Istanbul'),
                    ])->columns(2),

                Forms\Components\Section::make('Yönetim tabloları')
                    ->schema([
                        Forms\Components\Select::make('default_table_page_size')
                            ->label('Varsayılan tablo kayıt sayısı')
                            ->helperText('Yönetim tabloları ilk açıldığında bir sayfada gösterilecek kayıt sayısını belirler. Kullanıcı tablo üzerinden bunu geçici olarak değiştirebilir.')
                            ->options([
                                10 => '10 kayıt',
                                20 => '20 kayıt',
                                50 => '50 kayıt',
                                100 => '100 kayıt',
                                1000 => '1000 kayıt',
                                'all' => 'Tüm kayıtlar',
                            ])
                            ->default(10)
                            ->required(),
                    ]),

                Forms\Components\Section::make('Stok ve depo')
                    ->description('Depo modülü firma bazında isteğe bağlıdır. Deposuz stok, genel stok olarak tutulur.')
                    ->schema([
                        Forms\Components\Toggle::make('stok_depo_modulu_aktif_mi')
                            ->label('Depo modülü aktif')
                            ->live()
                            ->default(false),
                        Forms\Components\Select::make('stok_varsayilan_depo_id')
                            ->label('Varsayılan depo')
                            ->options(fn (): array => Depo::query()->aktif()->orderBy('ad')->pluck('ad', 'id')->all())
                            ->searchable()
                            ->nullable()
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('stok_depo_modulu_aktif_mi')),
                        Forms\Components\Toggle::make('stok_depo_secimi_zorunlu_mu')
                            ->label('Depo seçimi zorunlu mu')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('stok_depo_modulu_aktif_mi')),
                        Forms\Components\Toggle::make('stok_deposuz_izinli_mi')
                            ->label('Deposuz stoğa izin veriliyor mu')
                            ->default(true)
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('stok_depo_modulu_aktif_mi')),
                        Forms\Components\Toggle::make('stok_depo_bildirimleri_aktif_mi')
                            ->label('Depo işlem bildirimleri aktif')
                            ->default(true)
                            ->helperText('Transfer ve sayım tamamlandığında firma kullanıcılarına panel bildirimi gönderilir.'),
                        Forms\Components\Select::make('stok_son_kullanma_tarihi_kurali')
                            ->label('Son kullanma tarihi davranışı')
                            ->options(['uyar' => 'Sadece uyar', 'engelle' => 'Süresi geçmiş satışı engelle'])
                            ->default('uyar')
                            ->helperText('Tarihi girilmiş parti ürünlerinde uygulanır.'),
                        Forms\Components\Toggle::make('stok_parti_telegram_aktif_mi')
                            ->label('Parti son kullanma Telegram uyarısı')
                            ->helperText('Aktif edilirse günlük tek bir özet mesaj gönderilir.'),
                        Forms\Components\Select::make('stok_parti_telegram_uyari_gun')
                            ->label('Kaç gün önce uyarı verilsin')
                            ->options([7 => '7 gün', 15 => '15 gün', 30 => '30 gün'])
                            ->default(30),
                    ])->columns(2),

                Forms\Components\Section::make('Telegram')
                    ->description('Firma genelindeki tüm Telegram bildirimleri için ortak bağlantı bilgileri.')
                    ->schema([
                        Forms\Components\TextInput::make('telegram_bot_token')
                            ->label('Telegram Bot Token')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->maxLength(255)
                            ->helperText('BotFather tarafından verilen bot token. Güvenli olarak saklanır.'),
                        Forms\Components\TextInput::make('telegram_chat_id')
                            ->label('Telegram Chat ID')
                            ->maxLength(80)
                            ->helperText('Mesajların gönderileceği kişi, grup veya kanal Chat ID değeri.'),
                    ])->columns(2),

                ...($eTicaretDetayModu ? [
                Forms\Components\Section::make('E-Ticaret Teslimat Bölgeleri')
                    ->schema([
                        Forms\Components\Select::make('ecommerce_varsayilan_checkout_ulke')
                            ->label('Varsayılan checkout ülkesi')
                            ->options(fn (Forms\Get $get): array => (bool) $get('ecommerce_etkin_mi')
                                ? app(EcommerceUlkeServisi::class)->tumUlkeSecenekleri()
                                : [])
                            ->searchable()
                            ->default('TR'),
                        Forms\Components\Textarea::make('ecommerce_checkout_ulke_kodlari')
                            ->label('Checkout ülke kodları')
                            ->rows(3)
                            ->helperText('Checkout ekranında gösterilecek teslimat ülkelerini virgülle yazın. Örn: TR, DE, GB, US')
                            ->placeholder('TR, DE, GB, US'),
                    ])->columns(2),
                Forms\Components\Section::make('E-Ticaret Tahsilat')
                    ->schema([
                        Forms\Components\Toggle::make('ecommerce_etkin_mi')
                            ->label('E-ticaret tahsilatı aktif mi')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('ecommerce_tahsilat_cari_id')
                            ->label('Tahsilat cari hesabı')
                            ->searchable()
                            ->options(fn (Forms\Get $get): array => (bool) $get('ecommerce_etkin_mi')
                                ? $ayarServisi->cariSecenekleri($fid)
                                : [])
                            ->placeholder('Seçiniz')
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_etkin_mi')),

                        Forms\Components\Select::make('ecommerce_tahsilat_kasa_id')
                            ->label('Tahsilat kasası')
                            ->searchable()
                            ->options(fn (Forms\Get $get): array => (bool) $get('ecommerce_etkin_mi')
                                ? $ayarServisi->kasaSecenekleri($fid)
                                : [])
                            ->placeholder('Seçiniz')
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_etkin_mi')),

                        Forms\Components\Select::make('ecommerce_tahsilat_pos_id')
                            ->label('Varsayılan POS hesabı (opsiyonel)')
                            ->searchable()
                            ->options(fn (Forms\Get $get): array => (bool) $get('ecommerce_etkin_mi')
                                ? $ayarServisi->posSecenekleri($fid)
                                : [])
                            ->placeholder('Yok')
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_etkin_mi')),

                        Forms\Components\TextInput::make('ecommerce_odeme_dakika')
                            ->label('Ödeme süresi (dk)')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_etkin_mi')),

                        Forms\Components\Toggle::make('ecommerce_otomatik_genel_kasa_kullan')
                            ->label('Kasa yoksa otomatik Genel Kasa oluştur')
                            ->default(true)
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_etkin_mi')),

                        Forms\Components\Toggle::make('ecommerce_cron_fallback_etkin_mi')
                            ->label('Cron/fallback tetiklemeleri aktif mi')
                            ->default(true)
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_etkin_mi')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('E-Ticaret Ödeme Ayarları')
                    ->schema([
                        Forms\Components\Toggle::make('ecommerce_odeme_aktif_mi')
                            ->label('Ödeme sağlayıcısı aktif mi?')
                            ->default(false)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('ecommerce_odeme_provider')
                            ->label('Ödeme sağlayıcısı')
                            ->searchable()
                            ->options([
                                'paytr' => 'PayTR',
                                'iyzico' => 'iyzico',
                            ])
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_odeme_aktif_mi'))
                            ->required()
                            ->placeholder('Seçiniz'),

                        Forms\Components\Toggle::make('test_modu')
                            ->label('Test modu')
                            ->default(false)
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_odeme_aktif_mi'))
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('odeme_aciklama_sablonu')
                            ->label('Ödeme açıklama şablonu (opsiyonel)')
                            ->rows(3)
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_odeme_aktif_mi')),

                        Forms\Components\TextInput::make('callback_url')
                            ->label('Callback URL (opsiyonel)')
                            ->helperText("Boşsa sistem otomatik endpoint'i kullanır.")
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Placeholder::make('odeme_yapilandirma_ozeti')
                            ->label('Ödeme Yapılandırma Özeti')
                            ->content(function (Forms\Get $get): HtmlString {
                                $provider = (string) ($get('ecommerce_odeme_provider') ?? '');
                                $aktif = (bool) ($get('ecommerce_odeme_aktif_mi') ?? false);
                                $testModu = (bool) ($get('test_modu') ?? false);
                                $baseUrl = trim((string) ($get('iyzico_base_url') ?? ''));
                                $iyzicoApiKeyVar = trim((string) ($get('iyzico_api_key') ?? '')) !== '';
                                $iyzicoSecretKeyVar = trim((string) ($get('iyzico_secret_key') ?? '')) !== '';
                                $paytrMerchantIdVar = trim((string) ($get('paytr_merchant_id') ?? '')) !== '';
                                $paytrMerchantKeyVar = trim((string) ($get('paytr_merchant_key') ?? '')) !== '';
                                $paytrMerchantSaltVar = trim((string) ($get('paytr_merchant_salt') ?? '')) !== '';

                                $satirlar = [
                                    '<div class="text-sm leading-6">',
                                    '<p><strong>Durum:</strong> '.($aktif ? 'Aktif' : 'Pasif').'</p>',
                                    '<p><strong>Provider:</strong> '.e($provider !== '' ? $provider : 'Seçilmemiş').'</p>',
                                    '<p><strong>Test modu:</strong> '.($testModu ? 'Açık' : 'Kapalı').'</p>',
                                ];

                                if ($provider === 'iyzico') {
                                    $satirlar[] = '<p><strong>Base URL:</strong> <code>'.e($baseUrl !== '' ? $baseUrl : 'https://sandbox-api.iyzipay.com').'</code></p>';
                                    $satirlar[] = '<p><strong>API Key:</strong> '.($iyzicoApiKeyVar ? 'Kaydedilmiş' : 'Eksik').'</p>';
                                    $satirlar[] = '<p><strong>Secret Key:</strong> '.($iyzicoSecretKeyVar ? 'Kaydedilmiş' : 'Eksik').'</p>';
                                }

                                if ($provider === 'paytr') {
                                    $satirlar[] = '<p><strong>Merchant ID:</strong> '.($paytrMerchantIdVar ? 'Kaydedilmiş' : 'Eksik').'</p>';
                                    $satirlar[] = '<p><strong>Merchant Key:</strong> '.($paytrMerchantKeyVar ? 'Kaydedilmiş' : 'Eksik').'</p>';
                                    $satirlar[] = '<p><strong>Merchant Salt:</strong> '.($paytrMerchantSaltVar ? 'Kaydedilmiş' : 'Eksik').'</p>';
                                }

                                $satirlar[] = '</div>';

                                return new HtmlString(implode('', $satirlar));
                            })
                            ->columnSpanFull(),

                        // PayTR alanları
                        Forms\Components\TextInput::make('paytr_merchant_id')
                            ->label('PayTR Merchant ID')
                            ->maxLength(50)
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_odeme_aktif_mi'))
                            ->visible(fn (Forms\Get $get): bool => (string) $get('ecommerce_odeme_provider') === 'paytr'),

                        Forms\Components\TextInput::make('paytr_merchant_key')
                            ->label('PayTR Merchant Key')
                            ->password()
                            ->revealable()
                            ->autocomplete('off')
                            ->dehydrated(fn (): bool => $this->odemeSecretGoruntulebilirMi())
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_odeme_aktif_mi'))
                            ->visible(fn (Forms\Get $get): bool => $this->odemeSecretGoruntulebilirMi() && (string) $get('ecommerce_odeme_provider') === 'paytr')
                            ->helperText('Bu alan sadece yetkili kullanıcıya görünür.')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('paytr_merchant_salt')
                            ->label('PayTR Merchant Salt')
                            ->password()
                            ->revealable()
                            ->autocomplete('off')
                            ->dehydrated(fn (): bool => $this->odemeSecretGoruntulebilirMi())
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_odeme_aktif_mi'))
                            ->visible(fn (Forms\Get $get): bool => $this->odemeSecretGoruntulebilirMi() && (string) $get('ecommerce_odeme_provider') === 'paytr'),

                        // iyzico alanları
                        Forms\Components\TextInput::make('iyzico_api_key')
                            ->label('iyzico API Key')
                            ->maxLength(80)
                            ->password()
                            ->revealable()
                            ->autocomplete('off')
                            ->dehydrated(fn (): bool => $this->odemeSecretGoruntulebilirMi())
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_odeme_aktif_mi'))
                            ->visible(fn (Forms\Get $get): bool => $this->odemeSecretGoruntulebilirMi() && (string) $get('ecommerce_odeme_provider') === 'iyzico'),

                        Forms\Components\TextInput::make('iyzico_secret_key')
                            ->label('iyzico Secret Key')
                            ->password()
                            ->revealable()
                            ->autocomplete('off')
                            ->dehydrated(fn (): bool => $this->odemeSecretGoruntulebilirMi())
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_odeme_aktif_mi'))
                            ->visible(fn (Forms\Get $get): bool => $this->odemeSecretGoruntulebilirMi() && (string) $get('ecommerce_odeme_provider') === 'iyzico'),

                        Forms\Components\TextInput::make('iyzico_base_url')
                            ->label('iyzico Base URL')
                            ->helperText('Test için resmi sandbox URL: https://sandbox-api.iyzipay.com')
                            ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_odeme_aktif_mi'))
                            ->visible(fn (Forms\Get $get): bool => (string) $get('ecommerce_odeme_provider') === 'iyzico'),

                        Forms\Components\Placeholder::make('iyzico_test_bilgileri')
                            ->label('iyzico Test Bilgileri')
                            ->content(new HtmlString(implode('', [
                                '<div class="text-sm leading-6">',
                                '<p><strong>Sandbox kayıt:</strong> <a href="https://sandbox-merchant.iyzipay.com/auth/register" target="_blank" rel="noopener">sandbox-merchant.iyzipay.com/auth/register</a></p>',
                                '<p><strong>API anahtarları:</strong> iyzico ortak test key vermez. Sandbox hesabınıza giriş yapıp <strong>Settings &gt; Merchant Settings &gt; API Keys</strong> bölümünden size özel <code>sandbox-...</code> API Key ve Secret Key alın.</p>',
                                '<p><strong>Test Base URL:</strong> <code>https://sandbox-api.iyzipay.com</code></p>',
                                '<p><strong>Sandbox OTP:</strong> <code>123456</code></p>',
                                '<p><strong>Örnek başarılı test kartları:</strong></p>',
                                '<ul style="margin:8px 0 0 18px; list-style:disc;">',
                                '<li><code>5526080000000006</code> - Akbank Mastercard Credit</li>',
                                '<li><code>4603450000000000</code> - Denizbank Visa Credit</li>',
                                '<li><code>5311570000000005</code> - QNB Mastercard Credit</li>',
                                '</ul>',
                                '<p style="margin-top:10px;">SKT ve CVV sandbox ortamında doğru formatta ve ileri tarih olacak şekilde serbest kullanılabilir.</p>',
                                '<p style="margin-top:10px;">Resmi dokümanlar: <a href="https://docs.iyzico.com/en/getting-started/preliminaries/sandbox" target="_blank" rel="noopener">Sandbox</a> | <a href="https://docs.iyzico.com/en/payment-methods/checkoutform/cf-implementation/cf-initialize" target="_blank" rel="noopener">Checkout Form Initialize</a> | <a href="https://docs.iyzico.com/en/add-ons/test-cards" target="_blank" rel="noopener">Test Cards</a></p>',
                                '</div>',
                            ])))
                            ->visible(fn (Forms\Get $get): bool => (string) $get('ecommerce_odeme_provider') === 'iyzico')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('E-Ticaret Bildirimleri')
                    ->schema($this->bildirimSchema())
                    ->visible(fn (Forms\Get $get): bool => (bool) $get('ecommerce_etkin_mi'))
                    ->columns(3),
                ] : []),
            ])
            ->statePath('data');
    }

    public function kaydet(): void
    {
        $fid = $this->aktifFirmaId();
        if (! $fid) {
            abort(403);
        }

        $firma = Firma::query()->findOrFail($fid);
        $this->authorize('update', $firma);

        $s = $this->form->getState();

        $firma->update([
            'ad' => $s['firma_ayar_ad'] ?? $firma->ad,
            'telefon' => $s['telefon'] ?? null,
            'eposta' => $s['eposta'] ?? null,
            'adres' => $s['adres'] ?? null,
        ]);

        $depo = app(FirmaAyarDeposu::class);
        if (array_key_exists('logo', $s) && filled($s['logo'])) {
            $depo->yaz($fid, 'logo', $s['logo']);
        }
        $depo->yaz($fid, 'para_birimi', $s['para_birimi'] ?? 'TRY');
        $depo->yaz($fid, 'varsayilan_dil', $s['varsayilan_dil'] ?? 'tr');
        $depo->yaz($fid, 'zaman_dilimi', $s['zaman_dilimi'] ?? 'Europe/Istanbul');
        $depo->yaz(
            $fid,
            TablePaginationDefaults::SETTING_KEY,
            TablePaginationDefaults::normalize($s['default_table_page_size'] ?? 10),
        );
        $depo->yaz($fid, 'stok_depo_modulu_aktif_mi', (bool) ($s['stok_depo_modulu_aktif_mi'] ?? false));
        $depo->yaz($fid, 'stok_varsayilan_depo_id', filled($s['stok_varsayilan_depo_id'] ?? null) ? (int) $s['stok_varsayilan_depo_id'] : null);
        $depo->yaz($fid, 'stok_depo_secimi_zorunlu_mu', (bool) ($s['stok_depo_secimi_zorunlu_mu'] ?? false));
        $depo->yaz($fid, 'stok_deposuz_izinli_mi', (bool) ($s['stok_deposuz_izinli_mi'] ?? true));
        $depo->yaz($fid, 'stok_depo_bildirimleri_aktif_mi', (bool) ($s['stok_depo_bildirimleri_aktif_mi'] ?? true));
        $depo->yaz($fid, 'stok_son_kullanma_tarihi_kurali', in_array(($s['stok_son_kullanma_tarihi_kurali'] ?? 'uyar'), ['uyar', 'engelle'], true) ? $s['stok_son_kullanma_tarihi_kurali'] : 'uyar');
        $depo->yaz($fid, 'stok_parti_telegram_aktif_mi', (bool) ($s['stok_parti_telegram_aktif_mi'] ?? false));
        $depo->yaz($fid, 'stok_parti_telegram_uyari_gun', in_array((int) ($s['stok_parti_telegram_uyari_gun'] ?? 30), [7, 15, 30], true) ? (int) $s['stok_parti_telegram_uyari_gun'] : 30);
        $depo->yaz($fid, 'telegram_bot_token', trim((string) ($s['telegram_bot_token'] ?? '')));
        $depo->yaz($fid, 'telegram_chat_id', trim((string) ($s['telegram_chat_id'] ?? '')));

        if ($this->eTicaretDetayModu()) {
            $depo->yaz($fid, 'ecommerce_varsayilan_checkout_ulke', $s['ecommerce_varsayilan_checkout_ulke'] ?? 'TR');
            $depo->yaz($fid, 'ecommerce_checkout_ulke_kodlari', $s['ecommerce_checkout_ulke_kodlari'] ?? '');
            app(EcommerceFirmaAyarServisi::class)->kaydetAyarlar($fid, $s);
            app(EcommerceOdemeFirmaAyarServisi::class)->kaydetAyarlar($fid, $s);
            app(EcommerceBildirimAyarServisi::class)->kaydetAyarlar($fid, $s);
        }

        Notification::make()->title('Firma ayarları kaydedildi.')->success()->send();
    }

    /**
     * @return array<int, Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        $detayModu = $this->eTicaretDetayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_ayarlar' : 'e_ticaret_detaylari')
                ->label($detayModu ? 'Hızlı Ayarlar' : 'E-Ticaret Detayları')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url($detayModu ? static::getUrl() : static::getUrl(['e_ticaret_detay' => 1])),
        ];
    }

    protected function aktifFirmaId(): ?int
    {
        return app(TenantContextService::class)->aktifFirmaId();
    }

    private function eTicaretDetayModu(): bool
    {
        return request()->boolean('e_ticaret_detay');
    }

    private static function firmaGetir(int $firmaId): ?Firma
    {
        if ($firmaId < 1) {
            return null;
        }

        if (! array_key_exists($firmaId, self::$firmaCache)) {
            self::$firmaCache[$firmaId] = Firma::query()->find($firmaId);
        }

        return self::$firmaCache[$firmaId];
    }

    protected function odemeSecretGoruntulebilirMi(): bool
    {
        $kullaniciId = Auth::id() ? (int) Auth::id() : null;
        if ($this->odemeSecretGoruntulebilirMiCache !== null
            && $this->odemeSecretGoruntulebilirMiKullaniciId === $kullaniciId) {
            return $this->odemeSecretGoruntulebilirMiCache;
        }

        $this->odemeSecretGoruntulebilirMiKullaniciId = $kullaniciId;

        return $this->odemeSecretGoruntulebilirMiCache = $this->odemeSecretGoruntulebilirMiForFirma();
    }

    private function odemeSecretGoruntulebilirMiForFirma(?Firma $firma = null): bool
    {
        /** @var User|null $kullanici */
        $kullanici = Auth::user();
        if (! $kullanici) {
            return false;
        }

        if (KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici)) {
            return true;
        }

        if (! $firma) {
            $fid = $this->aktifFirmaId();
            if (! $fid) {
                return false;
            }

            $firma = Firma::query()->find($fid);
            if (! $firma) {
                return false;
            }
        }

        // Secret görme yetkisi daha dar: sadece yüksek yetkili rol.
        return $kullanici->can('delete', $firma);
    }

    /**
     * @return array<string, string>
     */
    private static function zamanDilimiBaslangicSecenekleri(): array
    {
        return collect([
            'Europe/Istanbul',
            'UTC',
            'Europe/London',
            'Europe/Berlin',
            'Europe/Paris',
            'America/New_York',
            'America/Chicago',
            'America/Los_Angeles',
            'Asia/Dubai',
            'Asia/Tokyo',
        ])->mapWithKeys(fn (string $zamanDilimi): array => [$zamanDilimi => $zamanDilimi])->all();
    }

    /**
     * @return array<string, string>
     */
    private static function zamanDilimiAramaSonuclari(string $search): array
    {
        $aranan = mb_strtolower(trim($search));

        return collect(\DateTimeZone::listIdentifiers())
            ->when($aranan !== '', fn ($liste) => $liste->filter(
                fn (string $zamanDilimi): bool => str_contains(mb_strtolower($zamanDilimi), $aranan)
            ))
            ->take(50)
            ->mapWithKeys(fn (string $zamanDilimi): array => [$zamanDilimi => $zamanDilimi])
            ->all();
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function bildirimSchema(): array
    {
        $schema = [
            Forms\Components\TextInput::make('ecommerce_bildirim_admin_eposta')
                ->label('Yönetici bildirim e-postası')
                ->email()
                ->helperText('Boş bırakırsanız firma e-postası kullanılır.')
                ->columnSpanFull(),
        ];

        foreach (EcommerceBildirimTanimlari::olaylar() as $olay => $etiket) {
            $schema[] = Forms\Components\Fieldset::make($etiket)
                ->schema([
                    Forms\Components\Toggle::make('ecommerce_bildirim_'.$olay.'_email')
                        ->label('E-posta')
                        ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_etkin_mi')),
                    Forms\Components\Toggle::make('ecommerce_bildirim_'.$olay.'_panel')
                        ->label('Panel')
                        ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_etkin_mi')),
                    Forms\Components\Toggle::make('ecommerce_bildirim_'.$olay.'_sms')
                        ->label('SMS/WhatsApp')
                        ->disabled(fn (Forms\Get $get): bool => ! (bool) $get('ecommerce_etkin_mi')),
                ])
                ->columns(3);
        }

        return $schema;
    }
}

