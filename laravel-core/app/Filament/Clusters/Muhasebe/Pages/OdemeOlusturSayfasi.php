<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Proje\IsletmeProjesi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\ParaBirimi;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\DovizKurServisi;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\Services\ModulErisimService;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class OdemeOlusturSayfasi extends Page implements HasForms
{
    use InteractsWithForms;
    use MuhasebeSayfaErisimleri;

    public ?array $data = [];

    private bool $aktifFirmaIdCozuldu = false;

    private ?int $aktifFirmaIdCache = null;

    /** @var array<string, array<int|string, string>> */
    private array $hesapSecenekleriCache = [];

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Odeme';

    protected static ?string $slug = 'finans/odeme-olustur';

    protected static string $view = 'filament.clusters.muhasebe.pages.tahsilat-odeme-form';

    public function getHeading(): string|Htmlable
    {
        return 'Odeme olustur';
    }

    public function getSubheading(): ?string
    {
        return 'Kaynak/hedef para birimi, kur ve donusen hedef tutar ile odeme kaydi.';
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_OLUSTUR;
    }

    private function aktifFirmaId(): ?int
    {
        if (! $this->aktifFirmaIdCozuldu) {
            $firmaId = app(TenantContextService::class)->aktifFirmaId();
            $this->aktifFirmaIdCache = $firmaId ? (int) $firmaId : null;
            $this->aktifFirmaIdCozuldu = true;
        }

        return $this->aktifFirmaIdCache;
    }

    public function mount(): void
    {
        $firmaId = $this->aktifFirmaId();
        $fill = [
            'kanal' => 'kasa',
            'tarih' => now()->format('Y-m-d H:i'),
            'tutar' => null,
            'aciklama' => null,
            'cari_id' => null,
            'fatura_id' => null,
            'isletme_proje_id' => null,
            'kaynak_para_birimi' => 'TRY',
            'hedef_para_birimi' => null,
            'doviz_kuru_turu' => 'otomatik',
            'doviz_kuru' => null,
            'hedef_tutar' => null,
            'kasa_hesap_id' => null,
            'banka_hesap_id' => null,
        ];

        $faturaId = (int) request()->query('fatura_id', 0);
        if ($faturaId > 0 && $firmaId) {
            $f = Fatura::query()->whereKey($faturaId)->where('firma_id', $firmaId)->first();
            if ($f && $f->cari_id && $this->faturaOdemeyeUygunMu($f)) {
                $fill['fatura_id'] = $f->id;
                $fill['cari_id'] = $f->cari_id;
                $fill['tutar'] = (string) ($f->acik_tutar ?? '0');
                $fill['hedef_para_birimi'] = strtoupper((string) ($f->para_birimi ?: 'TRY'));
                $fill['isletme_proje_id'] = $f->isletme_proje_id;
            }
        } else {
            $cariId = (int) request()->query('cari_id', 0);
            if ($cariId > 0 && $firmaId) {
                $c = Cari::query()->whereKey($cariId)->where('firma_id', $firmaId)->first();
                if ($c) {
                    $fill['cari_id'] = $c->id;
                    $fill['hedef_para_birimi'] = strtoupper((string) ($c->para_birimi ?: 'TRY'));
                }
            }
        }

        $this->form->fill($fill);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Odeme')
                    ->schema([
                        Forms\Components\Radio::make('kanal')
                            ->label('Odeme kanali')
                            ->options([
                                'kasa' => 'Kasa',
                                'banka' => 'Banka',
                            ])
                            ->required()
                            ->live()
                            ->inline()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('kasa_hesap_id', null);
                                $set('banka_hesap_id', null);
                                $set('kaynak_para_birimi', null);
                                $set('doviz_kuru', null);
                                $set('hedef_tutar', null);
                            }),

                        Forms\Components\Select::make('kasa_hesap_id')
                            ->label('Kasa hesabi')
                            ->options(fn (Get $get): array => ($get('kanal') ?? 'kasa') === 'kasa' ? $this->hesapSecenekleri('kasa') : [])
                            ->visible(fn (Get $get): bool => ($get('kanal') ?? '') === 'kasa')
                            ->required(fn (Get $get): bool => ($get('kanal') ?? '') === 'kasa')
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Get $get): void {
                                $this->kaynakParaBirimiGuncelle('kasa', (int) $state, $set, $get);
                            }),

                        Forms\Components\Select::make('banka_hesap_id')
                            ->label('Banka hesabi')
                            ->options(fn (Get $get): array => ($get('kanal') ?? '') === 'banka' ? $this->hesapSecenekleri('banka') : [])
                            ->visible(fn (Get $get): bool => ($get('kanal') ?? '') === 'banka')
                            ->required(fn (Get $get): bool => ($get('kanal') ?? '') === 'banka')
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Get $get): void {
                                $this->kaynakParaBirimiGuncelle('banka', (int) $state, $set, $get);
                            }),

                        Forms\Components\Select::make('cari_id')
                            ->label('Cari')
                            ->searchable()
                            ->options(fn (): array => $this->cariAramaSonuclari(''))
                            ->optionsLimit(50)
                            ->getSearchResultsUsing(fn (string $search): array => $this->cariAramaSonuclari($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $this->cariEtiketi($value))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Get $get): void {
                                if (! $state) {
                                    return;
                                }
                                $c = Cari::query()->find((int) $state);
                                if ($c) {
                                    $set('hedef_para_birimi', strtoupper((string) ($c->para_birimi ?: 'TRY')));
                                    $set('doviz_kuru', null);
                                    $this->otomatikKurDoldur($get, $set);
                                    $this->hedefTutarGuncelle($get, $set);
                                }
                            }),

                        Forms\Components\Select::make('isletme_proje_id')
                            ->label('İşletme projesi')
                            ->placeholder('Projeye bağlama (isteğe bağlı)')
                            ->searchable()
                            ->options(fn (): array => $this->projeSecenekleri())
                            ->helperText('Ödemeyi proje gider/finans raporlarına dahil eder.')
                            ->visible(fn (): bool => $this->projeModuluAktifMi()),

                        Forms\Components\Placeholder::make('proje_yeri')
                            ->label('')
                            ->content('')
                            ->visible(fn (): bool => ! $this->projeModuluAktifMi())
                            ->extraAttributes(['class' => 'hidden md:block']),

                        Forms\Components\TextInput::make('kaynak_para_birimi')
                            ->label('Kaynak para birimi')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('tutar')
                            ->label('Tutar')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->step('0.01')
                            ->minValue(0.01)
                            ->afterStateUpdated(fn (Get $get, Forms\Set $set) => $this->hedefTutarGuncelle($get, $set)),

                        Forms\Components\TextInput::make('hedef_para_birimi')
                            ->label('Hedef para birimi')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('hedef_tutar')
                            ->label('Ödenecek Tutar')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0.01)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Forms\Set $set) => $this->kurGuncelleHedefTutardan($get, $set))
                            ->suffix(fn (Get $get): string => strtoupper((string) ($get('hedef_para_birimi') ?? '')))
                            ->visible(fn (Get $get): bool => $this->farkliParaBirimiSeciliMi($get)),

                        Forms\Components\DateTimePicker::make('tarih')
                            ->label('İşlem tarihi')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Forms\Set $set): void {
                                $this->otomatikKurDoldur($get, $set);
                            })
                            ->seconds(false)
                            ->hintActions([
                                Forms\Components\Actions\Action::make('kur_cek_tarih')
                                    ->label('Kur çek')
                                    ->icon('heroicon-o-bolt')
                                    ->color('warning')
                                    ->action(fn (Get $get, Forms\Set $set) => $this->otomatikKurDoldur($get, $set)),
                            ])
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('simdi')
                                    ->label('Şimdi')
                                    ->icon('heroicon-o-clock')
                                    ->color('success')
                                    ->action(function (Get $get, Forms\Set $set): void {
                                        $set('tarih', now()->format('Y-m-d H:i'));
                                        $this->otomatikKurDoldur($get, $set);
                                    })
                            ),

                        Forms\Components\Select::make('doviz_kuru_turu')
                            ->label('Kur turu')
                            ->options([
                                'otomatik' => 'Otomatik cek',
                                'manuel' => 'Manuel',
                            ])
                            ->default('otomatik')
                            ->hidden()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Forms\Set $set): void {
                                $this->otomatikKurDoldur($get, $set);
                                $this->hedefTutarGuncelle($get, $set);
                            })
                            ->visible(fn (Get $get): bool => $this->farkliParaBirimiSeciliMi($get)),

                        Forms\Components\TextInput::make('doviz_kuru')
                            ->label('Kur')
                            ->numeric()
                            ->step('0.00000001')
                            ->minValue(0.00000001)
                            ->helperText(fn (Get $get): string => $this->kurGosterimMetni($get))
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Forms\Set $set) => $this->hedefTutarGuncelle($get, $set))
                            ->required(fn (Get $get): bool => $this->farkliParaBirimiSeciliMi($get))
                            ->visible(fn (Get $get): bool => $this->farkliParaBirimiSeciliMi($get))
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('kur_cek')
                                    ->label('Kur cek')
                                    ->icon('heroicon-o-bolt')
                                    ->color('warning')
                                    ->action(fn (Get $get, Forms\Set $set) => $this->otomatikKurDoldur($get, $set))
                            ),

                        Forms\Components\Textarea::make('aciklama')
                            ->label('Aciklama')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('fatura_id')
                            ->label('Referans fatura (opsiyonel)')
                            ->options(fn (Get $get): array => $this->acikBorcFaturaSecenekleri((int) ($get('cari_id') ?? 0)))
                            ->searchable()
                            ->live()
                            ->helperText('Borc yonundeki onayli faturalar listelenir.')
                            ->afterStateUpdated(function ($state, Forms\Set $set, Get $get): void {
                                if (! $state) {
                                    return;
                                }
                                $f = Fatura::query()->find((int) $state);
                                if ($f && bccomp((string) ($f->acik_tutar ?? '0'), '0', 2) > 0) {
                                    $acik = (string) $f->acik_tutar;
                                    $set('hedef_para_birimi', strtoupper((string) ($f->para_birimi ?: 'TRY')));
                                    if (! filled($get('tutar')) || bccomp((string) $get('tutar'), '0', 2) <= 0) {
                                        $set('tutar', $acik);
                                    }
                                }
                            }),

                        Forms\Components\Placeholder::make('kapama_onerisi')
                            ->label('Kapama onerisi')
                            ->content(function (Get $get): HtmlString {
                                $fid = (int) ($get('fatura_id') ?? 0);
                                $tutar = (string) ($get('tutar') ?? '0');
                                if ($fid < 1) {
                                    return new HtmlString('<span class="text-sm text-gray-500">Fatura secildiginde acik tutar ve onerilen tutar gosterilir.</span>');
                                }
                                $f = Fatura::query()->find($fid);
                                if (! $f) {
                                    return new HtmlString('-');
                                }
                                $acik = (string) ($f->acik_tutar ?? '0');
                                $oneri = bccomp($tutar, $acik, 2) === 1 ? $acik : $tutar;
                                $pb = strtoupper((string) ($f->para_birimi ?: 'TRY'));

                                return new HtmlString(
                                    '<p class="text-sm">Fatura acik: <strong>'.e($acik).' '.e($pb).'</strong></p>'
                                    .'<p class="text-sm text-gray-600 dark:text-gray-400">Onerilen kapama tutari: <strong>'.e($oneri).' '.e($pb).'</strong>.</p>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function cariSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return [];
        }

        return Cache::remember(
            'muhasebe:odeme:cari-secenekleri:v1:'.$firmaId,
            now()->addSeconds(60),
            fn (): array => Cari::query()
                ->where('firma_id', $firmaId)
                ->where('durum', CariDurumu::Aktif)
                ->orderBy('ad')
                ->get(['id', 'ad', 'kod'])
                ->mapWithKeys(fn (Cari $c): array => [
                    $c->id => ($c->kod ? $c->kod.' - ' : '').$c->ad,
                ])
                ->all()
        );
    }

    /**
     * @return array<int, string>
     */
    private function cariAramaSonuclari(string $search): array
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return [];
        }

        return Cari::query()
            ->where('firma_id', $firmaId)
            ->where('durum', CariDurumu::Aktif)
            ->when(trim($search) !== '', function ($query) use ($search): void {
                $aranan = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';
                $query->where(function ($q) use ($aranan): void {
                    $q->where('ad', 'like', $aranan)
                        ->orWhere('kod', 'like', $aranan)
                        ->orWhere('telefon', 'like', $aranan)
                        ->orWhere('gsm', 'like', $aranan);
                });
            })
            ->orderBy('ad')
            ->limit(50)
            ->get(['id', 'ad', 'kod'])
            ->mapWithKeys(fn (Cari $c): array => [
                (int) $c->id => ($c->kod ? $c->kod.' - ' : '').$c->ad,
            ])
            ->all();
    }

    private function cariEtiketi(mixed $value): ?string
    {
        $id = (int) $value;
        $firmaId = $this->aktifFirmaId();
        if ($id < 1 || ! $firmaId) {
            return null;
        }

        $c = Cache::remember(
            'muhasebe:odeme:cari-etiket:v1:'.$firmaId.':'.$id,
            now()->addSeconds(60),
            fn (): ?Cari => Cari::query()
                ->where('firma_id', $firmaId)
                ->whereKey($id)
                ->first(['id', 'ad', 'kod'])
        );

        return $c ? (($c->kod ? $c->kod.' - ' : '').$c->ad) : null;
    }

    /**
     * @return array<int|string, string>
     */
    private function acikBorcFaturaSecenekleri(int $cariId): array
    {
        if ($cariId < 1) {
            return [];
        }

        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return [];
        }

        $turler = [
            FaturaTuru::Gelen->value,
            FaturaTuru::GelenFatura->value,
            FaturaTuru::Gider->value,
            FaturaTuru::GiderFaturasi->value,
            FaturaTuru::SatisIadesi->value,
        ];

        return Cache::remember(
            'muhasebe:odeme:acik-borc-faturalar:v1:'.$firmaId.':'.$cariId,
            now()->addSeconds(60),
            fn (): array => Fatura::query()
                ->where('firma_id', $firmaId)
                ->where('cari_id', $cariId)
                ->where('durum', FaturaDurumu::Onayli)
                ->whereIn('tur', $turler)
                ->whereRaw('CAST(acik_tutar AS DECIMAL(18,4)) > 0')
                ->orderByDesc('tarih')
                ->limit(100)
                ->get(['id', 'fatura_no', 'acik_tutar', 'para_birimi', 'tarih'])
                ->mapWithKeys(fn (Fatura $f) => [
                    $f->id => ($f->fatura_no ?: '#'.$f->id).' - acik: '.(string) $f->acik_tutar.' '.strtoupper((string) ($f->para_birimi ?: 'TRY')),
                ])
                ->all()
        );
    }

    /**
     * @return array<int|string, string>
     */
    private function hesapSecenekleri(string $tip): array
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return [];
        }

        $requestCacheKey = $firmaId.':'.$tip;
        if (array_key_exists($requestCacheKey, $this->hesapSecenekleriCache)) {
            return $this->hesapSecenekleriCache[$requestCacheKey];
        }

        $model = match ($tip) {
            'kasa' => KasaHesabi::class,
            'banka' => BankaHesabi::class,
            default => KasaHesabi::class,
        };

        return $this->hesapSecenekleriCache[$requestCacheKey] = Cache::remember(
            'muhasebe:odeme:hesap-secenekleri:v1:'.$firmaId.':'.$tip,
            now()->addSeconds(60),
            fn (): array => $model::query()
                ->where('firma_id', $firmaId)
                ->where('durum', HesapDurumu::Aktif)
                ->orderBy('ad')
                ->get(['id', 'ad', 'para_birimi'])
                ->mapWithKeys(function ($hesap): array {
                    $pb = strtoupper((string) ($hesap->para_birimi ?? 'TRY'));

                    return [$hesap->id => $hesap->ad.' ('.$pb.')'];
                })
                ->all()
        );
    }

    /**
     * @return array<string, string>
     */
    private function paraBirimiSecenekleri(): array
    {
        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return [];
        }

        return Cache::remember(
            'muhasebe:odeme:para-birimi-secenekleri:v1:'.$firmaId,
            now()->addSeconds(60),
            fn (): array => ParaBirimi::tenantScopeOlmadan(fn (): array => ParaBirimi::query()
                ->where('aktif_mi', true)
                ->gorunurFirmaIle($firmaId)
                ->orderBy('kod')
                ->get(['kod', 'ad'])
                ->mapWithKeys(function (ParaBirimi $pb): array {
                    $kod = strtoupper((string) $pb->kod);
                    $ad = trim((string) ($pb->ad ?? ''));

                    return [$kod => $ad !== '' ? $kod.' - '.$ad : $kod];
                })
                ->all())
        );
    }

    private function hesapParaBirimi(string $tip, int $hesapId): ?string
    {
        if ($hesapId < 1) {
            return null;
        }

        $model = match ($tip) {
            'kasa' => KasaHesabi::class,
            'banka' => BankaHesabi::class,
            default => KasaHesabi::class,
        };

        $hesap = $model::query()->find($hesapId);

        return $hesap ? strtoupper((string) $hesap->para_birimi) : null;
    }

    private function kaynakParaBirimiGuncelle(string $tip, int $hesapId, Forms\Set $set, Get $get): void
    {
        if ($hesapId < 1) {
            $set('kaynak_para_birimi', null);
            $set('hedef_tutar', null);
            return;
        }

        $kaynakPb = $this->hesapParaBirimi($tip, $hesapId);
        $set('kaynak_para_birimi', $kaynakPb);
        $set('doviz_kuru', null);

        $this->otomatikKurDoldur($get, $set);
        $this->hedefTutarGuncelle($get, $set);
    }

    private function farkliParaBirimiSeciliMi(Get $get): bool
    {
        $kaynak = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('hedef_para_birimi') ?? ''));

        return $kaynak !== '' && $hedef !== '' && $kaynak !== $hedef;
    }

    private function hedefTutarOnizleme(Get $get): string
    {
        $tutar = (string) ($get('tutar') ?? '0');
        $kur = (string) ($get('doviz_kuru') ?? '0');
        $hedefPb = strtoupper((string) ($get('hedef_para_birimi') ?? ''));
        $kaynakPb = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));

        if (bccomp($tutar, '0', 8) <= 0 || (float) $kur <= 0 || $hedefPb === '') {
            return '';
        }

        if ($kaynakPb === 'TRY' && $hedefPb !== 'TRY') {
            return bcdiv($tutar, $kur, 8);
        }

        return bcmul($tutar, $kur, 8);
    }

    private function kurGosterimMetni(Get $get): string
    {
        $kaynak = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('hedef_para_birimi') ?? ''));
        $kur = number_format((float) ($get('doviz_kuru') ?? 0), 8, '.', '');
        if ($kaynak === '' || $hedef === '' || (float) $kur <= 0) {
            return 'Hesaplamada kullanilan kur bu alandaki degerdir.';
        }

        $etiket = $this->otomatikKurTipiBelirle($kaynak, $hedef) === 'alis' ? 'Alis Kuru' : 'Satis Kuru';
        $ters = number_format((float) bcdiv('1', $kur, 8), 8, '.', '');

        if ($kaynak === 'TRY' && $hedef !== 'TRY') {
            return 'Kullanilan kur: '.$etiket.' ('.$kur.') | 1 '.$hedef.' = '.$kur.' TRY | 1 TRY = '.$ters.' '.$hedef;
        }

        if ($kaynak !== 'TRY' && $hedef === 'TRY') {
            return 'Kullanilan kur: '.$etiket.' ('.$kur.') | 1 '.$kaynak.' = '.$kur.' TRY | 1 TRY = '.$ters.' '.$kaynak;
        }

        return 'Kullanilan kur: '.$etiket.' ('.$kur.') | 1 '.$kaynak.' = '.$kur.' '.$hedef.' | 1 '.$hedef.' = '.$ters.' '.$kaynak;
    }

    private function kurGuncelleHedefTutardan(Get $get, Forms\Set $set): void
    {
        $tutar = (string) ($get('tutar') ?? '0');
        $hedefTutar = (string) ($get('hedef_tutar') ?? '0');
        $kaynakPb = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));
        $hedefPb = strtoupper((string) ($get('hedef_para_birimi') ?? ''));
        if (bccomp($tutar, '0', 8) <= 0 || bccomp($hedefTutar, '0', 8) <= 0) {
            return;
        }

        $kur = ($kaynakPb === 'TRY' && $hedefPb !== 'TRY')
            ? bcdiv($tutar, $hedefTutar, 8)
            : bcdiv($hedefTutar, $tutar, 8);
        $set('doviz_kuru', $kur);
    }

    private function otomatikKurDoldur(Get $get, Forms\Set $set): void
    {
        $kurTuru = (string) ($get('doviz_kuru_turu') ?? 'otomatik');
        if ($kurTuru !== 'otomatik') {
            return;
        }

        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return;
        }

        $kaynak = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('hedef_para_birimi') ?? ''));
        if ($kaynak === '' || $hedef === '' || $kaynak === $hedef) {
            return;
        }

        $tarih = (string) ($get('tarih') ?? now()->format('Y-m-d H:i'));
        $kur = $this->otomatikKurBul($firmaId, $kaynak, $hedef, $tarih);
        if ($kur !== null) {
            $set('doviz_kuru', $kur);
        }

        $this->hedefTutarGuncelle($get, $set);
    }

    private function hedefTutarGuncelle(Get $get, Forms\Set $set): void
    {
        $set('hedef_tutar', $this->hedefTutarOnizleme($get));
    }

    private function otomatikKurBul(int $firmaId, string $kaynak, string $hedef, string $tarih): ?string
    {
        $gun = Carbon::parse($tarih)->toDateString();
        $kurTipi = $this->otomatikKurTipiBelirle($kaynak, $hedef);

        try {
            $sonuc = app(DovizKurServisi::class)->otomatikKurGetirKurTipi($kaynak, $hedef, $gun, $kurTipi);
            $kur = number_format((float) ($sonuc['kur'] ?? 0), 8, '.', '');
            if ($kaynak === 'TRY' && $hedef !== 'TRY' && (float) $kur > 0) {
                $kur = number_format((float) bcdiv('1', $kur, 8), 8, '.', '');
            }

            return $kur;
        } catch (\Throwable) {
            return null;
        }
    }

    private function otomatikKurTipiBelirle(string $kaynak, string $hedef): string
    {
        $kaynak = strtoupper(trim($kaynak));
        $hedef = strtoupper(trim($hedef));

        if ($kaynak !== 'TRY' && $hedef === 'TRY') {
            return 'alis';
        }

        if ($kaynak === 'TRY' && $hedef !== 'TRY') {
            return 'satis';
        }

        return 'satis';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('kaydet')
                ->label('Odemeyi kaydet')
                ->icon('heroicon-o-check')
                ->color('warning')
                ->action('kaydet'),
            Actions\Action::make('paneleDon')
                ->label('Finans paneli')
                ->icon('heroicon-o-home')
                ->url(FinansDashboardSayfasi::getUrl())
                ->color('gray'),
        ];
    }

    public function kaydet(): void
    {
        $firmaId = (int) ($this->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            Notification::make()->title('Aktif firma yok')->danger()->send();

            return;
        }

        $data = $this->form->getState();
        $tutar = (string) ($data['tutar'] ?? '0');
        if (bccomp($tutar, '0', 8) <= 0) {
            Notification::make()->title('Tutar gecersiz')->danger()->send();

            return;
        }

        $cariId = (int) ($data['cari_id'] ?? 0);
        $hesapTipi = (string) ($data['kanal'] ?? '');
        $hesapId = match ($hesapTipi) {
            'kasa' => (int) ($data['kasa_hesap_id'] ?? 0),
            'banka' => (int) ($data['banka_hesap_id'] ?? 0),
            default => 0,
        };

        if ($cariId < 1 || $hesapId < 1) {
            Notification::make()->title('Cari ve hesap secimi zorunlu')->danger()->send();

            return;
        }

        $faturaId = filled($data['fatura_id'] ?? null) ? (int) $data['fatura_id'] : null;
        $fatura = $faturaId ? Fatura::query()
            ->where('firma_id', $firmaId)
            ->whereKey($faturaId)
            ->where('cari_id', $cariId)
            ->first() : null;
        $cariParaBirimi = (string) (Cari::query()->whereKey($cariId)->value('para_birimi') ?? '');
        $hesapParaBirimi = (string) $this->hesapParaBirimi($hesapTipi, $hesapId);
        $kaynakPb = strtoupper(trim($hesapParaBirimi !== '' ? $hesapParaBirimi : (string) ($data['kaynak_para_birimi'] ?? '')));
        $hedefPb = strtoupper(trim((string) ($fatura?->para_birimi ?: ($data['hedef_para_birimi'] ?? $cariParaBirimi ?: 'TRY'))));
        if ($kaynakPb === '' || $hedefPb === '') {
            Notification::make()->title('Para birimi bulunamadi')->danger()->send();

            return;
        }

        $tarih = Carbon::parse((string) ($data['tarih'] ?? now()->format('Y-m-d H:i')));
        $aciklama = filled($data['aciklama'] ?? null) ? (string) $data['aciklama'] : null;
        $refTur = $faturaId ? 'fatura' : null;
        $refId = $faturaId ?: null;

        try {
            $servis = app(FinansHareketServisi::class);

            if ($kaynakPb === $hedefPb) {
                $sonuc = match ($hesapTipi) {
                    'kasa' => $servis->odemeKasadanKaydet(
                        $firmaId,
                        $cariId,
                        $hesapId,
                        $tutar,
                        $kaynakPb,
                        $tarih,
                        $aciklama,
                        $refTur,
                        $refId,
                    ),
                    'banka' => $servis->odemeBankadanKaydet(
                        $firmaId,
                        $cariId,
                        $hesapId,
                        $tutar,
                        $kaynakPb,
                        $tarih,
                        $aciklama,
                        $refTur,
                        $refId,
                    ),
                    default => throw new IsKuraliIstisnasi('Kanal secimi gecersiz.'),
                };
            } else {
                $kur = number_format((float) ($data['doviz_kuru'] ?? 0), 8, '.', '');
                if ((float) $kur <= 0 && (string) ($data['doviz_kuru_turu'] ?? 'otomatik') === 'otomatik') {
                    $kurBulunan = $this->otomatikKurBul($firmaId, $kaynakPb, $hedefPb, $tarih->format('Y-m-d H:i'));
                    if ($kurBulunan !== null) {
                        $kur = $kurBulunan;
                    }
                }

                if ((float) $kur <= 0) {
                    Notification::make()
                        ->title('Kur bilgisi gerekli')
                        ->body('Farkli para birimi seciminde kur girilmeli ya da otomatik cekilmelidir.')
                        ->danger()
                        ->send();

                    return;
                }

                $kaynakTutar = $tutar;
                $manuelHedefTutar = (string) ($data['hedef_tutar'] ?? '0');
                $hedefTutar = bccomp($manuelHedefTutar, '0', 8) === 1
                    ? $manuelHedefTutar
                    : (($kaynakPb === 'TRY' && $hedefPb !== 'TRY')
                        ? bcdiv($kaynakTutar, $kur, 8)
                        : bcmul($kaynakTutar, $kur, 8));
                $kur = ($kaynakPb === 'TRY' && $hedefPb !== 'TRY')
                    ? bcdiv($kaynakTutar, $hedefTutar, 8)
                    : bcdiv($hedefTutar, $kaynakTutar, 8);

                $sonuc = $servis->odemeKurIleKaydet(
                    $firmaId,
                    $cariId,
                    $hesapTipi,
                    $hesapId,
                    $hedefTutar,
                    $hedefPb,
                    $kaynakTutar,
                    $kaynakPb,
                    $kur,
                    $tarih,
                    $aciklama,
                    $refTur,
                    $refId,
                );
            }

            $this->projeBaglantisiniKaydet($sonuc, $firmaId, (int) ($data['isletme_proje_id'] ?? 0));

            Notification::make()->title('Odeme kaydedildi')->success()->send();
            $this->redirect(FinansDashboardSayfasi::getUrl());
        } catch (IsKuraliIstisnasi $e) {
            Notification::make()->title('Islem yapilamadi')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Sunucu hatasi')->body($e->getMessage())->danger()->send();
        }
    }

    private function faturaOdemeyeUygunMu(Fatura $f): bool
    {
        if (! $f->tur instanceof FaturaTuru) {
            return false;
        }
        if ($f->durum !== FaturaDurumu::Onayli) {
            return false;
        }
        if (! $f->tur->kayitUretirMi()) {
            return false;
        }
        if ($f->tur->cariYonu() !== 'borc') {
            return false;
        }

        return bccomp((string) ($f->acik_tutar ?? '0'), '0', 2) > 0;
    }

    /** @param array<string, mixed> $sonuc */
    private function projeBaglantisiniKaydet(array $sonuc, int $firmaId, int $projeId): void
    {
        if ($projeId < 1 || ! $this->projeModuluAktifMi()) {
            return;
        }

        if (! IsletmeProjesi::query()->where('firma_id', $firmaId)->whereKey($projeId)->exists()) {
            throw new IsKuraliIstisnasi('Seçilen proje aktif firmaya ait olmalıdır.');
        }

        if (($sonuc['finans'] ?? null) instanceof FinansHareketi) {
            $sonuc['finans']->update(['isletme_proje_id' => $projeId]);
        }
        if (($sonuc['cari'] ?? null) instanceof CariHareketi) {
            $sonuc['cari']->update(['isletme_proje_id' => $projeId]);
        }
    }

    private function projeModuluAktifMi(): bool
    {
        $firmaId = (int) ($this->aktifFirmaId() ?? 0);

        return $firmaId > 0
            && app(ModulErisimService::class)->modulErisilebilirMi($firmaId, 'proje_yonetimi');
    }

    /** @return array<int, string> */
    private function projeSecenekleri(): array
    {
        if (! $this->projeModuluAktifMi()) {
            return [];
        }

        $firmaId = (int) ($this->aktifFirmaId() ?? 0);

        return IsletmeProjesi::query()
            ->where('firma_id', $firmaId)
            ->whereIn('durum', [IsletmeProjesi::DURUM_TASLAK, IsletmeProjesi::DURUM_AKTIF])
            ->orderBy('ad')
            ->limit(100)
            ->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (IsletmeProjesi $proje): array => [$proje->id => $proje->kod.' — '.$proje->ad])
            ->all();
    }
}
