<?php

namespace App\Filament\Clusters\PersonelTakip\Pages;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipFilamentErisimYardimcisi;
use App\Models\Sube;
use App\Services\PersonelTakip\PersonelPinGirisCikisServisi;
use App\Services\TenantContextService;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class PersonelPinTerminalSayfasi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $title = 'PIN Terminali';

    protected static ?string $navigationLabel = 'PIN Terminali';

    protected static ?string $slug = 'terminal/pin-giris-cikis';

    protected static string $view = 'filament.clusters.personel-takip.pages.personel-pin-terminal';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $sonIslem = null;

    public function mount(): void
    {
        $this->form->fill([
            'pin_kodu' => null,
            'sube_id' => null,
        ]);
    }

    public function getHeading(): string|Htmlable
    {
        return 'PIN terminali';
    }

    public function getSubheading(): ?string
    {
        return 'Personel giriş-çıkış işlemlerini PIN ile hızlı kaydet.';
    }

    public static function canAccess(): bool
    {
        return PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(
            PersonelTakipYetkiSablonlari::GIRIS_CIKIS_DUZENLE
        );
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Terminal')
                    ->schema([
                        Forms\Components\TextInput::make('pin_kodu')
                            ->label('PIN')
                            ->password()
                            ->numeric()
                            ->minLength(4)
                            ->maxLength(12)
                            ->required()
                            ->extraInputAttributes([
                                'autocomplete' => 'one-time-code',
                                'inputmode' => 'numeric',
                                'wire:keydown.enter.prevent' => 'pinIslemiYap',
                            ]),
                        Forms\Components\Select::make('sube_id')
                            ->label('Şube')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this->subeSecenekleri($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => $this->subeEtiketi($value)),
                    ])
                    ->columns(2),
            ]);
    }

    public function pinIslemiYap(): void
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            Notification::make()->title('Aktif firma bulunamadı')->danger()->send();

            return;
        }

        $veri = $this->form->getState();

        try {
            $kayit = app(PersonelPinGirisCikisServisi::class)->pinIleIslemYap(
                firmaId: $firmaId,
                pin: (string) ($veri['pin_kodu'] ?? ''),
                subeId: filled($veri['sube_id'] ?? null) ? (int) $veri['sube_id'] : null,
            );
        } catch (ValidationException $exception) {
            $ilkHata = Arr::first(Arr::flatten($exception->errors())) ?: 'PIN işlemi tamamlanamadı.';
            Notification::make()->title((string) $ilkHata)->danger()->send();

            return;
        }

        $kayit->loadMissing('personel', 'sube');
        $cikisMi = filled($kayit->cikis_at);
        $this->sonIslem = [
            'tip' => $cikisMi ? 'cikis' : 'giris',
            'personel' => $kayit->personel?->ad_soyad,
            'sube' => $kayit->sube?->ad,
            'giris_at' => $kayit->giris_at?->format('d.m.Y H:i'),
            'cikis_at' => $kayit->cikis_at?->format('d.m.Y H:i'),
            'onay_durumu' => $kayit->onay_durumu,
        ];

        $this->form->fill([
            'pin_kodu' => null,
            'sube_id' => $veri['sube_id'] ?? null,
        ]);

        Notification::make()
            ->title($cikisMi ? 'Çıkış kaydedildi' : 'Giriş kaydedildi')
            ->body((string) ($this->sonIslem['personel'] ?? 'Personel'))
            ->success()
            ->send();
    }

    /**
     * @return array<int, string>
     */
    public function subeSecenekleri(?string $search = null): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return [];
        }

        return Sube::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->when(
                filled($search),
                fn ($query) => $query->where('ad', 'like', '%'.trim((string) $search).'%')
            )
            ->orderBy('ad')
            ->limit(20)
            ->pluck('ad', 'id')
            ->all();
    }

    private function subeEtiketi(mixed $value): ?string
    {
        $id = (int) $value;
        if ($id < 1) {
            return null;
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return null;
        }

        return Sube::query()
            ->where('firma_id', $firmaId)
            ->whereKey($id)
            ->value('ad');
    }
}
