<?php

namespace App\TeknikServis\Filament;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\StokKarti;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisMuhasebeBaglantisi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaSinifi;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\TeknikServis\Enumlar\TeknikServisMuhasebeIslemTipi;
use App\TeknikServis\Enumlar\TeknikServisMuhasebeSenkronDurumu;
use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServisGiderFaturasiDestegi
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function formSchema(TeknikServisKaydi $servis): array
    {
        $firmaId = (int) ($servis->firma_id ?? 0);

        return [
            Forms\Components\Placeholder::make('servis_bilgisi')
                ->label('Servis kaydı')
                ->content('#'.(int) $servis->getKey().' / '.((string) ($servis->fis_no ?: '-'))),
            Forms\Components\Grid::make(['default' => 1, 'xl' => 4])
                ->schema([
                    Forms\Components\Select::make('cari_id')
                        ->label('Cari')
                        ->getSearchResultsUsing(fn (string $search): array => Cari::query()
                            ->where('firma_id', $firmaId)
                            ->where('ad', 'like', '%'.$search.'%')
                            ->orderBy('ad')
                            ->limit(50)
                            ->pluck('ad', 'id')
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => $value
                            ? Cari::query()->where('firma_id', $firmaId)->whereKey((int) $value)->value('ad')
                            : null)
                        ->default((int) ($servis->cari_id ?? 0) ?: null)
                        ->searchable()
                        ->required(),
                    Forms\Components\Select::make('tur')
                        ->label('Fatura türü')
                        ->options([
                            FaturaTuru::Gelen->value => FaturaTuru::Gelen->etiket(),
                        ])
                        ->default(FaturaTuru::Gelen->value)
                        ->disabled()
                        ->dehydrated(),
                    Forms\Components\DatePicker::make('tarih')
                        ->label('Fatura tarihi')
                        ->default(now())
                        ->native(false)
                        ->required(),
                    Forms\Components\Select::make('para_birimi')
                        ->label('Para birimi')
                        ->options(fn (): array => FaturaKaynagi::paraBirimiSecenekleri($firmaId))
                        ->default('TRY')
                        ->searchable()
                        ->live()
                        ->required(),
                ]),
            Forms\Components\Section::make('Kalemler')
                ->schema([
                    static::kalemlerRepeater($firmaId),
                ])
                ->columnSpanFull(),
            Forms\Components\Section::make('Tutar Özeti')
                ->schema(FaturaKaynagi::tutarOzetiFormAlanlari())
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\Section::make('Açıklamalar')
                ->schema([
                    Forms\Components\Textarea::make('aciklama')
                        ->label('Açıklama')
                        ->default(static::varsayilanAciklama($servis))
                        ->rows(3),
                    Forms\Components\Textarea::make('notlar')
                        ->label('Not')
                        ->rows(3),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Forms\Components\Hidden::make('durum')
                ->default(FaturaDurumu::Taslak->value)
                ->dehydrated(),
            Forms\Components\Hidden::make('doviz_kuru')
                ->default(1)
                ->dehydrated(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function varsayilanFormData(TeknikServisKaydi $servis): array
    {
        $kalem = FaturaKaynagi::hesaplaKalemSatiri([
            'sira_no' => 1,
            'stok_id' => null,
            'birim' => 'AD',
            'miktar' => 1,
            'birim_fiyat' => 0,
            'indirim_orani' => 0,
            'indirim_tutari' => 0,
            'kdv_orani' => 20,
            'kdv_tutari' => 0,
            'para_birimi' => 'TRY',
            'kalem_tipi' => 'stok_kalemi',
        ]);

        return FaturaKaynagi::hesaplaFormKalemleriVeOzet([
            'cari_id' => (int) ($servis->cari_id ?? 0) ?: null,
            'tur' => FaturaTuru::Gelen->value,
            'tarih' => now()->toDateString(),
            'para_birimi' => 'TRY',
            'doviz_kuru' => 1,
            'durum' => FaturaDurumu::Taslak->value,
            'odendi_tutari' => 0,
            'tevkifat_orani' => 0,
            'kalemler' => [array_merge($kalem, [
                'sira_no' => 1,
                'satir_no' => 1,
                'stok_kodu_secim' => null,
            ])],
            'aciklama' => static::varsayilanAciklama($servis),
            'notlar' => null,
        ]);
    }

    public static function varsayilanAciklama(TeknikServisKaydi $servis): string
    {
        $servisId = (int) $servis->getKey();
        $fisNo = trim((string) ($servis->fis_no ?? ''));

        return $fisNo !== ''
            ? 'Teknik Servis modülündeki servis kaydı #'.$servisId.' (Fiş No: '.$fisNo.') üzerinden oluşturuldu.'
            : 'Teknik Servis modülündeki servis kaydı #'.$servisId.' üzerinden oluşturuldu.';
    }

    public static function kaydet(TeknikServisKaydi $servis, array $data): Fatura
    {
        $firmaId = (int) ($servis->firma_id ?? 0);
        $servisId = (int) $servis->getKey();

        if ($firmaId < 1 || $servisId < 1) {
            throw ValidationException::withMessages([
                'cari_id' => 'Servis kaydı bulunamadı.',
            ]);
        }

        $hazirVeri = FaturaKaynagi::hesaplaFormKalemleriVeOzet(array_merge($data, [
            'tur' => $data['tur'] ?? FaturaTuru::Gelen->value,
            'durum' => $data['durum'] ?? FaturaDurumu::Taslak->value,
            'para_birimi' => strtoupper((string) ($data['para_birimi'] ?? 'TRY')),
            'doviz_kuru' => (float) ($data['doviz_kuru'] ?? 1),
            'odendi_tutari' => (float) ($data['odendi_tutari'] ?? 0),
        ]));

        static::validateReferences($hazirVeri, $firmaId);

        // Formdaki fatura tarihi yalnızca gün bilgisini taşır. Faturalar tablosu
        // dateTime kullandığı için saat kısmı aksi halde 00:00:00 olarak kaydolur.
        // Seçilen tarihi koruyup kayıt anındaki güncel saati ekliyoruz.
        $faturaTarihi = now();
        if (filled($hazirVeri['tarih'] ?? null)) {
            $faturaTarihi = Carbon::parse((string) $hazirVeri['tarih'])
                ->setTimeFrom($faturaTarihi);
        }

        return DB::transaction(function () use ($servis, $hazirVeri, $firmaId, $servisId, $faturaTarihi): Fatura {
            $paraBirimi = strtoupper((string) ($hazirVeri['para_birimi'] ?? 'TRY'));

            $fatura = Fatura::query()->create([
                'firma_id' => $firmaId,
                'cari_id' => (int) $hazirVeri['cari_id'],
                'tur' => (string) $hazirVeri['tur'],
                'fatura_sinifi' => FaturaSinifi::Gider->value,
                'durum' => (string) ($hazirVeri['durum'] ?? FaturaDurumu::Taslak->value),
                'tarih' => $faturaTarihi,
                'doviz_kuru' => (float) ($hazirVeri['doviz_kuru'] ?? 1),
                'ara_toplam' => $hazirVeri['ara_toplam'] ?? 0,
                'baz_ara_toplam' => $hazirVeri['ara_toplam'] ?? 0,
                'toplam_indirim' => $hazirVeri['toplam_indirim'] ?? 0,
                'baz_toplam_indirim' => $hazirVeri['toplam_indirim'] ?? 0,
                'kdv_toplam' => $hazirVeri['kdv_toplam'] ?? 0,
                'baz_kdv_toplam' => $hazirVeri['kdv_toplam'] ?? 0,
                'tevkifat_orani' => $hazirVeri['tevkifat_orani'] ?? 0,
                'genel_toplam' => $hazirVeri['genel_toplam'] ?? 0,
                'baz_genel_toplam' => $hazirVeri['genel_toplam'] ?? 0,
                'odenecek_tutar' => $hazirVeri['odenecek_tutar'] ?? 0,
                'baz_odenecek_tutar' => $hazirVeri['odenecek_tutar'] ?? 0,
                'odendi_tutari' => $hazirVeri['odendi_tutari'] ?? 0,
                'baz_odendi_tutari' => $hazirVeri['odendi_tutari'] ?? 0,
                'acik_tutar' => $hazirVeri['acik_tutar'] ?? 0,
                'baz_acik_tutar' => $hazirVeri['acik_tutar'] ?? 0,
                'genel_indirim_tutari' => $hazirVeri['genel_indirim_tutari'] ?? ($hazirVeri['toplam_indirim'] ?? 0),
                'kdv_dahil_fiyatlandirma_mi' => false,
                'para_birimi' => $paraBirimi,
                'baz_para_birimi' => $paraBirimi,
                'aciklama' => $hazirVeri['aciklama'] ?? static::varsayilanAciklama($servis),
                'notlar' => $hazirVeri['notlar'] ?? null,
                'kaynak_tipi' => 'teknik_servis',
                'islem_tipi' => 'Servis',
                'islem_no' => $servisId,
            ]);

            foreach ((array) ($hazirVeri['kalemler'] ?? []) as $index => $kalem) {
                FaturaKalemi::query()->create([
                    'firma_id' => $firmaId,
                    'fatura_id' => (int) $fatura->getKey(),
                    'satir_no' => (int) ($kalem['satir_no'] ?? ($index + 1)),
                    'kalem_tipi' => (string) ($kalem['kalem_tipi'] ?? 'stok_kalemi'),
                    'stok_id' => ! empty($kalem['stok_id']) ? (int) $kalem['stok_id'] : null,
                    'birim' => (string) ($kalem['birim'] ?? 'AD'),
                    'hizmet_mi' => false,
                    'aciklama' => $kalem['aciklama'] ?? null,
                    'miktar' => $kalem['miktar'] ?? 0,
                    'birim_fiyat' => $kalem['birim_fiyat'] ?? 0,
                    'baz_birim_fiyat' => $kalem['birim_fiyat'] ?? 0,
                    'indirim_orani' => $kalem['indirim_orani'] ?? 0,
                    'kdv_orani' => $kalem['kdv_orani'] ?? 0,
                    'satir_indirim_tutari' => $kalem['satir_indirim_tutari'] ?? ($kalem['indirim_tutari'] ?? 0),
                    'indirim_tutari' => $kalem['indirim_tutari'] ?? 0,
                    'baz_indirim_tutari' => $kalem['indirim_tutari'] ?? 0,
                    'net_tutar' => $kalem['net_tutar'] ?? 0,
                    'baz_net_tutar' => $kalem['net_tutar'] ?? 0,
                    'kdv_tutari' => $kalem['kdv_tutari'] ?? 0,
                    'baz_kdv_tutari' => $kalem['kdv_tutari'] ?? 0,
                    'satir_toplami' => $kalem['satir_toplami'] ?? 0,
                    'baz_satir_toplami' => $kalem['satir_toplami'] ?? 0,
                    'satir_genel_toplam' => $kalem['satir_genel_toplam'] ?? ($kalem['toplam'] ?? 0),
                    'baz_satir_genel_toplam' => $kalem['satir_genel_toplam'] ?? ($kalem['toplam'] ?? 0),
                    'para_birimi' => $paraBirimi,
                    'baz_para_birimi' => $paraBirimi,
                    'toplam' => $kalem['toplam'] ?? 0,
                ]);
            }

            TeknikServisMuhasebeBaglantisi::query()->create([
                'firma_id' => $firmaId,
                'teknik_servis_kaydi_id' => $servisId,
                'islem_tipi' => TeknikServisMuhasebeIslemTipi::Gider->value,
                'idempotency_key' => 'teknik_servis:'.$servisId.':gider_faturasi:'.$fatura->getKey(),
                'gider_faturasi_id' => (int) $fatura->getKey(),
                'senkron_durumu' => TeknikServisMuhasebeSenkronDurumu::Basarili->value,
                'son_senkron_tarihi' => now(),
                'hata_mesaji' => null,
            ]);

            return $fatura;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function validateReferences(array $data, int $firmaId): void
    {
        if (! empty($data['cari_id']) && ! Cari::query()->where('firma_id', $firmaId)->whereKey((int) $data['cari_id'])->exists()) {
            throw ValidationException::withMessages(['cari_id' => 'Seçilen cari aktif firmaya ait değil.']);
        }

        foreach (($data['kalemler'] ?? []) as $i => $kalem) {
            if (($kalem['kalem_tipi'] ?? '') === 'stok_kalemi' && empty($kalem['stok_id'])) {
                throw ValidationException::withMessages(["kalemler.{$i}.stok_id" => 'Stok kaleminde stok seçimi zorunludur.']);
            }

            if (! empty($kalem['stok_id']) && ! StokKarti::query()->where('firma_id', $firmaId)->whereKey((int) $kalem['stok_id'])->exists()) {
                throw ValidationException::withMessages(["kalemler.{$i}.stok_id" => 'Seçilen stok kartı aktif firmaya ait değil.']);
            }
        }
    }

    /**
     * Teknik Servis ve genel Masraf ekranının aynı stok kalemi bileşenini
     * kullanması için dışarıya açılan ortak üretici.
     */
    public static function stokKalemleriRepeater(int $firmaId): Forms\Components\Repeater
    {
        return static::kalemlerRepeater($firmaId);
    }

    private static function kalemlerRepeater(int $firmaId): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make('kalemler')
            ->label('Kalemler')
            ->reorderable(false)
            ->cloneable(false)
            ->collapsible(false)
            ->extraAttributes(['class' => 'teklif-line-repeater teknik-servis-line-repeater masraf-fatura-line-repeater'])
            ->addAction(fn (Forms\Components\Actions\Action $action): Forms\Components\Actions\Action => $action
                ->icon('heroicon-o-plus')
                ->color('success'))
            ->addActionLabel('Stok kalemi ekle')
            ->afterStateUpdated(function (?array $state, callable $set): void {
                foreach (array_keys($state ?? []) as $index => $key) {
                    $set($key.'.sira_no', $index + 1);
                }
            })
            ->schema([
                Forms\Components\Hidden::make('sira_no')
                    ->dehydrated()
                    ->default(1),
                Forms\Components\Placeholder::make('sira_no_gosterim')
                    ->label('Sıra No')
                    ->content(fn (Get $get): string => (string) ($get('sira_no') ?: 1))
                    ->dehydrated(false)
                    ->extraAttributes(['class' => 'teknik-servis-line-index'])
                    ->columnSpan(['default' => 1, 'xl' => 1]),
                Forms\Components\Hidden::make('stok_kodu_secim')->dehydrated(false),
                Forms\Components\Select::make('stok_id')
                    ->label('Stok Adı')
                    ->extraAttributes(['class' => 'teknik-servis-stok-secici'])
                    ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-stok'])
                    ->getSearchResultsUsing(fn (string $search): array => StokKarti::query()
                        ->where('firma_id', $firmaId)
                        ->where(function ($query) use ($search): void {
                            $query->where('ad', 'like', '%'.$search.'%')
                                ->orWhere('kod', 'like', '%'.$search.'%');
                        })
                        ->orderBy('ad')
                        ->limit(50)
                        ->pluck('ad', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => $value
                        ? StokKarti::query()->where('firma_id', $firmaId)->whereKey((int) $value)->value('ad')
                        : null)
                    ->searchable()
                    ->native(false)
                    ->required()
                    ->live()
                    ->columnSpan(['default' => 1, 'xl' => 4])
                    ->afterStateUpdated(function ($state, callable $set, Get $get) use ($firmaId): void {
                        if (! $state) {
                            $set('stok_kodu_secim', null);

                            return;
                        }

                        $stok = StokKarti::query()->where('firma_id', $firmaId)->find((int) $state);
                        if (! $stok) {
                            return;
                        }

                        $set('stok_kodu_secim', (string) $stok->id);
                        if (! empty($stok->birim)) {
                            $set('birim', $stok->birim);
                        }
                        if ($stok->kdv_orani !== null) {
                            $set('kdv_orani', (float) $stok->kdv_orani);
                        }
                        if ($stok->alis_fiyati !== null) {
                            $set('birim_fiyat', (float) $stok->alis_fiyati);
                        } elseif ($stok->satis_fiyati !== null) {
                            $set('birim_fiyat', (float) $stok->satis_fiyati);
                        }

                        static::kalemleriHesaplaFormdan($get, $set, 'stok_id');
                    }),
                Forms\Components\Select::make('birim')
                    ->label('Birim')
                    ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-birim'])
                    ->options(fn (): array => static::birimSecenekleri($firmaId))
                    ->default('AD')
                    ->searchable()
                    ->native(false)
                    ->columnSpan(['default' => 1, 'xl' => 1]),
                Forms\Components\TextInput::make('miktar')
                    ->label('Miktar')
                    ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-miktar'])
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->columnSpan(['default' => 1, 'xl' => 1])
                    ->afterStateHydrated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set))
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'miktar')),
                Forms\Components\TextInput::make('birim_fiyat')
                    ->label('Birim Fiyat')
                    ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-birim-fiyat'])
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->columnSpan(['default' => 1, 'xl' => 2])
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'birim_fiyat')),
                Forms\Components\TextInput::make('brut_fiyat_gosterim')
                    ->label('Brüt Fiyat')
                    ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-brut-fiyat'])
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpan(['default' => 1, 'xl' => 2]),
                Forms\Components\TextInput::make('indirim_orani')
                    ->label('İskonto Oranı')
                    ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-iskonto-orani'])
                    ->numeric()
                    ->default(0)
                    ->columnSpan(['default' => 1, 'xl' => 2])
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'indirim_orani')),
                Forms\Components\TextInput::make('indirim_tutari')
                    ->label('İskonto Tutarı')
                    ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-iskonto-tutari'])
                    ->numeric()
                    ->default(0)
                    ->columnSpan(['default' => 1, 'xl' => 2])
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'indirim_tutari')),
                Forms\Components\Select::make('kdv_orani')
                    ->label('KDV Oranı')
                    ->extraAttributes(['class' => 'teknik-servis-kdv-secici'])
                    ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-kdv-orani'])
                    ->required()
                    ->options(fn (): array => FaturaKaynagi::vergiOraniSecenekleri($firmaId))
                    ->searchable()
                    ->native(false)
                    ->default('20')
                    ->columnSpan(['default' => 1, 'xl' => 3])
                    ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? 0 : (float) $state)
                    ->live()
                    ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'kdv_orani')),
                Forms\Components\TextInput::make('kdv_tutari')
                    ->label('KDV Tutarı')
                    ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-kdv-tutari'])
                    ->numeric()
                    ->default(0)
                    ->readOnly()
                    ->columnSpan(['default' => 1, 'xl' => 1]),
                Forms\Components\TextInput::make('net_toplam')
                    ->label('Net Toplam')
                    ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-net-toplam'])
                    ->numeric()
                    ->default(0)
                    ->dehydrated(false)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, callable $set): void {
                        static::netToplamdanBirimFiyatHesapla($get, $set);
                    }),
                Forms\Components\Hidden::make('toplam')->default(0),
                Forms\Components\Hidden::make('kalem_tipi')->default('stok_kalemi')->dehydrated(),
                Forms\Components\Hidden::make('satir_indirim_tutari')->default(0),
                Forms\Components\Hidden::make('net_tutar')->default(0),
                Forms\Components\Hidden::make('satir_toplami')->default(0),
                Forms\Components\Hidden::make('satir_genel_toplam')->default(0),
                Forms\Components\Hidden::make('para_birimi')
                    ->default('TRY')
                    ->afterStateHydrated(function (Get $get, callable $set): void {
                        $set('para_birimi', strtoupper((string) ($get('../../para_birimi') ?: 'TRY')));
                    }),
            ])
            ->defaultItems(1)
            ->columns(['default' => 1, 'md' => 2, 'xl' => 18])
            ->columnSpanFull();
    }

    /**
     * @return array<string, string>
     */
    private static function birimSecenekleri(int $firmaId): array
    {
        $liste = Birim::query()
            ->where('aktif_mi', true)
            ->where(function ($query) use ($firmaId): void {
                $query->where('firma_id', $firmaId)
                    ->orWhere(function ($globalQuery): void {
                        $globalQuery->whereNull('firma_id')
                            ->where('is_sabit', true);
                    });
            })
            ->orderBy('kod')
            ->get()
            ->mapWithKeys(fn (Birim $birim) => [
                // Kullanıcıya kod değil, yalnızca okunabilir birim adı gösterilir.
                (string) $birim->kod => (string) ($birim->ad ?: $birim->kod),
            ])
            ->all();

        if (! array_key_exists('AD', $liste)) {
            $liste = ['AD' => 'AD'] + $liste;
        }

        return $liste;
    }

    private static function kalemleriHesaplaFormdan(Get $get, callable $set, string $guncellenenAlan = ''): void
    {
        $satir = [
            'miktar' => $get('miktar'),
            'birim_fiyat' => $get('birim_fiyat'),
            'kdv_orani' => $get('kdv_orani'),
            'kdv_tutari' => $get('kdv_tutari'),
            'indirim_orani' => $get('indirim_orani'),
            'indirim_tutari' => $get('indirim_tutari'),
            'para_birimi' => $get('../../para_birimi') ?: 'TRY',
        ];

        $hesapli = FaturaKaynagi::hesaplaKalemSatiri($satir, $guncellenenAlan);
        $set('brut_fiyat_gosterim', $hesapli['brut_fiyat_gosterim']);
        $set('kdv_tutari', $hesapli['kdv_tutari']);
        $set('indirim_tutari', $hesapli['indirim_tutari']);
        $set('indirim_orani', $hesapli['indirim_orani']);
        $set('toplam', $hesapli['toplam']);
        $set('net_toplam', $hesapli['toplam']);
        $set('net_tutar', $hesapli['net_tutar']);
        $set('satir_toplami', $hesapli['satir_toplami']);
        $set('satir_genel_toplam', $hesapli['satir_genel_toplam']);
        $set('satir_indirim_tutari', $hesapli['satir_indirim_tutari']);
        $set('para_birimi', $hesapli['para_birimi']);

        static::ozetiHesaplaFormdan($get, $set, true);
    }

    private static function netToplamdanBirimFiyatHesapla(Get $get, callable $set): void
    {
        $netToplam = (float) ($get('net_toplam') ?? 0);
        $miktar = (float) ($get('miktar') ?? 0);
        $kdvOrani = (float) ($get('kdv_orani') ?? 0);

        if ($miktar <= 0) {
            return;
        }

        $kdvCarpani = 1 + ($kdvOrani / 100);
        $kdvHaricToplam = $kdvCarpani > 0 ? $netToplam / $kdvCarpani : $netToplam;

        $set('birim_fiyat', round($kdvHaricToplam / $miktar, 8));
        $set('indirim_orani', 0);
        $set('indirim_tutari', 0);
        static::kalemleriHesaplaFormdan($get, $set, 'birim_fiyat');
    }

    private static function ozetiHesaplaFormdan(Get $get, callable $set, bool $repeaterIcinden = true): void
    {
        $kalemler = $repeaterIcinden ? $get('../../kalemler') : $get('kalemler');
        if (! is_array($kalemler)) {
            return;
        }

        $ozet = FaturaKaynagi::hesaplaFormKalemleriVeOzet([
            'kalemler' => $kalemler,
            'odendi_tutari' => $repeaterIcinden ? $get('../../odendi_tutari') : $get('odendi_tutari'),
            'tevkifat_orani' => $repeaterIcinden ? $get('../../tevkifat_orani') : $get('tevkifat_orani'),
            'para_birimi' => $repeaterIcinden ? $get('../../para_birimi') : $get('para_birimi'),
        ]);

        if ($repeaterIcinden) {
            $set('../../mal_hizmet_toplam_tutari_gosterim', $ozet['mal_hizmet_toplam_tutari_gosterim']);
            $set('../../ara_toplam', $ozet['ara_toplam']);
            $set('../../toplam_indirim', $ozet['toplam_indirim']);
            $set('../../kdv_toplam', $ozet['kdv_toplam']);
            $set('../../genel_toplam', $ozet['genel_toplam']);
            $set('../../tevkifat_tutari_gosterim', $ozet['tevkifat_tutari_gosterim']);
            $set('../../genel_indirim_tutari', $ozet['genel_indirim_tutari']);
            $set('../../odenecek_tutar', $ozet['odenecek_tutar']);
            $set('../../acik_tutar', $ozet['acik_tutar']);

            return;
        }

        $set('mal_hizmet_toplam_tutari_gosterim', $ozet['mal_hizmet_toplam_tutari_gosterim']);
        $set('ara_toplam', $ozet['ara_toplam']);
        $set('toplam_indirim', $ozet['toplam_indirim']);
        $set('kdv_toplam', $ozet['kdv_toplam']);
        $set('genel_toplam', $ozet['genel_toplam']);
        $set('tevkifat_tutari_gosterim', $ozet['tevkifat_tutari_gosterim']);
        $set('genel_indirim_tutari', $ozet['genel_indirim_tutari']);
        $set('odenecek_tutar', $ozet['odenecek_tutar']);
        $set('acik_tutar', $ozet['acik_tutar']);
    }
}
