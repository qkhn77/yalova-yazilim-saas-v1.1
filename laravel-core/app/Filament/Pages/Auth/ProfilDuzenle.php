<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View as ViewField;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Support\Enums\MaxWidth;
use Filament\Pages\Auth\EditProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfilDuzenle extends EditProfile
{
    protected static ?string $slug = 'profil';

    public bool $profilFotografiAlaniAcik = false;

    public function getMaxWidth(): MaxWidth
    {
        return MaxWidth::FiveExtraLarge;
    }

    public static function getLabel(): string
    {
        return 'Profilim';
    }

    public static function getRelativeRouteName(): string
    {
        return 'profile';
    }

    public function getTitle(): string
    {
        return 'Profilimi Düzenle';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Profil bilgileriniz güncellendi.';
    }

    public function form(Form $form): Form
    {
        $profilBolumleri = [];

        if ($this->profilFotografiAlaniAcik) {
            $profilBolumleri[] = Section::make('Profil fotoğrafı')
                ->description('Panelin sağ üst köşesinde görünen avatarınız.')
                ->icon('heroicon-o-user-circle')
                ->schema([
                    FileUpload::make('profil_fotografi')
                        ->label('Fotoğraf')
                        ->disk('public')
                        ->directory('profil-fotograflari')
                        ->image()
                        ->avatar()
                        ->imagePreviewHeight('9rem')
                        ->panelLayout('compact circle')
                        ->placeholder('Fotoğraf seç')
                        ->maxSize(2048)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->helperText('Kare fotoğraflar en iyi sonucu verir.'),
                    Placeholder::make('profil_fotografi_notu')
                        ->label('')
                        ->content('JPG, PNG veya WebP kullanabilirsiniz. En fazla 2 MB.'),
                ])
                ->columnSpan([
                    'default' => 1,
                    'lg' => 1,
                ]);
        }

        $profilBolumleri[] = Section::make('Kişisel bilgiler')
            ->description('Ad, kullanıcı adı ve iletişim bilgileriniz.')
            ->icon('heroicon-o-identification')
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->schema([
                TextInput::make('name')
                    ->label('Görünen ad')
                    ->prefixIcon('heroicon-m-user')
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),
                TextInput::make('ad_soyad')
                    ->label('Ad soyad')
                    ->prefixIcon('heroicon-m-identification')
                    ->maxLength(255),
                TextInput::make('kullanici_adi')
                    ->label('Kullanıcı adı')
                    ->prefixIcon('heroicon-m-at-symbol')
                    ->helperText('Harf, rakam, nokta, tire ve alt çizgi kullanabilirsiniz.')
                    ->rule('regex:/^[A-Za-z0-9._-]*$/')
                    ->validationMessages([
                        'regex' => 'Kullanıcı adı yalnızca harf, rakam, nokta, tire ve alt çizgi içerebilir.',
                    ])
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('telefon')
                    ->label('Telefon')
                    ->prefixIcon('heroicon-m-phone')
                    ->tel()
                    ->placeholder('+90 555 000 00 00')
                    ->helperText('WhatsApp ve bildirimlerde kullanılacak iletişim numarası.')
                    ->maxLength(32)
                    ->rule('regex:/^[0-9+()\\s-]*$/')
                    ->validationMessages([
                        'regex' => 'Telefon numarası yalnızca rakam, boşluk, +, -, ( ve ) içerebilir.',
                    ]),
                TextInput::make('email')
                    ->label('E-posta')
                    ->prefixIcon('heroicon-m-envelope')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),
            ])
            ->columnSpan([
                'default' => 1,
                'lg' => $this->profilFotografiAlaniAcik ? 2 : 3,
            ]);

        return $form
            ->schema([
                ViewField::make('filament.pages.auth.profil-ozet')
                    ->columnSpanFull(),
                Grid::make([
                    'default' => 1,
                    'lg' => 3,
                ])
                    ->schema($profilBolumleri)
                    ->columnSpanFull(),
                Section::make('Şifre')
                    ->description('Şifrenizi değiştirmek için mevcut şifrenizi ve yeni şifrenizi iki kez girin.')
                    ->icon('heroicon-o-lock-closed')
                    ->columns([
                        'default' => 1,
                        'lg' => 3,
                    ])
                    ->schema([
                        TextInput::make('currentPassword')
                            ->label('Mevcut şifre')
                            ->prefixIcon('heroicon-m-key')
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->autocomplete('current-password')
                            ->helperText('Şifre değişikliği için mevcut şifrenizi doğrulayın.')
                            ->required(fn (Get $get): bool => filled($get('password')) || filled($get('passwordConfirmation')))
                            ->rules(fn (Get $get): array => filled($get('password')) || filled($get('passwordConfirmation'))
                                ? ['current_password']
                                : [])
                            ->validationMessages([
                                'current_password' => 'Mevcut şifreniz hatalı.',
                                'required' => 'Şifre değiştirmek için mevcut şifrenizi girin.',
                            ])
                            ->dehydrated(false),
                        TextInput::make('password')
                            ->label('Yeni şifre')
                            ->prefixIcon('heroicon-m-lock-closed')
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->helperText('Güçlü bir şifre belirleyin.')
                            ->required(fn (Get $get): bool => filled($get('currentPassword')) || filled($get('passwordConfirmation')))
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
                            ->live(debounce: 500)
                            ->same('passwordConfirmation')
                            ->validationMessages([
                                'required' => 'Yeni şifrenizi girin.',
                                'same' => 'Yeni şifre ve tekrarı aynı olmalı.',
                            ]),
                        TextInput::make('passwordConfirmation')
                            ->label('Yeni şifre tekrarı')
                            ->prefixIcon('heroicon-m-check-circle')
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->autocomplete('new-password')
                            ->required(fn (Get $get): bool => filled($get('password')))
                            ->helperText('Yeni şifreyi aynı şekilde tekrar girin.')
                            ->validationMessages([
                                'required' => 'Yeni şifrenizi tekrar girin.',
                            ])
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public function profilFotografiAlaniniDegistir(): void
    {
        $this->profilFotografiAlaniAcik = ! $this->profilFotografiAlaniAcik;

        if ($this->profilFotografiAlaniAcik) {
            $this->data['profil_fotografi'] = $this->getUser()->profil_fotografi;
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! $this->profilFotografiAlaniAcik) {
            unset($data['profil_fotografi']);
        }

        foreach (['name', 'ad_soyad', 'kullanici_adi', 'telefon', 'email'] as $alan) {
            if (array_key_exists($alan, $data) && is_string($data[$alan])) {
                $data[$alan] = trim($data[$alan]);
            }
        }

        if (blank($data['ad_soyad'] ?? null) && filled($data['name'] ?? null)) {
            $data['ad_soyad'] = $data['name'];
        }

        if (blank($data['kullanici_adi'] ?? null)) {
            $data['kullanici_adi'] = null;
        }

        if (blank($data['telefon'] ?? null)) {
            $data['telefon'] = null;
        }

        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = mb_strtolower($data['email'], 'UTF-8');
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $eskiProfilFotografi = (string) ($record->profil_fotografi ?? '');

        $record = parent::handleRecordUpdate($record, $data);

        $yeniProfilFotografi = (string) ($record->profil_fotografi ?? '');

        if ($eskiProfilFotografi !== '' && $eskiProfilFotografi !== $yeniProfilFotografi) {
            Storage::disk('public')->delete($eskiProfilFotografi);
        }

        return $record;
    }
}
