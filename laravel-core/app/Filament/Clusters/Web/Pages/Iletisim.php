<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\ContactPage;
use App\Support\SaaSemaYardimcisi;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class Iletisim extends Page implements HasForms
{
    use InteractsWithForms;
    use \Filament\Pages\Concerns\InteractsWithFormActions;

    private const BOLUMLER = [
        'baslik' => 'Başlık',
        'bilgiler' => 'Bilgiler',
        'sosyal' => 'Sosyal',
        'harita' => 'Harita',
        'form' => 'Form',
        'seo' => 'SEO',
    ];

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'İletişim';

    protected static ?string $slug = 'sayfalar/iletisim';

    protected static string $view = 'filament.clusters.web.pages.iletisim';

    public ?array $data = [];

    public string $aktifBolum = 'baslik';

    public function mount(): void
    {
        $this->form->fill($this->bolumVarsayilanlari($this->aktifBolum));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(match ($this->aktifBolum) {
                'baslik' => [
                Forms\Components\Section::make('İletişim başlığı ve açıklama')
                    ->description('Sayfadaki iletişim başlığı, alt başlık ve açıklama metni.')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([
                        Forms\Components\TextInput::make('section_heading')
                            ->label('Bölüm başlığı')
                            ->placeholder('İletişim')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('section_subheading')
                            ->label('Alt başlık')
                            ->placeholder('Güvenliğinizi bizimle sağlayın')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('section_intro')
                            ->label('Açıklama metni')
                            ->rows(3)
                            ->placeholder('Sorularınız mı var...')
                            ->maxLength(1000),
                    ])
                    ->columns(1),

                ],
                'bilgiler' => [
                Forms\Components\Section::make('Telefon, e-posta, adres')
                    ->description('İletişim sayfasında görünen iletişim bilgileri.')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefon')
                            ->placeholder('0 (226) 352 07 24')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('email')
                            ->label('E-posta')
                            ->email()
                            ->placeholder('info@yalovakamera.com')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('address')
                            ->label('Adres')
                            ->placeholder('Çiftlikköy / Yalova')
                            ->maxLength(255),
                    ])
                    ->columns(1),

                ],
                'sosyal' => [
                Forms\Components\Section::make('Sosyal medya linkleri')
                    ->description('Buradaki linkler hem İletişim sayfasında hem de footer sosyal medya alanında kullanılır. Boş bırakılan linkler frontta gösterilmez.')
                    ->icon('heroicon-o-share')
                    ->schema([
                        Forms\Components\TextInput::make('instagram_url')
                            ->label('Instagram')
                            ->placeholder('https://instagram.com/...')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('facebook_url')
                            ->label('Facebook')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('linkedin_url')
                            ->label('LinkedIn')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pinterest_url')
                            ->label('Pinterest')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('twitter_url')
                            ->label('X (Twitter)')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(1),

                ],
                'harita' => [
                Forms\Components\Section::make('Google Harita')
                    ->description('Embed URL veya tam iframe kodu yapıştırabilirsiniz.')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Forms\Components\Textarea::make('map_url')
                            ->label('Harita embed URL')
                            ->rows(3)
                            ->placeholder('https://www.google.com/maps/embed?pb=...')
                            ->helperText('Google Maps iframe src değeri veya tam iframe etiketi kabul edilir.')
                            ->maxLength(10000),
                    ])
                    ->columns(1),

                ],
                'form' => [
                Forms\Components\Section::make('Form başlığı')
                    ->schema([
                        Forms\Components\TextInput::make('form_heading')
                            ->label('Mesaj formu başlığı')
                            ->placeholder('Mesaj Gönder')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('form_intro')
                            ->label('Form açıklaması')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->columns(1)
                    ->collapsed(),

                ],
                'seo' => [
                Forms\Components\Section::make('SEO')
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')->label('Sayfa başlığı')->maxLength(255),
                        Forms\Components\Textarea::make('meta_description')->label('Meta açıklama')->rows(2)->maxLength(500),
                        Forms\Components\TextInput::make('meta_keywords')->label('Meta anahtar kelimeler')->maxLength(255),
                        Forms\Components\TextInput::make('header_heading')->label('Sayfa üst başlık')->maxLength(255),
                    ])
                    ->columns(1)
                    ->collapsed(),
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
        $c = ContactPage::instance();

        return match ($bolum) {
            'baslik' => [
                'section_heading' => $c->section_heading,
                'section_subheading' => $c->section_subheading,
                'section_intro' => $c->section_intro,
            ],
            'bilgiler' => [
                'phone' => $c->phone,
                'email' => $c->email,
                'address' => $c->address,
            ],
            'sosyal' => [
                'instagram_url' => $c->instagram_url,
                'linkedin_url' => $c->linkedin_url,
                'pinterest_url' => $c->pinterest_url,
                'twitter_url' => $c->twitter_url,
                'facebook_url' => $c->facebook_url,
            ],
            'harita' => [
                'map_url' => $c->map_url,
            ],
            'form' => [
                'form_heading' => $c->form_heading,
                'form_intro' => $c->form_intro,
            ],
            'seo' => [
                'meta_title' => $c->meta_title,
                'meta_description' => $c->meta_description,
                'meta_keywords' => $c->meta_keywords,
                'header_heading' => $c->header_heading,
            ],
            default => [],
        };
    }

    public function save(): void
    {
        $this->form->getState();
        $data = $this->data ?? [];
        $c = ContactPage::instance();
        $table = $c->getTable();

        $allowed = [];
        foreach (array_keys($data) as $col) {
            if (SaaSemaYardimcisi::kolonVarMi($table, $col)) {
                $allowed[$col] = $data[$col];
            }
        }

        if (array_key_exists('map_url', $allowed)) {
            $allowed['map_url'] = $this->normalizeMapUrl($allowed['map_url']);
        }

        $c->update($allowed);

        Notification::make()
            ->title('İletişim sayfası kaydedildi.')
            ->success()
            ->send();
    }

    protected function normalizeMapUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (Str::contains(Str::lower($value), '<iframe') && preg_match('/src=["\']([^"\']+)["\']/i', $value, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')->label('Kaydet')->action('save')->color('primary'),
        ];
    }
}
