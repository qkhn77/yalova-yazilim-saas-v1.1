<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\BarkodluSatis\Guvenlik\BarkodluSatisFilamentErisimYardimcisi;
use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Models\Muhasebe\SatisFisSablonu;
use App\Muhasebe\Servisler\SatisFisSablonuServisi;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SatisFisDuzenleSayfasi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Satis fisi duzenle';

    protected static ?string $slug = 'satis/barkodlu-satis-fis-sablonlari';

    protected static string $view = 'filament.clusters.muhasebe.pages.satis-fis-duzenle-sayfasi';

    public ?int $duzenlenenSablonId = null;

    /** @var array<string,mixed> */
    public array $data = [];

    private ?bool $ayarGuncelleYetkisiVarMiCache = null;

    private static ?bool $sablonTablosuVarMiCache = null;

    public function getHeading(): string|Htmlable
    {
        return 'Satis Fisi Duzenle';
    }

    public function getSubheading(): ?string
    {
        return 'A4, 80mm ve 58mm fis sablonlarini yonetin. Varsayilan sablon Kaydet + Yazdir icin kullanilir.';
    }

    public static function canAccess(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::herhangiBirBarkodluSatisYetkisiVarMi([
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_AYAR_GORUNTULE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_AYAR_GUNCELLE,
        ]);
    }

    public function mount(): void
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            abort(403);
        }
        if (! $this->sablonTablosuVarMi()) {
            return;
        }

        app(SatisFisSablonuServisi::class)->firmaSablonlariniHazirla($firmaId);
        $this->yeniSablon();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Sablon bilgileri')
                    ->schema([
                        Forms\Components\TextInput::make('ad')
                            ->label('Sablon adi')
                            ->required()
                            ->maxLength(191),
                        Forms\Components\TextInput::make('kod')
                            ->label('Kod')
                            ->helperText('Benzersiz olmalidir. Ornek: satis-fisi-80mm')
                            ->required()
                            ->maxLength(64),
                        Forms\Components\Select::make('sayfa_tipi')
                            ->label('Sayfa boyutu')
                            ->options([
                                'a4' => 'A4',
                                '80mm' => '80mm Fis Yazici',
                                '58mm' => '58mm Fis Yazici',
                            ])
                            ->required(),
                        Forms\Components\FileUpload::make('sablon_logo')
                            ->label('Sablon logosu')
                            ->helperText('Bos birakilirsa firma logosu kullanilir.')
                            ->disk('public')
                            ->directory('satis-fis-logolari')
                            ->image()
                            ->imageEditor()
                            ->maxSize(4096),
                        Forms\Components\Toggle::make('aktif')
                            ->label('Aktif')
                            ->default(true),
                        Forms\Components\Toggle::make('varsayilan_mi')
                            ->label('Varsayilan sablon'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Sablon icerigi')
                    ->schema([
                        Forms\Components\RichEditor::make('sablon_html')
                            ->label('Fis icerigi editoru')
                            ->helperText('Musteriye gorunen fis icerigini bu editor ile duzenleyin. Anahtarlar aynen yazilmalidir.')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'alignStart',
                                'alignCenter',
                                'alignEnd',
                                'blockquote',
                                'undo',
                                'redo',
                            ])
                            ->columnSpanFull()
                            ->required(),
                        Forms\Components\Textarea::make('sablon_css')
                            ->label('CSS')
                            ->rows(10),
                        Forms\Components\Placeholder::make('anahtarlar')
                            ->label('Kullanilabilir anahtarlar')
                            ->content('{{FIRMA_UNVAN}}, {{FIRMA_TELEFON}}, {{FIRMA_EPOSTA}}, {{FIRMA_ADRES}}, {{FIRMA_LOGO}}, {{SATIS_NO}}, {{SATIS_TARIHI}}, {{CARI_AD}}, {{KASIYER}}, {{ODEME_TIPI}}, {{KALEMLER}}, {{ARA_TOPLAM}}, {{ISKONTO_TOPLAMI}}, {{KDV_TOPLAMI}}, {{GENEL_TOPLAM}}, {{SATIS_NOTU}}'),
                    ]),
            ])
            ->statePath('data');
    }

    public function yeniSablon(): void
    {
        if (! $this->sablonTablosuVarMi()) {
            return;
        }

        $firmaId = (int) $this->aktifFirmaId();
        $hazir = Arr::first(app(SatisFisSablonuServisi::class)->hazirSablonlar());
        if (! is_array($hazir)) {
            return;
        }

        $this->duzenlenenSablonId = null;
        $this->form->fill([
            'ad' => 'Yeni Satis Fisi Sablonu',
            'kod' => app(SatisFisSablonuServisi::class)->benzersizKodUret($firmaId, 'yeni-satis-fisi-sablonu'),
            'sayfa_tipi' => '80mm',
            'aktif' => true,
            'varsayilan_mi' => false,
            'sablon_logo' => null,
            'sablon_html' => (string) $hazir['sablon_html'],
            'sablon_css' => (string) $hazir['sablon_css'],
        ]);
    }

    public function demoSablonlariGeriYukle(): void
    {
        if (! $this->ayarGuncelleYetkisiVarMi()) {
            abort(403);
        }

        $firmaId = (int) $this->aktifFirmaId();
        if ($firmaId < 1 || ! $this->sablonTablosuVarMi()) {
            return;
        }

        $hazirSablonlar = app(SatisFisSablonuServisi::class)->hazirSablonlar();
        $eklenen = 0;
        $guncellenen = 0;

        foreach ($hazirSablonlar as $hazir) {
            $mevcut = SatisFisSablonu::query()
                ->where('firma_id', $firmaId)
                ->where('kod', (string) $hazir['kod'])
                ->first();

            if ($mevcut) {
                $mevcut->forceFill([
                    'ad' => (string) $hazir['ad'],
                    'sayfa_tipi' => (string) $hazir['sayfa_tipi'],
                    'sablon_html' => (string) $hazir['sablon_html'],
                    'sablon_css' => (string) $hazir['sablon_css'],
                    'aktif' => true,
                ])->save();
                $guncellenen++;
            } else {
                SatisFisSablonu::query()->create([
                    'firma_id' => $firmaId,
                    'ad' => (string) $hazir['ad'],
                    'kod' => (string) $hazir['kod'],
                    'sayfa_tipi' => (string) $hazir['sayfa_tipi'],
                    'sablon_logo' => null,
                    'sablon_html' => (string) $hazir['sablon_html'],
                    'sablon_css' => (string) $hazir['sablon_css'],
                    'aktif' => true,
                    'varsayilan_mi' => false,
                ]);
                $eklenen++;
            }
        }

        $varsayilanVar = SatisFisSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('varsayilan_mi', true)
            ->exists();

        if (! $varsayilanVar) {
            $ilk = SatisFisSablonu::query()
                ->where('firma_id', $firmaId)
                ->where('kod', 'standart-80mm')
                ->first();

            if ($ilk) {
                app(SatisFisSablonuServisi::class)->varsayilanYap($ilk);
            }
        }

        Notification::make()
            ->title('Demo sablonlar geri yuklendi')
            ->body('Eklenen: '.$eklenen.' | Guncellenen: '.$guncellenen)
            ->success()
            ->send();
    }

    public function duzenle(int $id): void
    {
        if (! $this->sablonTablosuVarMi()) {
            return;
        }

        $firmaId = (int) $this->aktifFirmaId();
        $sablon = SatisFisSablonu::query()
            ->where('firma_id', $firmaId)
            ->whereKey($id)
            ->firstOrFail();

        $this->duzenlenenSablonId = (int) $sablon->id;
        $this->form->fill([
            'ad' => $sablon->ad,
            'kod' => $sablon->kod,
            'sayfa_tipi' => $sablon->sayfa_tipi,
            'aktif' => (bool) $sablon->aktif,
            'varsayilan_mi' => (bool) $sablon->varsayilan_mi,
            'sablon_logo' => (string) ($sablon->sablon_logo ?? ''),
            'sablon_html' => $sablon->sablon_html,
            'sablon_css' => (string) ($sablon->sablon_css ?? ''),
        ]);
    }

    public function kaydet(): void
    {
        if (! $this->ayarGuncelleYetkisiVarMi()) {
            abort(403);
        }

        $firmaId = (int) $this->aktifFirmaId();
        if (! $this->sablonTablosuVarMi()) {
            Notification::make()->title('Sablon tablosu bulunamadi')->danger()->send();

            return;
        }
        $state = $this->form->getState();

        $validated = validator($state, [
            'ad' => ['required', 'string', 'max:191'],
            'kod' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'sayfa_tipi' => ['required', 'in:a4,80mm,58mm'],
            'sablon_html' => ['required', 'string'],
            'sablon_css' => ['nullable', 'string'],
            'sablon_logo' => ['nullable', 'string', 'max:255'],
            'aktif' => ['nullable', 'boolean'],
            'varsayilan_mi' => ['nullable', 'boolean'],
        ], [
            'kod.regex' => 'Kod yalnizca kucuk harf, rakam ve tire icerebilir.',
        ])->validate();

        $kodAyniKayitDisindaVar = SatisFisSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('kod', (string) $validated['kod'])
            ->when($this->duzenlenenSablonId, fn ($q) => $q->where('id', '!=', (int) $this->duzenlenenSablonId))
            ->exists();

        if ($kodAyniKayitDisindaVar) {
            Notification::make()
                ->title('Kod zaten kullaniliyor')
                ->danger()
                ->send();

            return;
        }

        $sablon = SatisFisSablonu::query()->updateOrCreate(
            [
                'id' => $this->duzenlenenSablonId,
                'firma_id' => $firmaId,
            ],
            [
                'ad' => (string) $validated['ad'],
                'kod' => (string) $validated['kod'],
                'sayfa_tipi' => (string) $validated['sayfa_tipi'],
                'sablon_logo' => blank((string) ($validated['sablon_logo'] ?? '')) ? null : (string) $validated['sablon_logo'],
                'sablon_html' => (string) $validated['sablon_html'],
                'sablon_css' => (string) ($validated['sablon_css'] ?? ''),
                'aktif' => (bool) ($validated['aktif'] ?? true),
            ]
        );

        if ((bool) ($validated['varsayilan_mi'] ?? false)) {
            app(SatisFisSablonuServisi::class)->varsayilanYap($sablon);
        } elseif (! $this->varsayilanSablonVarMi($firmaId)) {
            app(SatisFisSablonuServisi::class)->varsayilanYap($sablon);
        }

        $this->duzenlenenSablonId = (int) $sablon->id;
        $this->duzenle((int) $sablon->id);

        Notification::make()
            ->title('Sablon kaydedildi')
            ->success()
            ->send();
    }

    public function varsayilanYap(int $id): void
    {
        if (! $this->ayarGuncelleYetkisiVarMi()) {
            abort(403);
        }

        $firmaId = (int) $this->aktifFirmaId();
        if (! $this->sablonTablosuVarMi()) {
            return;
        }
        $sablon = SatisFisSablonu::query()
            ->where('firma_id', $firmaId)
            ->whereKey($id)
            ->firstOrFail();

        app(SatisFisSablonuServisi::class)->varsayilanYap($sablon);

        if ($this->duzenlenenSablonId === (int) $sablon->id) {
            $this->data['varsayilan_mi'] = true;
        }

        Notification::make()
            ->title('Varsayilan sablon guncellendi')
            ->success()
            ->send();
    }

    public function sil(int $id): void
    {
        if (! $this->ayarGuncelleYetkisiVarMi()) {
            abort(403);
        }

        $firmaId = (int) $this->aktifFirmaId();
        if (! $this->sablonTablosuVarMi()) {
            return;
        }
        $sablon = SatisFisSablonu::query()
            ->where('firma_id', $firmaId)
            ->whereKey($id)
            ->firstOrFail();

        if ((bool) $sablon->varsayilan_mi) {
            Notification::make()
                ->title('Varsayilan sablon silinemez')
                ->body('Once farkli bir sablonu varsayilan yapin.')
                ->warning()
                ->send();

            return;
        }

        $sablon->delete();
        if ($this->duzenlenenSablonId === $id) {
            $this->yeniSablon();
        }

        Notification::make()
            ->title('Sablon silindi')
            ->success()
            ->send();
    }

    /**
     * @return array<int,SatisFisSablonu>
     */
    public function sablonlar(): array
    {
        if (! $this->sablonTablosuVarMi()) {
            return [];
        }

        $firmaId = (int) $this->aktifFirmaId();

        return SatisFisSablonu::query()
            ->where('firma_id', $firmaId)
            ->orderByDesc('varsayilan_mi')
            ->orderBy('sayfa_tipi')
            ->orderBy('ad')
            ->get()
            ->all();
    }

    public function ayarGuncelleYetkisiVarMi(): bool
    {
        return $this->ayarGuncelleYetkisiVarMiCache ??= BarkodluSatisFilamentErisimYardimcisi::barkodluSatisYetkisiVarMi(
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_AYAR_GUNCELLE
        );
    }

    protected function aktifFirmaId(): ?int
    {
        return app(TenantContextService::class)->aktifFirmaId();
    }

    private function varsayilanSablonVarMi(int $firmaId): bool
    {
        if (! $this->sablonTablosuVarMi()) {
            return false;
        }

        return SatisFisSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('varsayilan_mi', true)
            ->exists();
    }

    private function sablonTablosuVarMi(): bool
    {
        return self::$sablonTablosuVarMiCache ??= Schema::hasTable('muhasebe_satis_fis_sablonlari');
    }

    public function onizlemeHtmlCikti(): Htmlable
    {
        $state = $this->form->getState();
        $html = (string) ($state['sablon_html'] ?? '');
        if (trim($html) === '') {
            return new HtmlString('<div class="text-sm text-gray-600">On izleme icin sablon icerigi girin.</div>');
        }

        $logoUrl = $this->stateLogoUrl($state['sablon_logo'] ?? null) ?: $this->firmaLogoUrl();
        $kalemler = implode('', [
            '<tr><td>Ornek Urun A</td><td>869000000001</td><td>1,00</td><td>850,00</td><td>850,00</td></tr>',
            '<tr><td>Ornek Urun B</td><td>869000000002</td><td>2,00</td><td>120,00</td><td>240,00</td></tr>',
        ]);

        $tokens = [
            '{{FIRMA_UNVAN}}' => e('Yalova Bilgisayar Teknik Servis'),
            '{{FIRMA_TELEFON}}' => e('0 (226) 352 07 24'),
            '{{FIRMA_EPOSTA}}' => e('info@yalovabilgisayar.com'),
            '{{FIRMA_ADRES}}' => e('Yalova / Merkez'),
            '{{FIRMA_LOGO}}' => $logoUrl ? '<img src="'.e($logoUrl).'" alt="Sablon logosu">' : '',
            '{{SATIS_NO}}' => e('BS-ONIZLEME-001'),
            '{{SATIS_TARIHI}}' => e(now()->format('d.m.Y H:i')),
            '{{CARI_AD}}' => e('Perakende Musteri'),
            '{{KASIYER}}' => e('Sistem Kullanici'),
            '{{ODEME_TIPI}}' => e('NAKIT'),
            '{{KALEMLER}}' => $kalemler,
            '{{ARA_TOPLAM}}' => e('1.090,00 TRY'),
            '{{ISKONTO_TOPLAMI}}' => e('0,00 TRY'),
            '{{KDV_TOPLAMI}}' => e('196,20 TRY'),
            '{{GENEL_TOPLAM}}' => e('1.286,20 TRY'),
            '{{SATIS_NOTU}}' => e('Bu alan on izleme amaclidir.'),
        ];

        return new HtmlString(strtr($html, $tokens));
    }

    public function onizlemeCss(): string
    {
        $state = $this->form->getState();

        return (string) ($state['sablon_css'] ?? '');
    }

    public function onizlemeKapsayiciStili(): string
    {
        $state = $this->form->getState();
        $tip = (string) ($state['sayfa_tipi'] ?? '80mm');

        return match ($tip) {
            'a4' => 'max-width: 210mm; min-height: 297mm;',
            '58mm' => 'max-width: 58mm; min-height: 100mm;',
            default => 'max-width: 80mm; min-height: 100mm;',
        };
    }

    public function onizlemeSagBosluk(): string
    {
        $state = $this->form->getState();
        $tip = (string) ($state['sayfa_tipi'] ?? '80mm');

        return match ($tip) {
            '58mm' => '2mm',
            '80mm' => '3mm',
            default => '0mm',
        };
    }

    private function firmaLogoUrl(): ?string
    {
        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return null;
        }

        $logo = (string) (app(FirmaAyarDeposu::class)->oku($firmaId, 'logo', '') ?? '');

        return $this->dosyaUrlHazirla($logo);
    }

    private function stateLogoUrl(mixed $deger): ?string
    {
        if (is_string($deger)) {
            return $this->dosyaUrlHazirla($deger);
        }

        if (is_object($deger) && method_exists($deger, 'temporaryUrl')) {
            try {
                $url = $deger->temporaryUrl();

                return is_string($url) ? $url : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function dosyaUrlHazirla(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
