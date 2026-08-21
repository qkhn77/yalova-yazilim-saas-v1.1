<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\ParaBirimi;
use App\Models\Muhasebe\PosHesabi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\DovizKurServisi;
use App\Muhasebe\Servisler\FinansHareketServisi;
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

class TransferOlusturSayfasi extends Page implements HasForms
{
    use InteractsWithForms;
    use MuhasebeSayfaErisimleri;

    public ?array $data = [];

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Transfer';

    protected static ?string $slug = 'finans/transfer-olustur';

    protected static string $view = 'filament.clusters.muhasebe.pages.tahsilat-odeme-form';

    public function getHeading(): string|Htmlable
    {
        return 'Transfer (virman) olustur';
    }

    public function getSubheading(): ?string
    {
        return 'Kasa, banka ve POS hesaplari arasinda transfer kaydi olusturur.';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_OLUSTUR;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function mount(): void
    {
        $this->form->fill([
            'kaynak_tipi' => 'kasa',
            'hedef_tipi' => 'banka',
            'kaynak_hesap_id' => null,
            'hedef_hesap_id' => null,
            'para_birimi' => 'TRY',
            'kaynak_para_birimi' => null,
            'hedef_para_birimi' => null,
            'doviz_kuru_turu' => 'otomatik',
            'doviz_kuru' => null,
            'tutar' => null,
            'tarih' => now(),
            'aciklama' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Transfer bilgileri')
                    ->schema([
                        Forms\Components\Radio::make('kaynak_tipi')
                            ->label('Kaynak turu')
                            ->options($this->hesapTipiSecenekleri())
                            ->required()
                            ->inline()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('kaynak_hesap_id', null);
                                $set('hedef_hesap_id', null);
                                $set('kaynak_para_birimi', null);
                                $set('hedef_para_birimi', null);
                                $set('doviz_kuru', null);
                            }),
                        Forms\Components\Select::make('kaynak_hesap_id')
                            ->label('Kaynak hesap')
                            ->options(fn (Get $get): array => $this->kaynakHesapSecenekleri((string) ($get('kaynak_tipi') ?: 'kasa')))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Get $get): void {
                                if (! $state) {
                                    return;
                                }
                                $tip = (string) ($get('kaynak_tipi') ?: '');
                                $pb = $this->hesapParaBirimi($tip, (int) $state);
                                if ($pb !== null) {
                                    $pb = strtoupper($pb);
                                    $set('para_birimi', $pb);
                                    $set('kaynak_para_birimi', $pb);
                                    $set('hedef_hesap_id', null);
                                    $set('hedef_para_birimi', null);
                                    $set('doviz_kuru', null);
                                }
                            }),

                        Forms\Components\Radio::make('hedef_tipi')
                            ->label('Hedef turu')
                            ->options($this->hesapTipiSecenekleri())
                            ->required()
                            ->inline()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('hedef_hesap_id', null);
                                $set('hedef_para_birimi', null);
                                $set('doviz_kuru', null);
                            }),
                        Forms\Components\Select::make('hedef_hesap_id')
                            ->label('Hedef hesap')
                            ->options(fn (Get $get): array => $this->hedefHesapSecenekleri($get))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Get $get): void {
                                if (! $state) {
                                    return;
                                }
                                $tip = (string) ($get('hedef_tipi') ?: '');
                                $pb = $this->hesapParaBirimi($tip, (int) $state);
                                if ($pb !== null) {
                                    $set('hedef_para_birimi', strtoupper($pb));
                                    $this->otomatikKurDoldur($get, $set);
                                }
                            }),

                        Forms\Components\Select::make('para_birimi')
                            ->label('Para birimi')
                            ->options(fn (): array => $this->paraBirimiSecenekleri())
                            ->searchable()
                            ->required()
                            ->helperText('Kaynak hesap para birimi.'),
                        Forms\Components\TextInput::make('kaynak_para_birimi')
                            ->label('Kaynak PB')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('hedef_para_birimi')
                            ->label('Hedef PB')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('tutar')
                            ->label('Tutar')
                            ->numeric()
                            ->live(onBlur: true)
                            ->required()
                            ->step('0.01')
                            ->minValue(0.01),
                        Forms\Components\Select::make('doviz_kuru_turu')
                            ->label('Kur tipi')
                            ->options([
                                'otomatik' => 'Otomatik',
                                'manuel' => 'Manuel',
                            ])
                            ->default('otomatik')
                            ->live()
                            ->visible(fn (Get $get): bool => $this->farkliParaBirimiSeciliMi($get)),
                        Forms\Components\TextInput::make('doviz_kuru')
                            ->label('Doviz kuru')
                            ->numeric()
                            ->step('0.00000001')
                            ->minValue(0.00000001)
                            ->helperText(fn (Get $get): string => $this->kurGosterimMetni($get))
                            ->live(onBlur: true)
                            ->required(fn (Get $get): bool => $this->farkliParaBirimiSeciliMi($get))
                            ->visible(fn (Get $get): bool => $this->farkliParaBirimiSeciliMi($get))
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('kur_cek')
                                    ->label('Kur cek')
                                    ->action(fn (Get $get, Forms\Set $set) => $this->otomatikKurDoldur($get, $set))
                            ),
                        Forms\Components\DateTimePicker::make('tarih')
                            ->label('Islem tarihi')
                            ->required()
                            ->native(true)
                            ->displayFormat('d.m.Y H:i')
                            ->seconds(false)
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('simdi')
                                    ->label('Simdi')
                                    ->action(fn (Forms\Set $set) => $set('tarih', now()->format('Y-m-d H:i')))
                            ),
                        Forms\Components\Placeholder::make('hedef_tutar_onizleme')
                            ->label('Hedef tutar (onizleme)')
                            ->content(fn (Get $get): string => $this->hedefTutarOnizleme($get))
                            ->visible(fn (Get $get): bool => $this->farkliParaBirimiSeciliMi($get)),
                        Forms\Components\Textarea::make('aciklama')
                            ->label('Aciklama')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function hesapTipiSecenekleri(): array
    {
        return [
            'kasa' => 'Kasa',
            'banka' => 'Banka',
            'pos' => 'POS',
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private function hesapSecenekleri(string $tip): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return [];
        }

        $model = $this->hesapModeli($tip);

        return $model::query()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif)
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private function kaynakHesapSecenekleri(string $tip): array
    {
        return $this->hesapSecenekleri($tip);
    }

    /**
     * @return array<int|string, string>
     */
    private function hedefHesapSecenekleri(Get $get): array
    {
        $hedefTip = (string) ($get('hedef_tipi') ?: 'banka');
        $kaynakTip = (string) ($get('kaynak_tipi') ?: '');
        $kaynakId = (int) ($get('kaynak_hesap_id') ?: 0);
        $liste = $this->hesapSecenekleri($hedefTip);

        if ($kaynakTip === $hedefTip && $kaynakId > 0) {
            unset($liste[$kaynakId]);
        }

        return $liste;
    }

    /**
     * @return array<string, string>
     */
    private function paraBirimiSecenekleri(): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return [];
        }

        return ParaBirimi::tenantScopeOlmadan(fn (): array => ParaBirimi::query()
            ->where('aktif_mi', true)
            ->gorunurFirmaIle($firmaId)
            ->orderBy('kod')
            ->get(['kod', 'ad'])
            ->mapWithKeys(function (ParaBirimi $pb): array {
                $kod = strtoupper((string) $pb->kod);
                $ad = trim((string) ($pb->ad ?? ''));

                return [$kod => $ad !== '' ? $kod.' - '.$ad : $kod];
            })
            ->all());
    }

    /**
     * @return class-string<KasaHesabi|BankaHesabi|PosHesabi>
     */
    private function hesapModeli(string $tip): string
    {
        return match ($tip) {
            'kasa' => KasaHesabi::class,
            'banka' => BankaHesabi::class,
            'pos' => PosHesabi::class,
            default => KasaHesabi::class,
        };
    }

    private function hesapParaBirimi(string $tip, int $hesapId): ?string
    {
        if ($hesapId < 1) {
            return null;
        }
        $model = $this->hesapModeli($tip);
        $hesap = $model::query()->find($hesapId);

        return $hesap ? (string) $hesap->para_birimi : null;
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
        $kaynakPb = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));
        $hedefPb = strtoupper((string) ($get('hedef_para_birimi') ?? ''));

        if (bccomp($tutar, '0', 8) <= 0 || (float) $kur <= 0 || $hedefPb === '') {
            return '-';
        }

        $hedefTutar = ($kaynakPb === 'TRY' && $hedefPb !== 'TRY')
            ? bcdiv($tutar, $kur, 8)
            : bcmul($tutar, $kur, 8);

        return $hedefTutar.' '.$hedefPb;
    }

    private function kurGosterimMetni(Get $get): string
    {
        $kaynak = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('hedef_para_birimi') ?? ''));
        $kur = number_format((float) ($get('doviz_kuru') ?? 0), 8, '.', '');
        if ($kaynak === '' || $hedef === '' || (float) $kur <= 0) {
            return 'Kullanilan kur: Satis Kuru';
        }

        $ters = number_format((float) bcdiv('1', $kur, 8), 8, '.', '');
        if ($kaynak === 'TRY' && $hedef !== 'TRY') {
            return 'Kullanilan kur: Satis Kuru ('.$kur.') | 1 '.$hedef.' = '.$kur.' TRY | 1 TRY = '.$ters.' '.$hedef;
        }

        return 'Kullanilan kur: Satis Kuru ('.$kur.') | 1 '.$kaynak.' = '.$kur.' '.$hedef.' | 1 '.$hedef.' = '.$ters.' '.$kaynak;
    }

    private function otomatikKurDoldur(Get $get, Forms\Set $set): void
    {
        $kaynak = strtoupper((string) ($get('kaynak_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('hedef_para_birimi') ?? ''));
        $kurTipi = (string) ($get('doviz_kuru_turu') ?? 'otomatik');

        if ($kaynak === '' || $hedef === '' || $kaynak === $hedef || $kurTipi !== 'otomatik') {
            return;
        }

        try {
            $tarih = (string) ($get('tarih') ?? now()->toDateString());
            $sonuc = app(DovizKurServisi::class)->otomatikKurGetirKurTipi($kaynak, $hedef, $tarih, 'satis');
            $kur = number_format((float) ($sonuc['kur'] ?? 0), 8, '.', '');
            if ($kaynak === 'TRY' && $hedef !== 'TRY' && (float) $kur > 0) {
                $kur = number_format((float) bcdiv('1', $kur, 8), 8, '.', '');
            }
            $set('doviz_kuru', $kur);
        } catch (\Throwable) {
            // Otomatik cekilemezse manuel giris yapilabilir.
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('kaydet')
                ->label('Transferi kaydet')
                ->icon('heroicon-o-check')
                ->color('info')
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
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            Notification::make()->title('Aktif firma yok')->danger()->send();

            return;
        }

        $data = $this->form->getState();
        $tutar = (string) ($data['tutar'] ?? '0');
        if (bccomp($tutar, '0', 8) <= 0) {
            Notification::make()->title('Tutar gecersiz')->danger()->send();

            return;
        }

        $kaynakTip = (string) ($data['kaynak_tipi'] ?? '');
        $hedefTip = (string) ($data['hedef_tipi'] ?? '');
        $kaynakId = (int) ($data['kaynak_hesap_id'] ?? 0);
        $hedefId = (int) ($data['hedef_hesap_id'] ?? 0);
        if ($kaynakTip === $hedefTip && $kaynakId === $hedefId) {
            Notification::make()->title('Kaynak ve hedef ayni olamaz')->danger()->send();

            return;
        }

        $kaynakPb = strtoupper((string) ($this->hesapParaBirimi($kaynakTip, $kaynakId) ?? ''));
        $hedefPb = strtoupper((string) ($this->hesapParaBirimi($hedefTip, $hedefId) ?? ''));
        if ($kaynakPb === '' || $hedefPb === '') {
            Notification::make()->title('Hesap para birimi bulunamadi')->danger()->send();

            return;
        }

        $tarih = Carbon::parse((string) $data['tarih']);
        $aciklama = $data['aciklama'] ?? null;

        try {
            $servis = app(FinansHareketServisi::class);

            if ($kaynakPb === $hedefPb) {
                $servis->virmanHesaplarArasiKaydet(
                    (int) $firmaId,
                    $kaynakTip,
                    $kaynakId,
                    $hedefTip,
                    $hedefId,
                    $tutar,
                    $kaynakPb,
                    $tarih,
                    $aciklama
                );
            } else {
                $kur = number_format((float) ($data['doviz_kuru'] ?? 0), 8, '.', '');
                if ((float) $kur <= 0) {
                    Notification::make()->title('Kur zorunlu')->body('Farkli para birimi transferinde kur girilmelidir.')->danger()->send();

                    return;
                }

                $hedefTutar = ($kaynakPb === 'TRY' && $hedefPb !== 'TRY')
                    ? bcdiv($tutar, $kur, 8)
                    : bcmul($tutar, $kur, 8);

                $servis->virmanHesaplarArasiKurIleKaydet(
                    (int) $firmaId,
                    $kaynakTip,
                    $kaynakId,
                    $hedefTip,
                    $hedefId,
                    $tutar,
                    $kaynakPb,
                    $hedefTutar,
                    $hedefPb,
                    $kur,
                    $tarih,
                    $aciklama
                );
            }

            Notification::make()->title('Transfer kaydedildi')->success()->send();
            $this->redirect(FinansDashboardSayfasi::getUrl());
        } catch (IsKuraliIstisnasi $e) {
            Notification::make()->title('Islem yapilamadi')->body($e->getMessage())->danger()->send();
        }
    }
}
