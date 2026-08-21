<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisAyarSayfaErisimleri;
use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisFilamentErisimYardimcisi;
use App\Models\Firma;
use App\Models\TeknikServis\TeknikServisBaskiSablonu;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use App\Support\TeknikServisYetkiSablonlari;
use App\TeknikServis\Servisler\TeknikServisBaskiSablonuServisi;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

abstract class TeknikServisBaskiSablonuDuzenleyiciSayfasi extends Page implements HasForms
{
    use InteractsWithForms;
    use TeknikServisAyarSayfaErisimleri;
    use WithFileUploads;

    private const DEFAULT_LOGO_PATH = 'teknik-servis-sablon-logolari/iV8V8hfdaQcYmGA4aF6QcjPweytbx7vZtweJFqso.png';

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.clusters.teknik-servis.pages.baski-sablonu-duzenleyici-sayfasi';

    public ?int $duzenlenenSablonId = null;

    /** @var array<string,mixed> */
    public array $data = [];

    public bool $duzenleyiciAcik = false;

    /** @var array<int, TemporaryUploadedFile|null> */
    public array $sablonLogoYuklemeleri = [];

    private ?string $firmaLogoUrlCache = null;

    /** @var array<int,string|null> */
    private array $sablonLogoUrlCache = [];

    abstract protected static function sablonTuru(): string;

    abstract protected static function sayfaBasligi(): string;

    public static function canAccess(): bool
    {
        return TeknikServisFilamentErisimYardimcisi::herhangiBirTeknikServisErisimiVarMi([
            TeknikServisYetkiSablonlari::AYAR_GORUNTULE,
            TeknikServisYetkiSablonlari::AYAR_GUNCELLE,
            TeknikServisYetkiSablonlari::GORUNTULE,
            TeknikServisYetkiSablonlari::GUNCELLE,
        ]);
    }

    public function getHeading(): string|Htmlable
    {
        return static::sayfaBasligi();
    }

    public function getSubheading(): ?string
    {
        return 'A4, A5, 80mm, 58mm ve 10x10mm baskı şablonlarını yönetin. Varsayılan şablon yazdırma akışlarında kullanılır.';
    }

    public function mount(): void
    {
        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            abort(403);
        }

        if (! $this->firmaSablonuVarMi($firmaId)) {
            app(TeknikServisBaskiSablonuServisi::class)->firmaSablonlariniHazirla($firmaId, static::sablonTuru());
            Cache::put('teknik-servis:baski-sablonu-var:v1:'.$firmaId.':'.static::sablonTuru(), true, 3600);
        }

