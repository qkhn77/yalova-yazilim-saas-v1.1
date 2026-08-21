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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class WebMailAyarlar extends Page implements HasForms
{
    use InteractsWithForms;
    use \Filament\Pages\Concerns\InteractsWithFormActions;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Mail Ayarlari';

    protected static ?string $slug = 'web-ayarlar/web-mail-ayarlar';

    protected static string $view = 'filament.clusters.web.pages.web-mail-ayarlar';

    public ?array $data = [];

    public bool $ornekKurulumAcik = false;

    public function mount(): void
    {
        $this->form->fill([
            'mail_host' => Setting::get('mail_host', config('mail.mailers.smtp.host')),
            'mail_port' => Setting::get('mail_port', config('mail.mailers.smtp.port')),
            'mail_encryption' => Setting::get('mail_encryption', config('mail.mailers.smtp.encryption')),
            'mail_username' => Setting::get('mail_username', config('mail.mailers.smtp.username')),
            'mail_password' => '',
            'mail_active' => (bool) Setting::get('mail_active', true),
            'mail_recipient' => Setting::get('mail_recipient', config('mail.from.address')),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('SMTP Baglantisi')
                    ->description('E-posta gonderimi icin SMTP ayarlarini bu alandan yonetebilirsiniz.')
                    ->icon('heroicon-o-server-stack')
                    ->schema([
                        Forms\Components\TextInput::make('mail_host')
                            ->label('SMTP Server')
                            ->placeholder('smtp.gmail.com')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mail_port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->default(587)
                            ->placeholder('587')
                            ->required(),
                        Forms\Components\Select::make('mail_encryption')
                            ->label('Mail Sertifika')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                '' => 'Yok',
                            ])
                            ->placeholder('TLS')
                            ->default('tls'),
                        Forms\Components\TextInput::make('mail_username')
                            ->label('SMTP E-posta')
                            ->email()
                            ->placeholder('ornek@alanadi.com')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mail_password')
                            ->label('SMTP E-posta Sifre')
                            ->password()
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Degistirmek icin yazin. Bos birakirsaniz mevcut sifre korunur.')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Genel')
                    ->schema([
                        Forms\Components\Toggle::make('mail_active')
                            ->label('Aktif / Pasif')
                            ->helperText('Pasif yapilirsa sistem gercek e-posta gondermez.')
                            ->default(true),
                        Forms\Components\TextInput::make('mail_recipient')
                            ->label('Mesajin Gelecegi E-posta Adresi')
                            ->email()
                            ->placeholder('info@yalovakamera.com')
                            ->helperText('Iletisim formu ve test mailleri bu adrese gonderilir.')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(1),

                ...($this->ornekKurulumAcik ? [
                    Forms\Components\Section::make('Ornek Kurulum')
                        ->description('Kullandiginiz mail altyapisina gore asagidaki orneklerden size uygun olani baz alabilirsiniz.')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\Placeholder::make('gmail_example')
                                ->label('Gmail kullaniyorsaniz')
                                ->content(new \Illuminate\Support\HtmlString(
                                    '<div style="line-height:1.8">'.
                                    '<strong>SMTP Server:</strong> smtp.gmail.com<br>'.
                                    '<strong>SMTP Port:</strong> 587<br>'.
                                    '<strong>Mail Sertifika:</strong> TLS<br>'.
                                    '<strong>SMTP E-posta:</strong> Gmail adresiniz<br>'.
                                    '<strong>SMTP E-posta Sifre:</strong> Google App Password'.
                                    '</div>'
                                )),
                            Forms\Components\Placeholder::make('hosting_example')
                                ->label('Hosting veya cPanel kullaniyorsaniz')
                                ->content(new \Illuminate\Support\HtmlString(
                                    '<div style="line-height:1.8">'.
                                    '<strong>SMTP Server:</strong> mail.siteadi.com<br>'.
                                    '<strong>SMTP Port:</strong> 465 veya 587<br>'.
                                    '<strong>Mail Sertifika:</strong> SSL veya TLS<br>'.
                                    '<strong>SMTP E-posta:</strong> info@siteadi.com<br>'.
                                    '<strong>SMTP E-posta Sifre:</strong> ilgili posta kutusu sifresi'.
                                    '</div>'
                                )),
                            Forms\Components\Placeholder::make('usage_note')
                                ->label('En kolay kullanim')
                                ->content(new \Illuminate\Support\HtmlString(
                                    '<div style="line-height:1.8">'.
                                    '1. Bilgileri doldurun.<br>'.
                                    '2. Kaydet butonuna basin.<br>'.
                                    '3. Test Mail Gonder ile kontrol edin.<br>'.
                                    '4. Mail geliyorsa ayarlar dogrudur.'.
                                    '</div>'
                                )),
                        ])
                        ->columns(1),
                ] : []),
            ])
            ->statePath('data');
    }

    public function ornekKurulumDegistir(): void
    {
        $this->ornekKurulumAcik = ! $this->ornekKurulumAcik;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (empty($data['mail_password'])) {
            $data['mail_password'] = Setting::get('mail_password');
        }

        foreach ($data as $key => $value) {
            if ($key === 'mail_password' && $value === '') {
                continue;
            }

            Setting::set($key, $value ?? '', 'mail');
        }

        Notification::make()
            ->title('Mail ayarlari kaydedildi.')
            ->success()
            ->send();
    }

    public function sendTestMail(): void
    {
        $data = $this->form->getState();
        $password = $data['mail_password'] ?: Setting::get('mail_password');
        $recipient = trim((string) ($data['mail_recipient'] ?? ''));

        if (! (bool) ($data['mail_active'] ?? true)) {
            Notification::make()
                ->title('Mail gonderimi pasif')
                ->body('Test gonderimi icin once mail sistemini aktif hale getirin.')
                ->warning()
                ->send();

            return;
        }

        if (
            empty($data['mail_host']) ||
            empty($data['mail_port']) ||
            empty($data['mail_username']) ||
            empty($password) ||
            empty($recipient)
        ) {
            Notification::make()
                ->title('Eksik mail ayari')
                ->body('SMTP server, port, e-posta, sifre ve alici adresi alanlarini doldurun.')
                ->danger()
                ->send();

            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', array_merge(config('mail.mailers.smtp', []), [
            'transport' => 'smtp',
            'host' => $data['mail_host'],
            'port' => (int) $data['mail_port'],
            'encryption' => $data['mail_encryption'] ?: null,
            'username' => $data['mail_username'],
            'password' => $password,
        ]));
        Config::set('mail.from', [
            'address' => $data['mail_username'],
            'name' => config('app.name', 'Yalova Kamera'),
        ]);

        try {
            Mail::mailer('smtp')->raw(
                "Bu bir test e-postasidir.\n\nGonderim zamani: ".now()->format('d.m.Y H:i:s')."\nSistem: Yalova Kamera",
                function ($message) use ($recipient, $data) {
                    $message->to($recipient)
                        ->subject('Test Maili - Yalova Kamera')
                        ->replyTo($data['mail_username'], config('app.name', 'Yalova Kamera'));
                }
            );

            Notification::make()
                ->title('Test maili gonderildi')
                ->body($recipient.' adresine test maili basariyla gonderildi.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Test maili gonderilemedi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
            Actions\Action::make('ornekKurulumDegistir')
                ->label($this->ornekKurulumAcik ? 'Ornekleri Gizle' : 'Ornek Kurulum')
                ->action('ornekKurulumDegistir')
                ->color('gray'),
            Actions\Action::make('sendTestMail')
                ->label('Test Mail Gonder')
                ->action('sendTestMail')
                ->color('gray'),
        ];
    }
}
