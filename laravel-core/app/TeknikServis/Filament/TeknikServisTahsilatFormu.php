<?php

namespace App\TeknikServis\Filament;

use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisMuhasebeBaglantisi;
use App\Muhasebe\Servisler\DovizKurServisi;
use Carbon\Carbon;
use Filament\Forms;

final class TeknikServisTahsilatFormu
{
    /** @var array<string, array<int, string>> */
    private static array $hesapSecenekCache = [];

    /** @var array<string, array<int, string>> */
    private static array $hesapParaBirimiCache = [];

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(TeknikServisKaydi $record): array
    {
        return [
            Forms\Components\Placeholder::make('_servis_cari_bilgi')
                ->label('Cari')
                ->content((string) ($record->cari?->ad ?: '-')),
            Forms\Components\Placeholder::make('_servis_fatura_bilgi')
                ->label('Bağlı fatura')
                ->content(self::bagliFaturaMetni($record)),
            Forms\Components\Hidden::make('kaynak_para_birimi')
                ->default(self::kaynakParaBirimi($record))
                ->dehydrated(),
            Forms\Components\Radio::make('kanal')
                ->label('Tahsilat kanalı')
                ->options([
                    'kasa' => 'Kasa',
                    'banka' => 'Banka',
                    'pos' => 'POS',
                    'veresiye' => 'Veresiye',
                    'taksitli' => 'Taksitli',
                ])
                ->default('kasa')
                ->required()
                ->inline()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) use ($record): void {
                    foreach ([
                        'kasa_hesap_id',
                        'banka_hesap_id',
                        'pos_hesap_id',
                        'pesinat_kasa_hesap_id',
                        'pesinat_banka_hesap_id',
                        'pesinat_pos_hesap_id',
                        'hedef_para_birimi',
                        'pesinat_hedef_para_birimi',
                        'doviz_kuru',
                        'hedef_tutar',
                        'pesinat_doviz_kuru',
                        'pesinat_hedef_tutar',
                        'doviz_kuru_turu',
                        'pesinat_doviz_kuru_turu',
                    ] as $alan) {
                        $set($alan, null);
                    }

                    $set('doviz_kuru_turu', 'otomatik');
                    $set('pesinat_doviz_kuru_turu', 'otomatik');
                    if (($get('kanal') ?? '') === 'veresiye') {
                        $set('vade_farki_tipi', 'tek_seferlik');
                    } elseif (($get('kanal') ?? '') === 'taksitli') {
                        $set('vade_farki_tipi', 'aylik');
                    }
                    self::vadeliHesaplamaGuncelle($record, $get, $set);
                }),
            Forms\Components\Select::make('kasa_hesap_id')
                ->label('Kasa hesabı')
                ->options(fn (): array => self::hesapSecenekleri($record, 'kasa'))
                ->visible(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'kasa')
                ->required(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'kasa')
                ->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::hedefParaBirimiGuncelle($record, 'kasa', (int) $state, $set, $get)),
            Forms\Components\Select::make('banka_hesap_id')
                ->label('Banka hesabı')
                ->options(fn (): array => self::hesapSecenekleri($record, 'banka'))
                ->visible(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'banka')
                ->required(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'banka')
                ->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::hedefParaBirimiGuncelle($record, 'banka', (int) $state, $set, $get)),
            Forms\Components\Select::make('pos_hesap_id')
                ->label('POS hesabı')
                ->options(fn (): array => self::hesapSecenekleri($record, 'pos'))
                ->visible(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'pos')
                ->required(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'pos')
                ->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::hedefParaBirimiGuncelle($record, 'pos', (int) $state, $set, $get)),
            Forms\Components\Placeholder::make('_kaynak_pb')
                ->label('Tahsilat para birimi')
                ->content(fn (Forms\Get $get): string => strtoupper((string) ($get('kaynak_para_birimi') ?: self::kaynakParaBirimi($record)))),
            Forms\Components\TextInput::make('hedef_para_birimi')
                ->label('Hesap para birimi')
                ->disabled()
                ->dehydrated()
                ->placeholder('İlgili hesap seçin')
                ->visible(fn (Forms\Get $get): bool => ! self::vadeliOdemeSeciliMi($get)),
            Forms\Components\TextInput::make('tutar')
                ->label('Tahsilat tutarı')
                ->helperText('Bu alan düzenlenebilir; değişiklik finans kaydına ters kayıt ve yeni tutarla işlenir.')
                ->numeric()
                ->required(fn (Forms\Get $get): bool => ! self::vadeliOdemeSeciliMi($get))
                ->minValue(0.01)
                ->step('0.01')
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::hedefTutarGuncelle($get, $set, 'normal'))
                ->visible(fn (Forms\Get $get): bool => ! self::vadeliOdemeSeciliMi($get)),
            Forms\Components\Radio::make('doviz_kuru_turu')
                ->label('Kur türü')
                ->options([
                    'otomatik' => 'Otomatik çek',
                    'manuel' => 'Manuel',
                ])
                ->default('otomatik')
                ->inline()
                ->live()
                ->visible(fn (Forms\Get $get): bool => self::farkliParaBirimiSeciliMi($record, $get))
                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) use ($record): void {
                    self::otomatikKurDoldur($record, $get, $set, 'normal');
                    self::hedefTutarGuncelle($get, $set, 'normal');
                }),
            Forms\Components\TextInput::make('doviz_kuru')
                ->label('Kur')
                ->numeric()
                ->step('0.00000001')
                ->minValue(0.00000001)
                ->helperText(fn (Forms\Get $get): string => self::kurGosterimMetni($get, 'normal'))
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::hedefTutarGuncelle($get, $set, 'normal'))
                ->visible(fn (Forms\Get $get): bool => self::farkliParaBirimiSeciliMi($record, $get))
                ->required(fn (Forms\Get $get): bool => self::farkliParaBirimiSeciliMi($record, $get))
                ->suffixAction(
                    Forms\Components\Actions\Action::make('kur_cek')
                        ->label('Kur çek')
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->action(fn (Forms\Get $get, Forms\Set $set) => self::otomatikKurDoldur($record, $get, $set, 'normal'))
                ),
            Forms\Components\TextInput::make('hedef_tutar')
                ->label('Hedef tutar')
                ->numeric()
                ->step('0.01')
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::kurGuncelleHedefTutardan($get, $set, 'normal'))
                ->visible(fn (Forms\Get $get): bool => self::farkliParaBirimiSeciliMi($record, $get)),
            Forms\Components\TextInput::make('plan_para_birimi')
                ->label('Plan para birimi')
                ->default(self::kaynakParaBirimi($record))
                ->disabled()
                ->dehydrated()
                ->visible(fn (Forms\Get $get): bool => self::vadeliOdemeSeciliMi($get)),
            Forms\Components\TextInput::make('toplam_tutar')
                ->label('Plan tutarı')
                ->numeric()
                ->required(fn (Forms\Get $get): bool => self::vadeliOdemeSeciliMi($get))
                ->minValue(0.01)
                ->step('0.01')
                ->default(fn (): string => number_format(self::varsayilanPlanTutari($record), 2, '.', ''))
                ->live(debounce: 250)
                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::vadeliHesaplamaGuncelle($record, $get, $set))
                ->visible(fn (Forms\Get $get): bool => self::vadeliOdemeSeciliMi($get)),
            Forms\Components\TextInput::make('pesinat_tutari')
                ->label('Peşinat tutarı')
                ->helperText('Peşinat girilirse seçilen kasa/banka/POS hesabına ayrıca tahsilat kaydı oluşur.')
                ->numeric()
                ->minValue(0)
                ->step('0.01')
                ->default(0)
                ->live(debounce: 250)
                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) use ($record): void {
                    self::hedefTutarGuncelle($get, $set, 'pesinat');
                    self::vadeliHesaplamaGuncelle($record, $get, $set);
                })
                ->visible(fn (Forms\Get $get): bool => self::vadeliOdemeSeciliMi($get)),
            Forms\Components\Placeholder::make('_pesinat_pb')
                ->label('Peşinat para birimi')
                ->content(fn (Forms\Get $get): string => strtoupper((string) ($get('plan_para_birimi') ?: self::kaynakParaBirimi($record))))
                ->visible(fn (Forms\Get $get): bool => self::pesinatVarMi($get)),
            Forms\Components\Radio::make('pesinat_kanali')
                ->label('Peşinat tahsilat kanalı')
                ->options([
                    'kasa' => 'Kasa',
                    'banka' => 'Banka',
                    'pos' => 'POS',
                ])
                ->default('kasa')
                ->inline()
                ->live()
                ->visible(fn (Forms\Get $get): bool => self::pesinatVarMi($get))
                ->required(fn (Forms\Get $get): bool => self::pesinatVarMi($get))
                ->afterStateUpdated(function (Forms\Set $set): void {
                    foreach ([
                        'pesinat_kasa_hesap_id',
                        'pesinat_banka_hesap_id',
                        'pesinat_pos_hesap_id',
                        'pesinat_hedef_para_birimi',
                        'pesinat_doviz_kuru',
                        'pesinat_hedef_tutar',
                        'pesinat_doviz_kuru_turu',
                    ] as $alan) {
                        $set($alan, null);
                    }

                    $set('pesinat_doviz_kuru_turu', 'otomatik');
                }),
            Forms\Components\Select::make('pesinat_kasa_hesap_id')
                ->label('Peşinat kasa hesabı')
                ->options(fn (): array => self::hesapSecenekleri($record, 'kasa'))
                ->visible(fn (Forms\Get $get): bool => self::pesinatKanaliMi($get, 'kasa'))
                ->required(fn (Forms\Get $get): bool => self::pesinatKanaliMi($get, 'kasa'))
                ->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::pesinatHedefParaBirimiGuncelle($record, 'kasa', (int) $state, $set, $get)),
            Forms\Components\Select::make('pesinat_banka_hesap_id')
                ->label('Peşinat banka hesabı')
                ->options(fn (): array => self::hesapSecenekleri($record, 'banka'))
                ->visible(fn (Forms\Get $get): bool => self::pesinatKanaliMi($get, 'banka'))
                ->required(fn (Forms\Get $get): bool => self::pesinatKanaliMi($get, 'banka'))
                ->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::pesinatHedefParaBirimiGuncelle($record, 'banka', (int) $state, $set, $get)),
            Forms\Components\Select::make('pesinat_pos_hesap_id')
                ->label('Peşinat POS hesabı')
                ->options(fn (): array => self::hesapSecenekleri($record, 'pos'))
                ->visible(fn (Forms\Get $get): bool => self::pesinatKanaliMi($get, 'pos'))
                ->required(fn (Forms\Get $get): bool => self::pesinatKanaliMi($get, 'pos'))
                ->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::pesinatHedefParaBirimiGuncelle($record, 'pos', (int) $state, $set, $get)),
            Forms\Components\TextInput::make('pesinat_hedef_para_birimi')
                ->label('Peşinat hesap para birimi')
                ->disabled()
                ->dehydrated()
                ->placeholder('Peşinat hesabı seçin')
                ->visible(fn (Forms\Get $get): bool => self::pesinatVarMi($get)),
            Forms\Components\Radio::make('pesinat_doviz_kuru_turu')
                ->label('Peşinat kur türü')
                ->options([
                    'otomatik' => 'Otomatik çek',
                    'manuel' => 'Manuel',
                ])
                ->default('otomatik')
                ->inline()
                ->live()
                ->visible(fn (Forms\Get $get): bool => self::pesinatFarkliParaBirimiMi($record, $get))
                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) use ($record): void {
                    self::otomatikKurDoldur($record, $get, $set, 'pesinat');
                    self::hedefTutarGuncelle($get, $set, 'pesinat');
                }),
            Forms\Components\TextInput::make('pesinat_doviz_kuru')
                ->label('Peşinat kuru')
                ->helperText(fn (Forms\Get $get): string => self::kurGosterimMetni($get, 'pesinat'))
                ->numeric()
                ->step('0.00000001')
                ->minValue(0.00000001)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::hedefTutarGuncelle($get, $set, 'pesinat'))
                ->visible(fn (Forms\Get $get): bool => self::pesinatFarkliParaBirimiMi($record, $get))
                ->required(fn (Forms\Get $get): bool => self::pesinatFarkliParaBirimiMi($record, $get))
                ->suffixAction(
                    Forms\Components\Actions\Action::make('pesinat_kur_cek')
                        ->label('Kur çek')
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->action(fn (Forms\Get $get, Forms\Set $set) => self::otomatikKurDoldur($record, $get, $set, 'pesinat'))
                ),
            Forms\Components\TextInput::make('pesinat_hedef_tutar')
                ->label('Peşinat hedef tutar')
                ->numeric()
                ->step('0.01')
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::kurGuncelleHedefTutardan($get, $set, 'pesinat'))
                ->visible(fn (Forms\Get $get): bool => self::pesinatFarkliParaBirimiMi($record, $get)),
            Forms\Components\DatePicker::make('vade_tarihi')
                ->label(fn (Forms\Get $get): string => ($get('kanal') ?? '') === 'taksitli' ? 'İlk vade tarihi' : 'Vade tarihi')
                ->native(false)
                ->default(now()->addDays(30)->toDateString())
                ->live()
                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::vadeliHesaplamaGuncelle($record, $get, $set))
                ->visible(fn (Forms\Get $get): bool => self::vadeliOdemeSeciliMi($get))
                ->required(fn (Forms\Get $get): bool => self::vadeliOdemeSeciliMi($get)),
            Forms\Components\Toggle::make('vade_farki_uygula')
                ->label('Vade farkı uygula')
                ->default(false)
                ->reactive()
                ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) use ($record): void {
                    $set('vade_farki_uygula', self::dogruMu($state));
                    self::vadeliHesaplamaGuncelle($record, $get, $set);
                })
                ->visible(fn (Forms\Get $get): bool => self::vadeliOdemeSeciliMi($get)),
            Forms\Components\Select::make('vade_farki_tipi')
                ->label('Vade farkı tipi')
                ->options([
                    'tek_seferlik' => 'Tek seferlik',
                    'aylik' => 'Aylık',
                    'yillik' => 'Yıllık',
                ])
                ->default('aylik')
                ->live()
                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::vadeliHesaplamaGuncelle($record, $get, $set))
                ->visible(fn (Forms\Get $get): bool => self::vadeFarkiTipiGosterilsinMi($get)),
            Forms\Components\TextInput::make('vade_farki_orani')
                ->label('Vade farkı oranı (%)')
                ->helperText('Örn. %5 için 5 yazın. Tutar sistem tarafından hesaplanır.')
                ->numeric()
                ->minValue(0)
                ->step('0.01')
                ->default(0)
                ->live(debounce: 250)
                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::vadeliHesaplamaGuncelle($record, $get, $set))
                ->visible(fn (Forms\Get $get): bool => self::vadeFarkiOraniGosterilsinMi($get)),
            Forms\Components\Hidden::make('vade_farki_tutari')
                ->default('0.00')
                ->dehydrated(),
            Forms\Components\Placeholder::make('_vade_farki_tutari')
                ->label('Hesaplanan vade farkı')
                ->content(fn (Forms\Get $get): string => self::paraMetni(self::guncelVadeFarkiTutari($record, $get), self::kaynakParaBirimi($record)))
                ->visible(fn (Forms\Get $get): bool => self::vadeFarkiOraniGosterilsinMi($get)),
            Forms\Components\TextInput::make('taksit_sayisi')
                ->label('Taksit sayısı')
                ->numeric()
                ->minValue(2)
                ->default(2)
                ->live(debounce: 250)
                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::vadeliHesaplamaGuncelle($record, $get, $set))
                ->visible(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'taksitli')
                ->required(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'taksitli'),
            Forms\Components\TextInput::make('taksit_araligi_gun')
                ->label('Taksit aralığı (gün)')
                ->numeric()
                ->minValue(1)
                ->default(30)
                ->live(debounce: 250)
                ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::vadeliHesaplamaGuncelle($record, $get, $set))
                ->visible(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'taksitli')
                ->required(fn (Forms\Get $get): bool => ($get('kanal') ?? '') === 'taksitli'),
            Forms\Components\Placeholder::make('_vadeli_ozet')
                ->label('Plan özeti')
                ->content(fn (Forms\Get $get): string => self::vadeliPlanOzeti($record, $get))
                ->visible(fn (Forms\Get $get): bool => self::vadeliOdemeSeciliMi($get)),
            Forms\Components\DateTimePicker::make('tarih')
                ->label('İşlem tarihi')
                ->native(false)
                ->seconds(false)
                ->default(now())
                ->required()
                ->hintActions([
                    Forms\Components\Actions\Action::make('kur_cek_tarih')
                        ->label('Kur çek')
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->action(function (Forms\Get $get, Forms\Set $set) use ($record): void {
                            self::otomatikKurDoldur($record, $get, $set, 'normal');
                            self::otomatikKurDoldur($record, $get, $set, 'pesinat');
                        }),
                ]),
            Forms\Components\Textarea::make('aciklama')
                ->label('Açıklama')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    private static function kaynakParaBirimi(TeknikServisKaydi $record): string
    {
        return strtoupper((string) ($record->cari?->para_birimi ?: 'TRY'));
    }

    private static function vadeliOdemeSeciliMi(Forms\Get $get): bool
    {
        return in_array((string) ($get('kanal') ?? ''), ['veresiye', 'taksitli'], true);
    }

    private static function pesinatVarMi(Forms\Get $get): bool
    {
        return self::vadeliOdemeSeciliMi($get) && self::tutar($get('pesinat_tutari') ?? 0) > 0;
    }

    private static function pesinatKanaliMi(Forms\Get $get, string $kanal): bool
    {
        return self::pesinatVarMi($get) && (string) ($get('pesinat_kanali') ?? 'kasa') === $kanal;
    }

    private static function vadeFarkiTipiGosterilsinMi(Forms\Get $get): bool
    {
        return (string) ($get('kanal') ?? '') === 'taksitli' && self::dogruMu($get('vade_farki_uygula'));
    }

    private static function vadeFarkiOraniGosterilsinMi(Forms\Get $get): bool
    {
        return self::vadeliOdemeSeciliMi($get) && self::dogruMu($get('vade_farki_uygula'));
    }

    private static function dogruMu(mixed $deger): bool
    {
        if (is_bool($deger)) {
            return $deger;
        }

        if (is_int($deger) || is_float($deger)) {
            return (int) $deger === 1;
        }

        return in_array(strtolower(trim((string) $deger)), ['1', 'true', 'on', 'yes'], true);
    }

    private static function vadeliHesaplamaGuncelle(TeknikServisKaydi $record, Forms\Get $get, Forms\Set $set): void
    {
        if ((string) ($get('kanal') ?? '') === 'veresiye') {
            $set('vade_farki_tipi', 'tek_seferlik');
        } elseif ((string) ($get('kanal') ?? '') === 'taksitli' && blank($get('vade_farki_tipi'))) {
            $set('vade_farki_tipi', 'aylik');
        }

        $set('vade_farki_tutari', number_format(self::guncelVadeFarkiTutari($record, $get), 2, '.', ''));
    }

    private static function varsayilanPlanTutari(TeknikServisKaydi $record): float
    {
        $toplam = (float) ($record->toplam_tutar ?? 0);
        $odenen = (float) ($record->odenen_tutar ?? 0);
        $kalan = max(0, $toplam - $odenen);

        return $kalan > 0.009 ? $kalan : $toplam;
    }

    private static function vadeliPlanOzeti(TeknikServisKaydi $record, Forms\Get $get): string
    {
        $toplam = max(0, self::tutar($get('toplam_tutar') ?? self::varsayilanPlanTutari($record)));
        $pesinat = min($toplam, max(0, self::tutar($get('pesinat_tutari') ?? 0)));
        $vadeFarki = self::guncelVadeFarkiTutari($record, $get);
        $planlanacak = max(0, $toplam + $vadeFarki - $pesinat);
        $taksitSayisi = (string) ($get('kanal') ?? '') === 'taksitli'
            ? max(2, (int) ($get('taksit_sayisi') ?? 2))
            : 1;
        $taksitler = self::vadeliTaksitTutarlari($get, max(0, $toplam - $pesinat), $planlanacak, $taksitSayisi);
        $paraBirimi = self::kaynakParaBirimi($record);
        $taksitMetni = $taksitSayisi === 1
            ? self::paraMetni($taksitler[0] ?? $planlanacak, $paraBirimi)
            : 'İlk: '.self::paraMetni($taksitler[0] ?? 0, $paraBirimi)
                .' | Son: '.self::paraMetni($taksitler[$taksitSayisi - 1] ?? 0, $paraBirimi);

        return 'Peşinat: '.self::paraMetni($pesinat, $paraBirimi)
            .' | Vade farkı: '.self::paraMetni($vadeFarki, $paraBirimi)
            .' | Planlanacak: '.self::paraMetni($planlanacak, $paraBirimi)
            .' | Taksit: '.$taksitSayisi.' adet ('.$taksitMetni.')';
    }

    private static function guncelVadeFarkiTutari(TeknikServisKaydi $record, Forms\Get $get): float
    {
        $toplam = max(0, self::tutar($get('toplam_tutar') ?? self::varsayilanPlanTutari($record)));
        $pesinat = min($toplam, max(0, self::tutar($get('pesinat_tutari') ?? 0)));

        return self::vadeFarkiTutari($get, max(0, $toplam - $pesinat));
    }

    private static function vadeFarkiTutari(Forms\Get $get, float $anapara): float
    {
        if (! self::dogruMu($get('vade_farki_uygula'))) {
            return 0.0;
        }

        $oran = max(0, self::tutar($get('vade_farki_orani') ?? 0));
        if ($anapara <= 0 || $oran <= 0) {
            return 0.0;
        }

        $tip = (string) ($get('kanal') ?? '') === 'veresiye'
            ? 'tek_seferlik'
            : (string) ($get('vade_farki_tipi') ?: 'aylik');
        $tip = in_array($tip, ['tek_seferlik', 'aylik', 'yillik'], true)
            ? $tip
            : 'tek_seferlik';

        if ($tip === 'tek_seferlik') {
            return round($anapara * ($oran / 100), 2);
        }

        $taksitSayisi = (string) ($get('kanal') ?? '') === 'taksitli'
            ? max(2, (int) ($get('taksit_sayisi') ?? 2))
            : 1;
        $aralikGun = max(1, (int) ($get('taksit_araligi_gun') ?? 30));
        $baslangic = now()->startOfDay();
        $ilkVade = Carbon::parse((string) ($get('vade_tarihi') ?? now()->addDays(30)->toDateString()))->startOfDay();
        $taksitTutari = $anapara / max(1, $taksitSayisi);
        $toplam = 0.0;

        for ($index = 0; $index < $taksitSayisi; $index++) {
            $vade = $ilkVade->copy()->addDays($aralikGun * $index);
            $gun = max(0, $baslangic->diffInDays($vade, false));
            $donem = $tip === 'aylik' ? ($gun / 30) : ($gun / 365);
            $toplam += $taksitTutari * ($oran / 100) * $donem;
        }

        return round($toplam, 2);
    }

    /**
     * @return array<int, float>
     */
    private static function vadeliTaksitTutarlari(Forms\Get $get, float $anapara, float $planlanacak, int $taksitSayisi): array
    {
        $taksitSayisi = max(1, $taksitSayisi);
        $tip = (string) ($get('kanal') ?? '') === 'veresiye'
            ? 'tek_seferlik'
            : (string) ($get('vade_farki_tipi') ?: 'aylik');
        $oran = max(0, self::tutar($get('vade_farki_orani') ?? 0));

        if (! self::dogruMu($get('vade_farki_uygula')) || ! in_array($tip, ['aylik', 'yillik'], true) || $oran <= 0) {
            return self::esitTaksitTutarlari($planlanacak, $taksitSayisi);
        }

        $anaTaksitler = self::esitTaksitTutarlari($anapara, $taksitSayisi);
        $aralikGun = max(1, (int) ($get('taksit_araligi_gun') ?? 30));
        $baslangic = now()->startOfDay();
        $ilkVade = Carbon::parse((string) ($get('vade_tarihi') ?? now()->addDays(30)->toDateString()))->startOfDay();
        $tutarlar = [];

        foreach ($anaTaksitler as $index => $anaTaksit) {
            $vade = $ilkVade->copy()->addDays($aralikGun * $index);
            $gun = max(0, $baslangic->diffInDays($vade, false));
            $donem = $tip === 'aylik' ? ($gun / 30) : ($gun / 365);
            $tutarlar[] = round($anaTaksit + ($anaTaksit * ($oran / 100) * $donem), 2);
        }

        $fark = round($planlanacak - array_sum($tutarlar), 2);
        $tutarlar[$taksitSayisi - 1] = round(($tutarlar[$taksitSayisi - 1] ?? 0) + $fark, 2);

        return $tutarlar;
    }

    /**
     * @return array<int, float>
     */
    private static function esitTaksitTutarlari(float $toplam, int $adet): array
    {
        $adet = max(1, $adet);
        $baz = floor(($toplam / $adet) * 100) / 100;
        $tutarlar = array_fill(0, $adet, $baz);
        $tutarlar[$adet - 1] = round($tutarlar[$adet - 1] + ($toplam - array_sum($tutarlar)), 2);

        return $tutarlar;
    }

    private static function hesapSecenekleri(TeknikServisKaydi $record, string $tip): array
    {
        $firmaId = (int) ($record->firma_id ?? 0);

        if ($firmaId < 1) {
            return [];
        }

        $cacheKey = $firmaId.'|'.$tip;
        if (array_key_exists($cacheKey, self::$hesapSecenekCache)) {
            return self::$hesapSecenekCache[$cacheKey];
        }

        $satirlar = match ($tip) {
            'kasa' => KasaHesabi::query()
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->get(['id', 'ad', 'para_birimi']),
            'banka' => BankaHesabi::query()
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->get(['id', 'ad', 'para_birimi']),
            'pos' => PosHesabi::query()
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->get(['id', 'ad', 'para_birimi']),
            default => collect(),
        };

        $secenekler = [];
        foreach ($satirlar as $hesap) {
            $paraBirimi = strtoupper((string) ($hesap->para_birimi ?: 'TRY'));
            $id = (int) $hesap->id;

            self::$hesapParaBirimiCache[$tip][$id] = $paraBirimi;
            $secenekler[$id] = $hesap->ad.' ('.$paraBirimi.')';
        }

        return self::$hesapSecenekCache[$cacheKey] = $secenekler;
    }

    private static function hedefParaBirimiGuncelle(TeknikServisKaydi $record, string $tip, int $hesapId, Forms\Set $set, Forms\Get $get): void
    {
        $paraBirimi = self::hesapParaBirimi($tip, $hesapId) ?? self::kaynakParaBirimi($record);
        $set('hedef_para_birimi', $paraBirimi);

        if ($paraBirimi === strtoupper((string) ($get('kaynak_para_birimi') ?: self::kaynakParaBirimi($record)))) {
            $set('doviz_kuru', null);
            $set('hedef_tutar', null);
            return;
        }

        self::otomatikKurDoldur($record, $get, $set, 'normal');
        self::hedefTutarGuncelle($get, $set, 'normal');
    }

    private static function pesinatHedefParaBirimiGuncelle(TeknikServisKaydi $record, string $tip, int $hesapId, Forms\Set $set, Forms\Get $get): void
    {
        $paraBirimi = self::hesapParaBirimi($tip, $hesapId) ?? self::kaynakParaBirimi($record);
        $set('pesinat_hedef_para_birimi', $paraBirimi);
        $set('pesinat_doviz_kuru', null);
        $set('pesinat_hedef_tutar', null);

        if ($paraBirimi === strtoupper((string) ($get('plan_para_birimi') ?: self::kaynakParaBirimi($record)))) {
            return;
        }

        self::otomatikKurDoldur($record, $get, $set, 'pesinat');
        self::hedefTutarGuncelle($get, $set, 'pesinat');
    }

    /**
     * @return array{kaynak:string,hedef:string,tutar:string,kur:string,hedef_tutar:string,kur_turu:string}
     */
    private static function kurAlanlari(string $baglam): array
    {
        if ($baglam === 'pesinat') {
            return [
                'kaynak' => 'plan_para_birimi',
                'hedef' => 'pesinat_hedef_para_birimi',
                'tutar' => 'pesinat_tutari',
                'kur' => 'pesinat_doviz_kuru',
                'hedef_tutar' => 'pesinat_hedef_tutar',
                'kur_turu' => 'pesinat_doviz_kuru_turu',
            ];
        }

        return [
            'kaynak' => 'kaynak_para_birimi',
            'hedef' => 'hedef_para_birimi',
            'tutar' => 'tutar',
            'kur' => 'doviz_kuru',
            'hedef_tutar' => 'hedef_tutar',
            'kur_turu' => 'doviz_kuru_turu',
        ];
    }

    private static function hedefTutarOnizleme(Forms\Get $get, string $baglam): string
    {
        $alanlar = self::kurAlanlari($baglam);
        $tutar = (string) ($get($alanlar['tutar']) ?? '0');
        $kur = (string) ($get($alanlar['kur']) ?? '0');
        $kaynak = strtoupper((string) ($get($alanlar['kaynak']) ?? ''));
        $hedef = strtoupper((string) ($get($alanlar['hedef']) ?? ''));

        if (bccomp($tutar, '0', 8) <= 0 || (float) $kur <= 0 || $kaynak === '' || $hedef === '' || $kaynak === $hedef) {
            return '';
        }

        return $kaynak === 'TRY' && $hedef !== 'TRY'
            ? bcdiv($tutar, $kur, 8)
            : bcmul($tutar, $kur, 8);
    }

    private static function hedefTutarGuncelle(Forms\Get $get, Forms\Set $set, string $baglam): void
    {
        $alanlar = self::kurAlanlari($baglam);
        $set($alanlar['hedef_tutar'], self::hedefTutarOnizleme($get, $baglam));
    }

    private static function kurGuncelleHedefTutardan(Forms\Get $get, Forms\Set $set, string $baglam): void
    {
        $alanlar = self::kurAlanlari($baglam);
        $tutar = (string) ($get($alanlar['tutar']) ?? '0');
        $hedefTutar = (string) ($get($alanlar['hedef_tutar']) ?? '0');
        $kaynak = strtoupper((string) ($get($alanlar['kaynak']) ?? ''));
        $hedef = strtoupper((string) ($get($alanlar['hedef']) ?? ''));

        if (bccomp($tutar, '0', 8) <= 0 || bccomp($hedefTutar, '0', 8) <= 0 || $kaynak === '' || $hedef === '' || $kaynak === $hedef) {
            return;
        }

        $kur = $kaynak === 'TRY' && $hedef !== 'TRY'
            ? bcdiv($tutar, $hedefTutar, 8)
            : bcdiv($hedefTutar, $tutar, 8);

        $set($alanlar['kur'], $kur);
    }

    private static function otomatikKurDoldur(TeknikServisKaydi $record, Forms\Get $get, Forms\Set $set, string $baglam): void
    {
        $alanlar = self::kurAlanlari($baglam);
        if ((string) ($get($alanlar['kur_turu']) ?? 'otomatik') !== 'otomatik') {
            return;
        }

        $kaynak = strtoupper((string) ($get($alanlar['kaynak']) ?: self::kaynakParaBirimi($record)));
        $hedef = strtoupper((string) ($get($alanlar['hedef']) ?? ''));
        if ($kaynak === '' || $hedef === '' || $kaynak === $hedef) {
            return;
        }

        $kur = self::otomatikKurBul($kaynak, $hedef, (string) ($get('tarih') ?? now()->format('Y-m-d H:i')));
        if ($kur === null) {
            return;
        }

        $set($alanlar['kur'], $kur);
        self::hedefTutarGuncelle($get, $set, $baglam);
    }

    private static function otomatikKurBul(string $kaynak, string $hedef, string $tarih): ?string
    {
        $gun = Carbon::parse($tarih)->toDateString();
        $kurTipi = self::otomatikKurTipiBelirle($kaynak, $hedef);

        try {
            $sonuc = app(DovizKurServisi::class)->otomatikKurGetirKurTipi($kaynak, $hedef, $gun, $kurTipi);
            $kur = number_format((float) ($sonuc['kur'] ?? 0), 8, '.', '');
            if ($kaynak === 'TRY' && $hedef !== 'TRY' && (float) $kur > 0) {
                $kur = number_format((float) bcdiv('1', $kur, 8), 8, '.', '');
            }

            return (float) $kur > 0 ? $kur : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function otomatikKurTipiBelirle(string $kaynak, string $hedef): string
    {
        $kaynak = strtoupper(trim($kaynak));
        $hedef = strtoupper(trim($hedef));

        if ($kaynak !== 'TRY' && $hedef === 'TRY') {
            return 'alis';
        }

        return 'satis';
    }

    private static function kurGosterimMetni(Forms\Get $get, string $baglam): string
    {
        $alanlar = self::kurAlanlari($baglam);
        $kaynak = strtoupper((string) ($get($alanlar['kaynak']) ?? ''));
        $hedef = strtoupper((string) ($get($alanlar['hedef']) ?? ''));
        $kur = number_format((float) ($get($alanlar['kur']) ?? 0), 8, '.', '');

        if ($kaynak === '' || $hedef === '' || (float) $kur <= 0) {
            return 'Hesaplamada kullanılacak kur bu alandaki değerdir.';
        }

        $etiket = self::otomatikKurTipiBelirle($kaynak, $hedef) === 'alis' ? 'Alış kuru' : 'Satış kuru';
        $ters = number_format((float) bcdiv('1', $kur, 8), 8, '.', '');

        if ($kaynak === 'TRY' && $hedef !== 'TRY') {
            return 'Kullanılan kur: '.$etiket.' ('.$kur.') | 1 '.$hedef.' = '.$kur.' TRY | 1 TRY = '.$ters.' '.$hedef;
        }

        if ($kaynak !== 'TRY' && $hedef === 'TRY') {
            return 'Kullanılan kur: '.$etiket.' ('.$kur.') | 1 '.$kaynak.' = '.$kur.' TRY | 1 TRY = '.$ters.' '.$kaynak;
        }

        return 'Kullanılan kur: '.$etiket.' ('.$kur.') | 1 '.$kaynak.' = '.$kur.' '.$hedef.' | 1 '.$hedef.' = '.$ters.' '.$kaynak;
    }

    private static function hesapParaBirimi(string $tip, int $hesapId): ?string
    {
        if ($hesapId < 1) {
            return null;
        }

        if (array_key_exists($hesapId, self::$hesapParaBirimiCache[$tip] ?? [])) {
            return self::$hesapParaBirimiCache[$tip][$hesapId];
        }

        $paraBirimi = match ($tip) {
            'kasa' => KasaHesabi::query()->whereKey($hesapId)->value('para_birimi'),
            'banka' => BankaHesabi::query()->whereKey($hesapId)->value('para_birimi'),
            'pos' => PosHesabi::query()->whereKey($hesapId)->value('para_birimi'),
            default => null,
        };

        $paraBirimi = $paraBirimi ? strtoupper((string) $paraBirimi) : null;
        if ($paraBirimi !== null) {
            self::$hesapParaBirimiCache[$tip][$hesapId] = $paraBirimi;
        }

        return $paraBirimi;
    }

    private static function farkliParaBirimiSeciliMi(TeknikServisKaydi $record, Forms\Get $get): bool
    {
        $kaynak = strtoupper((string) ($get('kaynak_para_birimi') ?: self::kaynakParaBirimi($record)));
        $hedef = strtoupper((string) ($get('hedef_para_birimi') ?? ''));

        return $kaynak !== '' && $hedef !== '' && $kaynak !== $hedef;
    }

    private static function pesinatFarkliParaBirimiMi(TeknikServisKaydi $record, Forms\Get $get): bool
    {
        $kaynak = strtoupper((string) ($get('plan_para_birimi') ?: self::kaynakParaBirimi($record)));
        $hedef = strtoupper((string) ($get('pesinat_hedef_para_birimi') ?? ''));

        return self::pesinatVarMi($get) && $kaynak !== '' && $hedef !== '' && $kaynak !== $hedef;
    }

    private static function bagliFatura(TeknikServisKaydi $record): ?Fatura
    {
        $baglanti = TeknikServisMuhasebeBaglantisi::query()
            ->where('firma_id', (int) ($record->firma_id ?? 0))
            ->where('teknik_servis_kaydi_id', (int) $record->getKey())
            ->where('islem_tipi', 'satis')
            ->whereNotNull('satis_faturasi_id')
            ->orderByDesc('id')
            ->first();

        if (! $baglanti?->satis_faturasi_id) {
            return null;
        }

        return Fatura::query()->find((int) $baglanti->satis_faturasi_id);
    }

    private static function bagliFaturaMetni(TeknikServisKaydi $record): string
    {
        $fatura = self::bagliFatura($record);

        if (! $fatura) {
            return 'Bağlı fatura henüz oluşmamış. Tahsilat kaydı avans olarak tutulur.';
        }

        $faturaNo = (string) ($fatura->fatura_no ?: ('#'.$fatura->id));
        $acikTutar = number_format((float) ($fatura->acik_tutar ?? 0), 2, ',', '.');
        $paraBirimi = strtoupper((string) ($fatura->para_birimi ?: 'TRY'));

        return $faturaNo.' | Açık tutar: '.$acikTutar.' '.$paraBirimi;
    }

    private static function paraMetni(float $tutar, string $paraBirimi): string
    {
        return number_format($tutar, 2, ',', '.').' '.$paraBirimi;
    }

    private static function tutar(mixed $deger): float
    {
        return max(0, (float) str_replace(',', '.', (string) $deger));
    }
}
