<?php

namespace App\Filament\Clusters\PersonelTakip\Pages;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipFilamentErisimYardimcisi;
use App\Services\PersonelTakip\PersonelAyarlariServisi;
use App\Services\TenantContextService;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class PersonelAyarlariSayfasi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $title = 'Personel Ayarları';

    protected static ?string $navigationLabel = 'Ayarlar';

    protected static ?string $slug = 'ayarlar';

    protected static string $view = 'filament.clusters.personel-takip.pages.personel-ayarlari';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $this->form->fill($firmaId ? app(PersonelAyarlariServisi::class)->genel($firmaId) : []);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Personel ayarları';
    }

    public function getSubheading(): ?string
    {
        return 'Mesai, maaş ve PIN davranışları için firma bazlı varsayılanlar.';
    }

    public static function canAccess(): bool
    {
        return PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::TANIM_GUNCELLE);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Genel ayarlar')
                    ->schema([
                        Forms\Components\Select::make('para_birimi')
                            ->label('Varsayılan para birimi')
                            ->options([
                                'TRY' => 'TRY',
                                'USD' => 'USD',
                                'EUR' => 'EUR',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('gunluk_calisma_saati')
                            ->label('Günlük çalışma saati')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Forms\Components\TextInput::make('haftalik_calisma_saati')
                            ->label('Haftalık çalışma saati')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Forms\Components\TextInput::make('fazla_mesai_katsayi')
                            ->label('Fazla mesai katsayısı')
                            ->numeric()
                            ->minValue(1)
                            ->step('0.01')
                            ->required(),
                        Forms\Components\Toggle::make('pin_zorunlu')
                            ->label('Personel PIN zorunlu'),
                        Forms\Components\Toggle::make('otomatik_maas_hesaplama')
                            ->label('Otomatik maaş hesaplama'),
                    ])
                    ->columns(3),
            ]);
    }

    public function kaydet(): void
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            Notification::make()->title('Aktif firma bulunamadı')->danger()->send();

            return;
        }

        $ayarlar = app(PersonelAyarlariServisi::class)->kaydetGenel($firmaId, $this->form->getState());
        $this->form->fill($ayarlar);

        Notification::make()->title('Personel ayarları kaydedildi')->success()->send();
    }
}