        $this->bosSablonFormuHazirla();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->duzenleyiciAcik ? [
                Forms\Components\Section::make('Şablon bilgileri')
                    ->schema([
                        Forms\Components\TextInput::make('ad')
                            ->label('Şablon adı')
                            ->required()
                            ->maxLength(191),
                        Forms\Components\TextInput::make('kod')
                            ->label('Kod')
                            ->helperText('Benzersiz olmalıdır. Örnek: servis-kabul-a5')
                            ->required()
                            ->maxLength(64),
                        Forms\Components\Select::make('sayfa_tipi')
                            ->label('Sayfa boyutu')
                            ->options([
                                'a4' => 'A4',
                                'a5' => 'A5',
                                '80mm' => '80mm',
                                '58mm' => '58mm',
                                '10x10mm' => '10mm x 10mm',
                            ])
                            ->required(),
                        Forms\Components\FileUpload::make('sablon_logo')
                            ->label('Şablon logosu')
                            ->helperText('Boş bırakılırsa firma logosu kullanılır.')
                            ->extraAttributes(['id' => 'sablon-logo-editoru'])
                            ->disk('public')
                            ->directory('teknik-servis-sablon-logolari')
                            ->image()
                            ->maxSize(4096),
                        Forms\Components\Toggle::make('aktif')
                            ->label('Aktif')
                            ->default(true),
                        Forms\Components\Toggle::make('varsayilan_mi')
                            ->label('Varsayılan şablon'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Şablon içeriği')
                    ->schema([
                        Forms\Components\Textarea::make('sablon_html')
                            ->label('Belge HTML içeriği')
                            ->helperText('HTML kaynak kodunu doğrudan düzenleyin. Alan canlı önizlemeye yansır.')
                            ->rows(32)
                            ->autosize()
                            ->extraInputAttributes([
                                'class' => 'font-mono text-xs leading-relaxed',
                                'spellcheck' => 'false',
                                'wrap' => 'off',
                            ])
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('sablon_css')
                            ->label('CSS')
                            ->rows(18)
                            ->autosize()
                            ->extraInputAttributes([
                                'class' => 'font-mono text-xs leading-relaxed',
                                'spellcheck' => 'false',
                                'wrap' => 'off',
                            ]),
                        Forms\Components\Placeholder::make('anahtarlar')
                            ->label('Kullanılabilir anahtarlar')
                            ->content('{{FIRMA_UNVAN}}, {{FIRMA_TELEFON}}, {{FIRMA_EPOSTA}}, {{FIRMA_ADRES}}, {{FIRMA_VERGI_NO}}, {{FIRMA_LOGO}}, {{SERVIS_NO}}, {{KABUL_TARIHI}}, {{TESLIM_TARIHI}}, {{TAHMINI_TESLIM}}, {{MUSTERI_AD}}, {{MUSTERI_TC_NO}}, {{MUSTERI_TEL}}, {{MUSTERI_ADRES}}, {{CIHAZ}}, {{CIHAZ_TURU}}, {{MARKA}}, {{MODEL_NO}}, {{SERI_NO}}, {{CIHAZ_SIFRESI}}, {{AKSESUARLAR}}, {{FIZIKSEL_DURUM}}, {{IC_SERVIS_NOTU}}, {{CIHAZ_FOTOGRAFLARI}}, {{CIHAZ_FOTOGRAFLARI_BLOKU}}, {{SERVIS_DURUMU}}, {{ARIZA_ACIKLAMASI}}, {{TESLIM_NOTU}}, {{TESLIM_NOTU_BLOKU}}, {{TESPIT_UCRETI}}, {{TOPLAM_TUTAR}}, {{SEHIR}}, {{YAPILAN_ISLEMLER}}, {{PARCA_BILGISI}}, {{MUSTERI_ONAY_DURUMU}}, {{ONAY_NOTU}}, {{STOK_KALEMLERI_TABLOSU}}, {{TOPLAM_OZETI}}, {{ODEME_OZETI}}, {{TAHSILAT_TABLOSU}}'),
                    ]),
            ] : [])
            ->statePath('data');
    }

    public function yeniSablon(): void
    {
        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        $hazir = Arr::first(app(TeknikServisBaskiSablonuServisi::class)->hazirSablonlar(static::sablonTuru()));
        if (! is_array($hazir)) {
            return;
        }

        $this->duzenlenenSablonId = null;
        $this->duzenleyiciAcik = true;
        $this->form->fill([
            'ad' => static::sayfaBasligi().' Yeni Şablon',
            'kod' => app(TeknikServisBaskiSablonuServisi::class)->benzersizKodUret($firmaId, static::sablonTuru(), static::sayfaBasligi()),
            'sayfa_tipi' => 'a4',
            'aktif' => true,
            'varsayilan_mi' => false,
            'sablon_logo' => null,
            'sablon_html' => (string) $hazir['sablon_html'],
            'sablon_css' => (string) $hazir['sablon_css'],
        ]);
    }

    protected function bosSablonFormuHazirla(): void
    {
        $this->duzenlenenSablonId = null;
        $this->duzenleyiciAcik = false;
        $this->data = [
            'ad' => '',
            'kod' => '',
            'sayfa_tipi' => 'a4',
            'aktif' => true,
            'varsayilan_mi' => false,
            'sablon_logo' => null,
            'sablon_html' => '',
            'sablon_css' => '',
        ];
    }

    public function demoSablonlariGeriYukle(): void
    {
        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        $servis = app(TeknikServisBaskiSablonuServisi::class);
        $hazirSablonlar = $servis->hazirSablonlar(static::sablonTuru());
        $eklenen = 0;
        $guncellenen = 0;

        foreach ($hazirSablonlar as $hazir) {
            $mevcut = TeknikServisBaskiSablonu::query()
                ->where('firma_id', $firmaId)
                ->where('sablon_turu', static::sablonTuru())
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
                TeknikServisBaskiSablonu::query()->create([
                    'firma_id' => $firmaId,
                    'sablon_turu' => static::sablonTuru(),
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

        if (! $this->varsayilanSablonVarMi($firmaId)) {
            $ilk = TeknikServisBaskiSablonu::query()
                ->where('firma_id', $firmaId)
                ->where('sablon_turu', static::sablonTuru())
                ->where('kod', static::sablonTuru().'-a4')
                ->first();

            if (! $ilk) {
                $ilk = TeknikServisBaskiSablonu::query()
                    ->where('firma_id', $firmaId)
                    ->where('sablon_turu', static::sablonTuru())
                    ->orderBy('id')
                    ->first();
            }

            if ($ilk) {
                $servis->varsayilanYap($ilk);
            }
        }

        Notification::make()
            ->title('Demo şablonlar geri yüklendi')
            ->body('Eklenen: '.$eklenen.' | Güncellenen: '.$guncellenen)
            ->success()
            ->send();
    }

    public function duzenle(int $id): void
    {
        $sablon = TeknikServisBaskiSablonu::query()
            ->where('firma_id', (int) ($this->aktifFirmaId() ?? 0))
            ->where('sablon_turu', static::sablonTuru())
            ->whereKey($id)
            ->firstOrFail();

        $this->duzenlenenSablonId = (int) $sablon->id;
        $this->duzenleyiciAcik = true;
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
        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        $state = $this->form->getState();

        $validated = validator($state, [
            'ad' => ['required', 'string', 'max:191'],
            'kod' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'sayfa_tipi' => ['required', 'in:a4,a5,80mm,58mm,10x10mm'],
            'sablon_html' => ['required', 'string'],
            'sablon_css' => ['nullable', 'string'],
            'sablon_logo' => ['nullable', 'string', 'max:255'],
            'aktif' => ['nullable', 'boolean'],
            'varsayilan_mi' => ['nullable', 'boolean'],
        ], [
            'kod.regex' => 'Kod yalnızca küçük harf, rakam ve tire içerebilir.',
        ])->validate();

        $kodVar = TeknikServisBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('sablon_turu', static::sablonTuru())
            ->where('kod', (string) $validated['kod'])
            ->when($this->duzenlenenSablonId, fn ($q) => $q->where('id', '!=', (int) $this->duzenlenenSablonId))
            ->exists();

        if ($kodVar) {
            Notification::make()->title('Kod zaten kullanılıyor')->danger()->send();

            return;
        }

        $sablon = TeknikServisBaskiSablonu::query()->updateOrCreate(
            [
                'id' => $this->duzenlenenSablonId,
                'firma_id' => $firmaId,
            ],
            [
                'sablon_turu' => static::sablonTuru(),
                'ad' => (string) $validated['ad'],
                'kod' => (string) $validated['kod'],
                'sayfa_tipi' => (string) $validated['sayfa_tipi'],
                'sablon_logo' => blank((string) ($validated['sablon_logo'] ?? '')) ? null : (string) $validated['sablon_logo'],
                'sablon_html' => (string) $validated['sablon_html'],
                'sablon_css' => (string) ($validated['sablon_css'] ?? ''),
                'aktif' => (bool) ($validated['aktif'] ?? true),
            ]
        );

        $servis = app(TeknikServisBaskiSablonuServisi::class);
        if ((bool) ($validated['varsayilan_mi'] ?? false) || ! $this->varsayilanSablonVarMi($firmaId)) {
            $servis->varsayilanYap($sablon);
        }

        $this->duzenlenenSablonId = (int) $sablon->id;
        $this->duzenle((int) $sablon->id);

        Notification::make()->title('Şablon kaydedildi')->success()->send();
    }

    public function varsayilanYap(int $id): void
    {
        $sablon = TeknikServisBaskiSablonu::query()
            ->where('firma_id', (int) ($this->aktifFirmaId() ?? 0))
            ->where('sablon_turu', static::sablonTuru())
            ->whereKey($id)
            ->firstOrFail();

        app(TeknikServisBaskiSablonuServisi::class)->varsayilanYap($sablon);

        if ($this->duzenlenenSablonId === (int) $sablon->id) {
            $this->data['varsayilan_mi'] = true;
        }

        Notification::make()->title('Varsayılan şablon güncellendi')->success()->send();
    }

    public function kopyala(int $id): void
    {
        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        $sablon = TeknikServisBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('sablon_turu', static::sablonTuru())
            ->whereKey($id)
            ->firstOrFail();

        $servis = app(TeknikServisBaskiSablonuServisi::class);
        $yeniAd = $this->sonrakiKopyaAdi($firmaId, (string) $sablon->ad);

        $kopya = TeknikServisBaskiSablonu::query()->create([
            'firma_id' => $firmaId,
            'sablon_turu' => static::sablonTuru(),
            'ad' => $yeniAd,
            'kod' => $servis->benzersizKodUret($firmaId, static::sablonTuru(), $yeniAd),
            'sayfa_tipi' => (string) $sablon->sayfa_tipi,
            'sablon_logo' => blank((string) ($sablon->sablon_logo ?? '')) ? null : (string) $sablon->sablon_logo,
            'sablon_html' => (string) $sablon->sablon_html,
            'sablon_css' => (string) ($sablon->sablon_css ?? ''),
            'varsayilan_mi' => false,
            'aktif' => (bool) $sablon->aktif,
        ]);

        $this->duzenle((int) $kopya->id);

        Notification::make()
            ->title('Şablon kopyalandı')
            ->body($yeniAd.' oluşturuldu.')
            ->success()
            ->send();
    }

    public function sil(int $id): void
    {
        $sablon = TeknikServisBaskiSablonu::query()
            ->where('firma_id', (int) ($this->aktifFirmaId() ?? 0))
            ->where('sablon_turu', static::sablonTuru())
            ->whereKey($id)
            ->firstOrFail();

        if ((bool) $sablon->varsayilan_mi) {
            Notification::make()
                ->title('Varsayılan şablon silinemez')
                ->body('Önce farklı bir şablonu varsayılan yapın.')
                ->warning()
                ->send();

            return;
        }

        $sablon->delete();

        if ($this->duzenlenenSablonId === $id) {
            $this->yeniSablon();
        }

        Notification::make()->title('Şablon silindi')->success()->send();
    }

    public function sablonLogoKaydet(int $id): void
    {
        $sablon = TeknikServisBaskiSablonu::query()
            ->where('firma_id', (int) ($this->aktifFirmaId() ?? 0))
            ->where('sablon_turu', static::sablonTuru())
            ->whereKey($id)
            ->firstOrFail();

        $yukleme = $this->sablonLogoYuklemeleri[$id] ?? null;
        if (! $yukleme instanceof TemporaryUploadedFile) {
            Notification::make()
                ->title('Logo görseli seçin')
                ->warning()
                ->send();

            return;
        }

        validator(
            ['logo' => $yukleme],
            ['logo' => ['required', 'image', 'max:4096']]
        )->validate();

        $eskiLogo = trim((string) ($sablon->sablon_logo ?? ''));
        $yeniLogo = $yukleme->store('teknik-servis-sablon-logolari', 'public');

        $sablon->forceFill([
            'sablon_logo' => $yeniLogo,
        ])->save();

        if ($eskiLogo !== '' && $eskiLogo !== $yeniLogo && ! str_starts_with($eskiLogo, 'http')) {
            try {
                Storage::disk('public')->delete($eskiLogo);
            } catch (\Throwable) {
                // Eski dosya silinemezse yeni logo kullanımını engellemeyelim.
            }
        }

        $this->sablonLogoYuklemeleri[$id] = null;

        if ($this->duzenlenenSablonId === $id) {
            $this->data['sablon_logo'] = $yeniLogo;
        }

        Notification::make()
            ->title('Şablon logosu güncellendi')
            ->success()
            ->send();
    }

    public function sablonLogoSil(int $id): void
    {
        $sablon = TeknikServisBaskiSablonu::query()
            ->where('firma_id', (int) ($this->aktifFirmaId() ?? 0))
            ->where('sablon_turu', static::sablonTuru())
            ->whereKey($id)
            ->firstOrFail();

        $eskiLogo = trim((string) ($sablon->sablon_logo ?? ''));
        if ($eskiLogo === '') {
            Notification::make()
                ->title('Silinecek logo bulunamadı')
                ->warning()
                ->send();

            return;
        }

        $sablon->forceFill([
            'sablon_logo' => null,
        ])->save();

        if (! str_starts_with($eskiLogo, 'http')) {
            try {
                Storage::disk('public')->delete($eskiLogo);
            } catch (\Throwable) {
                // Dosya silinemezse veri tarafındaki temizliği geri almayalım.
            }
        }

        $this->sablonLogoYuklemeleri[$id] = null;

        if ($this->duzenlenenSablonId === $id) {
            $this->data['sablon_logo'] = null;
        }

        Notification::make()
            ->title('Şablon logosu silindi')
            ->success()
            ->send();
    }

    /**
     * @return array<int, TeknikServisBaskiSablonu>
     */
    public function sablonlar(): array
    {
        $query = TeknikServisBaskiSablonu::query()
            ->where('firma_id', (int) ($this->aktifFirmaId() ?? 0))
            ->where('sablon_turu', static::sablonTuru())
            ->orderByDesc('varsayilan_mi')
            ->orderBy('sayfa_tipi')
            ->orderBy('ad');

        if (static::sablonTuru() === 'talep_formu') {
            $query->where('kod', '!=', 'teknik-servis-formu-a4');
        }

        return $query
            ->select([
                'id',
                'firma_id',
                'sablon_turu',
                'ad',
                'kod',
                'sayfa_tipi',
                'sablon_logo',
                'varsayilan_mi',
                'aktif',
            ])
            ->get()
            ->all();
    }

    public function onizlemeHtmlCikti(): Htmlable
    {
        $state = $this->data;
        $html = (string) ($state['sablon_html'] ?? '');
        if (trim($html) === '') {
            return new HtmlString('<div class="text-sm text-gray-600">Ön izleme için şablon içeriği girin.</div>');
        }

        $logoUrl = $this->stateLogoUrl($state['sablon_logo'] ?? null) ?: $this->firmaLogoUrl();
        $tokens = [
            '{{FIRMA_UNVAN}}' => e('Yalova Bilgisayar Teknik Servis'),
            '{{FIRMA_TELEFON}}' => e('0 (226) 352 07 24'),
            '{{FIRMA_EPOSTA}}' => e('info@yalovabilgisayar.com'),
            '{{FIRMA_ADRES}}' => e('Sahil Mah. Yalı Cad. No:3/A Çiftlikköy/Yalova'),
            '{{FIRMA_VERGI_NO}}' => e('45199618384'),
            '{{FIRMA_LOGO}}' => $logoUrl ? '<img src="'.e($logoUrl).'" alt="Şablon logosu">' : '',
            '{{SERVIS_NO}}' => e('TS-ONIZLEME-001'),
            '{{KABUL_TARIHI}}' => e(now()->format('d.m.Y H:i')),
            '{{TESLIM_TARIHI}}' => e(now()->format('d.m.Y H:i')),
            '{{TAHMINI_TESLIM}}' => e(now()->addDays(2)->format('d.m.Y H:i')),
            '{{MUSTERI_AD}}' => e('Örnek Müşteri'),
            '{{MUSTERI_TC_NO}}' => e('11111111111'),
            '{{MUSTERI_TEL}}' => e('+90 (555) 000 00 00'),
            '{{MUSTERI_ADRES}}' => e('Mustafa Kemal Paşa Mah. Örnek Sok. No: 12 Yalova'),
            '{{CIHAZ}}' => e('Laptop'),
            '{{CIHAZ_TURU}}' => e('Notebook Bilgisayar'),
            '{{MARKA}}' => e('Lenovo'),
            '{{MODEL_NO}}' => e('ThinkPad E14'),
            '{{SERI_NO}}' => e('SN123456789'),
            '{{CIHAZ_SIFRESI}}' => e('Belirtilmedi'),
            '{{IC_SERVIS_NOTU}}' => e('Teknisyen notu: ilk açılışta fan sesi ve sıcaklık kontrol edilecek.'),
            '{{AKSESUARLAR}}' => e('Adaptör, çanta, şarj kablosu'),
            '{{FIZIKSEL_DURUM}}' => e('Kasa üzerinde hafif çizikler mevcut, ekran sağlam, köşe kısmında darbe izi var.'),
            '{{CIHAZ_FOTOGRAFLARI}}' => '
                <div class="sk-photo-gallery-wrap">
                    <div class="sk-photo-gallery-title">Cihaz Fotoğrafları</div>
                    <div class="sk-photo-gallery">
                        <div class="sk-photo-item"><div class="sk-photo-placeholder">Fotoğraf 1</div></div>
                        <div class="sk-photo-item"><div class="sk-photo-placeholder">Fotoğraf 2</div></div>
                        <div class="sk-photo-item"><div class="sk-photo-placeholder">Fotoğraf 3</div></div>
                        <div class="sk-photo-item"><div class="sk-photo-placeholder">Fotoğraf 4</div></div>
                    </div>
                </div>
            ',
            '{{CIHAZ_FOTOGRAFLARI_BLOKU}}' => '
                <div class="kts-box">
                    <div class="kts-box-title">Görsel Kayıt</div>
                    <div class="sk-photo-gallery-wrap">
                        <div class="sk-photo-gallery-title">Cihaz Fotoğrafları</div>
                        <div class="sk-photo-gallery">
                            <div class="sk-photo-item"><div class="sk-photo-placeholder">Fotoğraf 1</div></div>
                            <div class="sk-photo-item"><div class="sk-photo-placeholder">Fotoğraf 2</div></div>
                            <div class="sk-photo-item"><div class="sk-photo-placeholder">Fotoğraf 3</div></div>
                            <div class="sk-photo-item"><div class="sk-photo-placeholder">Fotoğraf 4</div></div>
                        </div>
                    </div>
                </div>
            ',
            '{{SERVIS_DURUMU}}' => e('Teslim Bekleyen'),
            '{{ARIZA_ACIKLAMASI}}' => e('Açılmıyor, fan sesi yüksek ve bakım talebi mevcut.'),
            '{{TESLIM_NOTU}}' => e('Cihaz tesliminde adaptör ve çanta müşteriye iade edilecektir.'),
            '{{TESLIM_NOTU_BLOKU}}' => '
                <div class="tsf-box tsf-full">
                    <div class="tsf-box-title">Teslim Notu</div>
                    <div>Cihaz tesliminde adaptör ve çanta müşteriye iade edilecektir.</div>
                </div>
            ',
            '{{TESPIT_UCRETI}}' => e('750,00 TL'),
            '{{TOPLAM_TUTAR}}' => e('1.500,00 TRY'),
            '{{SEHIR}}' => e('Yalova'),
            '{{YAPILAN_ISLEMLER}}' => '
                <ul style="margin:0; padding-left:18px;">
                    <li>Cihaz ic temizlik islemi yapildi.</li>
                    <li>Sogutma sistemi bakimi tamamlandi.</li>
                    <li>Arizali guc giris soketi degistirildi.</li>
                    <li>Son testler basariyla tamamlandi.</li>
                </ul>
            ',
            '{{PARCA_BILGISI}}' => e('DC Power Jack x1, Termal Macun x2, Fan Temizligi x2'),
            '{{MUSTERI_ONAY_DURUMU}}' => e('Telefon ile onaylandı'),
            '{{ONAY_NOTU}}' => e('30.04.2026 11:45 tarihinde telefonla onay verildi. Parça değişimi kabul edildi.'),
            '{{STOK_KALEMLERI_TABLOSU}}' => '
                <table class="tsf-table">
                    <thead>
                        <tr>
                            <th>Kalem</th>
                            <th>Aciklama</th>
                            <th class="is-right">Miktar</th>
                            <th class="is-right">Birim Fiyat</th>
                            <th class="is-right">İskonto Tutarı</th>
                            <th class="is-right">Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Fan Temizligi</td>
                            <td>Bakim ve sogutma bakimi hizmeti</td>
                            <td class="is-right">2</td>
                            <td class="is-right">175,00 TRY</td>
                            <td class="is-right">0,00 TRY</td>
                            <td class="is-right">350,00 TRY</td>
                        </tr>
                        <tr>
                            <td>DC Power Jack</td>
                            <td>Guc soketi degisimi</td>
                            <td class="is-right">1</td>
                            <td class="is-right">650,00 TRY</td>
                            <td class="is-right">50,00 TRY</td>
                            <td class="is-right">600,00 TRY</td>
                        </tr>
                        <tr>
                            <td>Termal Macun</td>
                            <td>Yuksek performansli termal uygulama</td>
                            <td class="is-right">2</td>
                            <td class="is-right">125,00 TRY</td>
                            <td class="is-right">0,00 TRY</td>
                            <td class="is-right">250,00 TRY</td>
                        </tr>
                    </tbody>
                </table>
            ',
            '{{TOPLAM_OZETI}}' => '
                <div class="tsf-summary">
                    <div class="tsf-summary-row"><span>Mal/Hizmet Toplami</span><strong>1.250,00 TRY</strong></div>
                    <div class="tsf-summary-row"><span>Toplam Iskonto</span><strong>50,00 TRY</strong></div>
                    <div class="tsf-summary-row"><span>KDV Haric Toplam</span><strong>1.200,00 TRY</strong></div>
                </div>
            ',
            '{{ODEME_OZETI}}' => '
                    <div class="tsf-summary">
                        <div class="tsf-summary-row"><span>Toplam Tutar</span><strong>1.440,00 TRY</strong></div>
                        <div class="tsf-summary-row"><span>Odenen</span><strong>1.000,00 TRY</strong></div>
                        <div class="tsf-summary-row"><span>Odeme Durumu</span><strong>Kismen Tahsil Edildi</strong></div>
                    </div>
            ',
            '{{TAHSILAT_TABLOSU}}' => '
                <table class="tsf-table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Kanal</th>
                            <th>Aciklama</th>
                            <th class="is-right">Tutar</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>27.04.2026 14:20</td>
                            <td>Kasa</td>
                            <td>Pesin tahsilat</td>
                            <td class="is-right">750,00 TRY</td>
                            <td>Aktif</td>
                        </tr>
                        <tr>
                            <td>27.04.2026 17:45</td>
                            <td>POS</td>
                            <td>Kart odemesi</td>
                            <td class="is-right">250,00 TRY</td>
                            <td>Aktif</td>
                        </tr>
                    </tbody>
                </table>
            ',
        ];

        return new HtmlString(strtr($html, $tokens));
    }

    public function onizlemeCss(): string
    {
        return (string) ($this->data['sablon_css'] ?? '');
    }

    public function onizlemeKapsayiciStili(): string
    {
        return match ((string) ($this->data['sayfa_tipi'] ?? 'a4')) {
            'a4' => 'max-width: 210mm; min-height: 297mm;',
            'a5' => 'max-width: 148mm; min-height: 210mm;',
            '58mm' => 'max-width: 58mm; min-height: 100mm;',
            '10x10mm' => 'max-width: 10mm; min-height: 10mm; padding: 0;',
            default => 'max-width: 80mm; min-height: 100mm;',
        };
    }

    public function onizlemeSagBosluk(): string
    {
        return match ((string) ($this->data['sayfa_tipi'] ?? 'a4')) {
            '58mm' => '2mm',
            '80mm' => '3mm',
            default => '0mm',
        };
    }

    protected function aktifFirmaId(): ?int
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);

        if ($firmaId > 0) {
            return $firmaId;
        }

        $varsayilanFirmaId = (int) Firma::query()->orderBy('id')->value('id');

        return $varsayilanFirmaId > 0 ? $varsayilanFirmaId : null;
    }

    private function varsayilanSablonVarMi(int $firmaId): bool
    {
        return TeknikServisBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('sablon_turu', static::sablonTuru())
            ->where('varsayilan_mi', true)
            ->exists();
    }

    private function firmaSablonuVarMi(int $firmaId): bool
    {
        return Cache::remember(
            'teknik-servis:baski-sablonu-var:v1:'.$firmaId.':'.static::sablonTuru(),
            3600,
            fn (): bool => TeknikServisBaskiSablonu::query()
                ->where('firma_id', $firmaId)
                ->where('sablon_turu', static::sablonTuru())
                ->exists()
        );
    }

    private function sonrakiKopyaAdi(int $firmaId, string $kaynakAd): string
    {
        $temelAd = preg_replace('/ Kopya \d+$/u', '', trim($kaynakAd)) ?: trim($kaynakAd);
        $desen = '/^'.preg_quote($temelAd, '/').' Kopya (\d+)$/u';

        $enYuksek = TeknikServisBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('sablon_turu', static::sablonTuru())
            ->pluck('ad')
            ->map(function ($ad) use ($desen): int {
                $ad = trim((string) $ad);

                if (! preg_match($desen, $ad, $eslesme)) {
                    return 0;
                }

                return (int) ($eslesme[1] ?? 0);
            })
            ->max() ?? 0;

        return $temelAd.' Kopya '.($enYuksek + 1);
    }

    private function firmaLogoUrl(): ?string
    {
        if ($this->firmaLogoUrlCache !== null) {
            return $this->firmaLogoUrlCache;
        }

        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return null;
        }

        $logo = (string) (app(FirmaAyarDeposu::class)->oku($firmaId, 'logo', '') ?? '');

        $this->firmaLogoUrlCache = $this->dosyaUrlHazirla($logo) ?: $this->dosyaUrlHazirla(self::DEFAULT_LOGO_PATH);

        return $this->firmaLogoUrlCache;
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

    private function dosyaUrlHazirla(?string $yol): ?string
    {
        $yol = trim((string) $yol);
        if ($yol === '') {
            return null;
        }

        if (str_starts_with($yol, 'http://') || str_starts_with($yol, 'https://')) {
            return $yol;
        }

        try {
            return Storage::disk('public')->url($yol);
        } catch (\Throwable) {
            return null;
        }
    }

    public function sablonLogoUrl(TeknikServisBaskiSablonu $sablon): ?string
    {
        $id = (int) $sablon->id;
        if (array_key_exists($id, $this->sablonLogoUrlCache)) {
            return $this->sablonLogoUrlCache[$id];
        }

        $this->sablonLogoUrlCache[$id] = $this->dosyaUrlHazirla((string) ($sablon->sablon_logo ?? '')) ?: $this->firmaLogoUrl();

        return $this->sablonLogoUrlCache[$id];
    }
}
