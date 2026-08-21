<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\SistemBakimModuServisi;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class SistemBakimModuSayfasi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Bakım Modu';

    protected static ?string $slug = 'sistem-bakim-modu';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string $view = 'filament.pages.sistem-bakim-modu-sayfasi';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $kullanici = Auth::user();

        return $kullanici instanceof User
            && ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false));
    }

    public function mount(SistemBakimModuServisi $bakim): void
    {
        $this->form->fill([
            'aktif' => $bakim->aktifMi(),
            'mesaj' => $bakim->mesaj(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Sistem bakım modu')
                    ->description('Bakım aktifken yalnızca sistem yöneticileri giriş yapabilir. Firma ve üye oturumları bir sonraki isteklerinde sonlandırılır.')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->schema([
                        Forms\Components\Toggle::make('aktif')
                            ->label('Bakım modunu aktif et')
                            ->onColor('danger')
                            ->helperText('Açıldığında normal kullanıcı girişleri ve uygulama erişimi bakım ekranına yönlendirilir.'),
                        Forms\Components\Textarea::make('mesaj')
                            ->label('Kullanıcıya gösterilecek mesaj')
                            ->rows(4)
                            ->maxLength(500)
                            ->required(),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function kaydet(SistemBakimModuServisi $bakim): void
    {
        $state = $this->form->getState();
        $bakim->kaydet((bool) ($state['aktif'] ?? false), (string) ($state['mesaj'] ?? ''));

        Notification::make()
            ->title(($state['aktif'] ?? false) ? 'Bakım modu aktif edildi.' : 'Bakım modu kapatıldı.')
            ->success()
            ->send();
    }
}
