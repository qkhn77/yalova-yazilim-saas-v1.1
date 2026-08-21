<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\Setting;
use App\Support\RecaptchaAyarlari;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WebApiAyarlar extends Page implements HasForms
{
    use InteractsWithForms;
    use \Filament\Pages\Concerns\InteractsWithFormActions;

    private const BOLUMLER = [
        'izleme' => 'İzleme',
        'guvenlik' => 'Güvenlik',
        'whatsapp' => 'WhatsApp',
        'arama' => 'Arama',
    ];

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'API Ayarlari';

    protected static ?string $slug = 'web-ayarlar/web-api-ayarlar';

    protected static string $view = 'filament.clusters.web.pages.web-api-ayarlar';

    public ?array $data = [];

    public string $aktifBolum = 'izleme';

    public function mount(): void
    {
        $this->form->fill($this->bolumVarsayilanlari($this->aktifBolum));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(match ($this->aktifBolum) {
                'izleme' => [
                Forms\Components\Section::make('Dogrulama ve Izleme')
                    ->description('Arama motoru dogrulama ve analiz kodlarini bu alandan yonetebilirsiniz.')
                    ->icon('heroicon-o-code-bracket-square')
                    ->schema([
                        Forms\Components\TextInput::make('google_search_console')
                            ->label('Google Search Console kodu')
                            ->placeholder('Orn: abc123XYZ...')
                            ->helperText('HTML dogrulama etiketindeki content degerini girebilirsiniz.')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('google_analytics_code')
                            ->label('Google Analytics kodu')
                            ->rows(4)
                            ->placeholder('Google Analytics script...')
                            ->helperText('Tum sayfalarin head bolumune eklenir.'),
                        Forms\Components\TextInput::make('google_tag_manager_id')
                            ->label('Google Tag Manager ID')
                            ->placeholder('Orn: GTM-XXXXXXX')
                            ->helperText('Orn: GTM-XXXXXXX formatinda Tag Manager kimligini girin.'),
                        Forms\Components\Textarea::make('meta_pixel_code')
                            ->label('Meta Pixel / Facebook Pixel kodu')
                            ->rows(6)
                            ->placeholder('<!-- Meta Pixel Code --> ...')
                            ->helperText('Tum sayfalarin head bolumune eklenir. Meta Pixel script kodunu tam olarak yapistirabilirsiniz.'),
                    ])
                    ->columns(1),

                ],
                'guvenlik' => [
                Forms\Components\Section::make('Form Guvenligi')
                    ->description('Iletisim formunda kullanilan reCAPTCHA ayarlarini buradan yonetebilirsiniz.')
                    ->schema([
                        Forms\Components\Toggle::make('recaptcha_enabled')
                            ->label('Google reCAPTCHA aktif')
                            ->helperText('Pasif oldugunda sitede hicbir yerde Google reCAPTCHA gosterilmez ve dogrulanmaz.')
                            ->default(false),
                        Forms\Components\TextInput::make('recaptcha_site_key')
                            ->label('Google reCAPTCHA Site Key')
                            ->placeholder('reCAPTCHA v2 site key')
                            ->helperText('Iletisim formunda robot dogrulamasi icin kullanilir.'),
                        Forms\Components\TextInput::make('recaptcha_secret_key')
                            ->label('Google reCAPTCHA Secret Key')
                            ->password()
                            ->dehydrated(fn ($state) => filled($state))
                            ->placeholder('Sunucu tarafi dogrulama icin')
                            ->helperText('Site Key ile birlikte doldurulmalidir.'),
                    ])
                    ->columns(1),

                ],
                'whatsapp' => [
                Forms\Components\Section::make('WhatsApp Butonu')
                    ->description('Sag altta gorunen sabit WhatsApp butonunu bu alandan yonetebilirsiniz.')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([

                        Forms\Components\Toggle::make('whatsapp_enabled')
                            ->label('WhatsApp butonu aktif')
                            ->helperText('Sag alttaki sabit WhatsApp butonunu gosterir veya gizler.')
                            ->default(true),
                        Forms\Components\TextInput::make('whatsapp_code')
                            ->label('WhatsApp numarasi')
                            ->placeholder('Orn: 905551234567')
                            ->helperText('Sag alttaki sabit WhatsApp sohbet butonu bu numarayi kullanir.')
                            ->maxLength(30),
                        Forms\Components\TextInput::make('whatsapp_button_text')
                            ->label('WhatsApp buton yazisi')
                            ->placeholder('Sohbet')
                            ->helperText('Sag alttaki sabit WhatsApp butonunda gorunecek metin.')
                            ->maxLength(30),
                        Forms\Components\Textarea::make('whatsapp_welcome_message')
                            ->label('WhatsApp otomatik mesaj')
                            ->rows(3)
                            ->placeholder('Merhaba, bilgi almak istiyorum.')
                            ->helperText('Butona tiklandiginda musteriyi karsilayan hazir mesaj otomatik doldurulur.')
                            ->maxLength(500),
                    ])
                    ->columns(1),

                ],
                'arama' => [
                Forms\Components\Section::make('Bizi Arayin Butonu')
                    ->description('WhatsApp butonunun ustunde gorunen sabit arama butonunu bu alandan yonetebilirsiniz.')
                    ->icon('heroicon-o-phone')
                    ->schema([

                        Forms\Components\Toggle::make('call_button_enabled')
                            ->label('Bizi Arayin butonu aktif')
                            ->helperText('WhatsApp butonunun uzerindeki sabit arama butonunu gosterir veya gizler.')
                            ->default(true),
                        Forms\Components\TextInput::make('call_button_phone')
                            ->label('Bizi Arayin telefon numarasi')
                            ->placeholder('Orn: 902263520724')
                            ->helperText('Arama butonuna tiklandiginda aranacak numara.')
                            ->maxLength(30),
                        Forms\Components\TextInput::make('call_button_text')
                            ->label('Bizi Arayin buton yazisi')
                            ->placeholder('Bizi Arayin')
                            ->helperText('Sag alttaki sabit arama butonunda gorunecek metin.')
                            ->maxLength(30),
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
            $this->data = array_replace($this->data ?? [], $this->bolumVarsayilanlari($bolum));
            $this->form->fill($this->data);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function bolumVarsayilanlari(string $bolum): array
    {
        return match ($bolum) {
            'izleme' => [
                'google_search_console' => Setting::get('google_search_console'),
                'google_analytics_code' => Setting::get('google_analytics_code'),
                'google_tag_manager_id' => Setting::get('google_tag_manager_id'),
                'meta_pixel_code' => Setting::get('meta_pixel_code'),
            ],
            'guvenlik' => [
                'recaptcha_enabled' => RecaptchaAyarlari::etkinMi(),
                'recaptcha_site_key' => Setting::get('recaptcha_site_key'),
                'recaptcha_secret_key' => Setting::get('recaptcha_secret_key'),
            ],
            'whatsapp' => [
                'whatsapp_enabled' => filter_var(Setting::get('whatsapp_enabled', true), FILTER_VALIDATE_BOOL),
                'whatsapp_code' => Setting::get('whatsapp_code'),
                'whatsapp_button_text' => Setting::get('whatsapp_button_text', 'Sohbet'),
                'whatsapp_welcome_message' => Setting::get('whatsapp_welcome_message', 'Merhaba, bilgi almak istiyorum.'),
            ],
            'arama' => [
                'call_button_enabled' => filter_var(Setting::get('call_button_enabled', true), FILTER_VALIDATE_BOOL),
                'call_button_phone' => Setting::get('call_button_phone'),
                'call_button_text' => Setting::get('call_button_text', 'Bizi Arayin'),
            ],
            default => [],
        };
    }

    public function save(): void
    {
        $this->form->getState();
        $data = $this->data ?? [];

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '', 'api');
        }

        Notification::make()
            ->title('API ayarlari kaydedildi.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')->label('Kaydet')->action('save')->color('primary'),
        ];
    }
}
