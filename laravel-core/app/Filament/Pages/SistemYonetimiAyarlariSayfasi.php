<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class SistemYonetimiAyarlariSayfasi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Sistem ayarları';

    protected static ?string $slug = 'sistem-ayarlari';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.sistem-yonetimi-ayarlari-sayfasi';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $kullanici = Auth::user();

        return $kullanici instanceof User
            && ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false));
    }

    public function mount(): void
    {
        $this->form->fill([
            'logo' => Setting::get('sistem_admin_logo'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Admin panel görünümü')
                    ->description('Bu logo, firma özel logosu tanımlanmamışsa admin panelinin toolbar ve sidebar alanlarında varsayılan olarak kullanılır.')
                    ->icon('heroicon-o-paint-brush')
                    ->schema([
                        Forms\Components\FileUpload::make('logo')
                            ->label('Varsayılan admin logosu')
                            ->image()
                            ->disk('public')
                            ->directory('sistem-logolari')
                            ->visibility('public')
                            ->storeFiles(true)
                            ->dehydrated(true)
                            ->imagePreviewHeight('80')
                            ->maxSize(2048)
                            ->helperText('PNG, JPG, JPEG veya WebP. Dosyayı seçtikten sonra Sistem ayarlarını kaydet butonuna basın.'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function kaydet(): void
    {
        $state = $this->form->getState();
        $logo = $state['logo'] ?? null;

        if (is_array($logo)) {
            $logo = $logo[0] ?? null;
        }

        if (blank($logo)) {
            $logo = Setting::get('sistem_admin_logo');
        }

        Setting::set('sistem_admin_logo', filled($logo) ? (string) $logo : null, 'sistem');

        Notification::make()
            ->title('Sistem ayarları kaydedildi.')
            ->success()
            ->send();

        $this->dispatch('sistem-logo-guncellendi');
    }
}
