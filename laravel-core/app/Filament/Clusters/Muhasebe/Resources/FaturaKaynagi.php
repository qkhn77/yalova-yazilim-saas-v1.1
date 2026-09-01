<?php

namespace App\Filament\Clusters\Muhasebe\Resources;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateBekleyenFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGelenFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGelenIadeFaturasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGidenFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGidenIadeFaturasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGiderFaturasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateIptalFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateProformaFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\EditFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\ListFaturalar;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\ParaBirimi;
use App\Models\Muhasebe\StokDepoBakiyesi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokOlcuBakiyesi;
use App\Models\Muhasebe\StokOlcusu;
use App\Models\Muhasebe\StokParcasi;
use App\Models\Muhasebe\StokSeriNo;
use App\Models\Muhasebe\VergiOrani;
use App\Models\Proje\IsletmeProjesi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Filament\AbstractKaynaklar\FaturaKaynagi as AbstractFaturaKaynagi;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Muhasebe\Servisler\FaturaKopyalamaServisi;
use App\Muhasebe\Servisler\FaturaOlcuFiyatlandirmaServisi;
use App\Muhasebe\Servisler\StokOlcuBakiyeServisi;
use App\Services\EBelgeHazirlikKontrolServisi;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FaturaKaynagi extends AbstractFaturaKaynagi
{
    private const PARA_BASAMAK = 8;

    /** @var array<string, array<int|string, string>> */
    protected static array $secenekCache = [];

    /** @var array<string, StokKarti|null> */
    protected static array $stokKaydiCache = [];

    /** @var array<string, string|null> */
    protected static array $birimEtiketCache = [];

    /** @var array<string, array<string, mixed>|null> */
    protected static array $cariAdresCache = [];

    /** @var array<string, array<int, string>> */
    protected static array $cariEBelgeUyariCache = [];

    /** @var array<string, string>|null */
    private static ?array $faturaTuruSecenekleri = null;

    /** @var array<string, string>|null */
    private static ?array $faturaDurumuSecenekleri = null;

    private static ?int $aktifFirmaIdCache = null;

    private static ?int $authIdCache = null;

    private static ?bool $yoneticiMiCache = null;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Faturalar';

    /**
     * Fatura ekranlarının değişmez referans formu.
     *
     * Yeni/gelen/giden/iade/gider/proforma ve düzenleme sayfaları bu kaynağın
     * formunu kullandığı için tasarım değişikliği yalnızca bu referans blokta
     * ve yedek güncellenerek yapılmalıdır.
     */
    public static function form(Form $form): Form
    {
        return static::formVarsayilan($form);
    }

    public static function kalemDetaylariGoster(): bool
    {
        // Taslak/beklemede faturalar her zaman referans tam form ile
        // düzenlenir. Eski ?hizli=1 bağlantıları da aynı forma düşürülür;
        // yalnızca durum alanını gösteren ayrı form veri kaybına açıktı.
        return true;
    }

    private static function turAlaniKilitliMi(): bool
    {
        $path = trim((string) request()->path(), '/');

        return str_starts_with($path, 'admin/muhasebe/fatura-kaynagis/create/')
            && $path !== 'admin/muhasebe/fatura-kaynagis/create';
    }

    /**
     * Eski özel tasarım çağrıları için geriye dönük uyumluluk.
     * Tüm fatura sayfaları değişmez referans formunu kullanır.
     */
    public static function formKanakkuGidenOlustur(Form $form): Form
    {
        return static::formVarsayilan($form);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function kalemAlani(Field $alan, bool $kanakkuTekSatir, string $kolonBasligi, array $meta = []): Field
    {
        if (! $kanakkuTekSatir) {
            return $alan;
        }

        // Toggle bileşeni placeholder desteği sunmaz. Kompakt görünümde
        // etiketi gizlemek yeterlidir; diğer alanlar kolon başlığını
        // placeholder olarak göstermeye devam eder.
        $p = $alan->hiddenLabel();
        if (! $alan instanceof Toggle) {
            $p = $p->placeholder($kolonBasligi);
        }
        if (($meta['dar'] ?? false) === true && ! $alan instanceof Toggle) {
            $p = $p->extraInputAttributes(['class' => 'kanakku-kalem-input-dar']);
        }
        // Tek satır görünümünde teknik servisle aynı kolon genişliklerini kullan.
        $p = $p->columnSpan([
            'default' => 1,
            'xl' => (int) ($meta['span'] ?? 1),
        ]);

        return $p;
    }

    /**
     * @param  array<string, mixed>|null  $state
     * @return array<string, mixed>
     */
    private static function kalemSiraNumaralariniNormalizeEt(?array $state): array
    {
        $normalized = [];

        foreach (($state ?? []) as $key => $kalem) {
            if (! is_array($kalem)) {
                $normalized[$key] = $kalem;

                continue;
            }

            $siraNo = count($normalized) + 1;
            $kalem['sira_no'] = $siraNo;
            $kalem['satir_no'] = $siraNo;
            $normalized[$key] = $kalem;
        }

        return $normalized;
    }

    private static function buildKalemlerRepeater(bool $kanakkuTekSatir, bool $relationship = true): Repeater
    {
        $h = fn (Field $field, string $kolon, array $meta = []): Field => static::kalemAlani($field, $kanakkuTekSatir, $kolon, $meta);
        $olcuOlusturmaYetkisi = MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::STOK_OLCU_OLUSTUR);

        $repeater = Repeater::make('kalemler');

        if ($relationship) {
            $repeater = $repeater->relationship();
        }

        $repeater = $repeater
            ->hiddenLabel()
            // Onaylı olarak oluşturma sırasında üst Section disabled olabilir.
            // İlişkiyi gerçekten kaydeden component Repeater olduğu için izin
            // burada verilmelidir; Section seviyesindeki ayar tek başına
            // Repeater'ın disabled kontrolünü geçemez.
            ->saveRelationshipsWhenDisabled(fn (Repeater $component): bool => static::faturaOlusturmaRotasiMi($component->getLivewire()))
            ->itemLabel(fn (): HtmlString => new HtmlString(
                '<div class="fatura-kalemler-item-baslik"><strong>Kalemler</strong><span>Fatura satırlarını aşağıdan ekleyin ve düzenleyin.</span></div>'
            ))
            ->extraItemActions([
                fn (): Action => Action::make('depo_ayrintilari')
                    ->label('Depo')
                    ->icon('heroicon-m-building-storefront')
                    ->tooltip(fn (Action $action): string => static::depoAyrintisiActionDikkatGerekliMi($action)
                        ? 'Depo veya seri bilgisi gerekli' : 'Depo / seri ayrıntılarını aç veya kapat')
                    ->badge(fn (Action $action): ?string => static::depoAyrintisiActionDikkatGerekliMi($action) ? '!' : null)
                    ->badgeColor('warning')
                    ->color(fn (Action $action): string => static::depoAyrintisiActionDikkatGerekliMi($action) ? 'warning' : 'gray')
                    ->iconButton()
                    ->alpineClickHandler(<<<'JS'
                        const section = $el.closest('.fi-fo-repeater-item')?.querySelector('.fatura-kalem-detay-paneli');
                        if (section?.id) {
                            section.classList.toggle('fatura-kalem-detay-paneli--acik');
                            section.dispatchEvent(new CustomEvent(
                                section.classList.contains('fatura-kalem-detay-paneli--acik')
                                    ? 'open-section'
                                    : 'collapse-section',
                                {
                                bubbles: true,
                                detail: { id: section.id },
                                },
                            ));
                        }
                    JS),
            ])
            ->reorderable(false)
            ->cloneable(false)
            ->collapsible(false)
            ->afterStateHydrated(function (Repeater $component, ?array $state): void {
                $normalized = static::kalemSiraNumaralariniNormalizeEt($state);

                foreach ($normalized as &$kalem) {
                    if (! is_array($kalem)) {
                        continue;
                    }

                    if (! array_key_exists('net_toplam_gosterim', $kalem)
                        || $kalem['net_toplam_gosterim'] === null
                        || $kalem['net_toplam_gosterim'] === '') {
                        $kalem['net_toplam_gosterim'] = $kalem['toplam']
                            ?? $kalem['satir_genel_toplam']
                            ?? 0;
                    }
                }
                unset($kalem);

                $component->state($normalized);
            })
            ->afterStateUpdated(function (Repeater $component, ?array $state, callable $set): void {
                $normalized = static::kalemSiraNumaralariniNormalizeEt($state);
                $component->state($normalized);

                foreach ($normalized as $key => $kalem) {
                    if (! is_array($kalem)) {
                        continue;
                    }

                    $set($key.'.sira_no', $kalem['sira_no']);
                    $set($key.'.satir_no', $kalem['satir_no']);
                }
            })
            ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Get $get): array {
                $firmaId = (int) ($get('../../firma_id') ?: static::aktifFirmaId());
                if ($firmaId > 0) {
                    $data['firma_id'] = $firmaId;
                }
                if (empty($data['para_birimi'])) {
                    $data['para_birimi'] = strtoupper((string) ($get('../../para_birimi') ?: 'TRY'));
                }

                return static::hesaplaKalemSatiri($data);
            })
            ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Get $get): array {
                $firmaId = (int) ($get('../../firma_id') ?: static::aktifFirmaId());
                if ($firmaId > 0) {
                    $data['firma_id'] = $firmaId;
                }
                if (empty($data['para_birimi'])) {
                    $data['para_birimi'] = strtoupper((string) ($get('../../para_birimi') ?: 'TRY'));
                }

                return static::hesaplaKalemSatiri($data);
            })
            ->schema([
                $h(TextInput::make('sira_no')
                    ->label('Sıra No')
                    ->numeric()
                    ->readOnly()
                    ->dehydrated()
                    ->default(1)
                    ->extraAttributes(['class' => 'fatura-kalem-sira-no-gosterim']), 'Sıra No', ['dar' => true, 'span' => 1]),
                Hidden::make('satir_no')->dehydrated()->default(1),
                $h(Select::make('stok_kodu_secim')
                    ->label('Stok Kodu')
                    // Teknik Servis stok kalemlerinde kod alanı gösterilmez; arama
                    // yine kod üzerinden yapılabilir, kullanıcı yalnızca adı görür.
                    ->visible(false)
                    ->searchable()
                    ->searchDebounce(300)
                    ->optionsLimit(30)
                    // Başlangıç seçeneklerini dinamik closure olarak vermek, açılış
                    // isteği ile kullanıcının arama isteğini yarıştırıp sonucun ilk
                    // 30 kayıtla ezilmesine neden olur. Aktif firma bu formda sabittir.
                    ->options(static::stokIlkSecenekleri(static::aktifFirmaId()))
                    ->native(false)
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => static::stokKodAramaSonuclari($search, static::formFirmaId($get)))
                    ->getOptionLabelUsing(fn ($value, Get $get): ?string => static::stokKodEtiketi($value, static::formFirmaId($get)))
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, Get $get): void {
                        if (! $state) {
                            return;
                        }

                        $stok = static::stokKaydi((int) $state, static::formFirmaId($get));
                        if (! $stok) {
                            return;
                        }

                        $set('stok_id', (int) $stok->id);
                        $set('birim', static::faturaSatirVarsayilanBirimKodu(static::formFirmaId($get), (int) $stok->id));
                        if ($stok->kdv_orani !== null) {
                            $set('kdv_orani', (float) $stok->kdv_orani);
                        }
                        if ($stok->satis_fiyati !== null) {
                            $set('birim_fiyat', (float) $stok->satis_fiyati);
                        }

                        static::kalemleriHesaplaFormdan($get, $set, 'stok_kodu_secim');
                    }), 'Stok Kodu', ['span' => 2]),
                $h(Select::make('stok_id')
                    ->label('Stok Adı')
                    ->placeholder('Bir seçenek seçin')
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->extraAttributes(['class' => 'fatura-kalem-stok-adi'])
                    ->searchable()
                    ->searchDebounce(300)
                    ->optionsLimit(30)
                    ->options(static::stokIlkSecenekleri(static::aktifFirmaId()))
                    ->native(false)
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => static::stokAdAramaSonuclari($search, static::formFirmaId($get)))
                    ->getOptionLabelUsing(fn ($value, Get $get): ?string => static::stokAdEtiketi($value, static::formFirmaId($get)))
                    ->createOptionForm([
                        TextInput::make('ad')
                            ->label('Stok Adı')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('barkod')
                            ->label('Barkod')
                            ->maxLength(128),
                        Select::make('birim')
                            ->label('Birim')
                            ->options(fn (Get $get): array => static::birimSecenekleri((int) ($get('../../firma_id') ?: static::aktifFirmaId())))
                            ->default('AD')
                            ->searchable()
                            ->required(),
                        TextInput::make('satis_fiyati')
                            ->label('Satış Fiyatı')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Select::make('kdv_orani')
                            ->label('KDV Oranı')
                            ->options(fn (Get $get): array => static::vergiOraniSecenekleri((int) ($get('../../firma_id') ?: static::aktifFirmaId())))
                            ->default('20')
                            ->searchable()
                            ->required(),
                    ])
                    ->createOptionUsing(function (array $data, ComponentContainer $form): int {
                        $firmaId = (int) (data_get($form->getRawState(), 'firma_id') ?: static::aktifFirmaId());
                        if ($firmaId < 1) {
                            throw ValidationException::withMessages([
                                'stok_id' => 'Stok eklemek için aktif firma gereklidir.',
                            ]);
                        }

                        $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
                        $ad = trim((string) ($data['ad'] ?? ''));
                        if ($ad === '') {
                            throw ValidationException::withMessages(['ad' => 'Stok adı zorunludur.']);
                        }

                        return (int) DB::transaction(function () use ($firmaId, $kod, $ad, $data): int {
                            $stokKodu = $kod !== '' ? $kod : StokKartiKaynagi::stokKodUret($firmaId);
                            if (StokKarti::query()
                                ->where('firma_id', $firmaId)
                                ->where('kod', $stokKodu)
                                ->exists()) {
                                throw ValidationException::withMessages(['kod' => 'Bu stok kodu firmada zaten kullanılıyor.']);
                            }

                            $stok = StokKarti::query()->create([
                                'firma_id' => $firmaId,
                                'kod' => $stokKodu,
                                'ad' => $ad,
                                'barkod' => trim((string) ($data['barkod'] ?? '')) ?: null,
                                'tur' => StokKartiTuru::TicariMal->value,
                                'birim' => Str::upper(trim((string) ($data['birim'] ?? 'AD'))) ?: 'AD',
                                'satis_fiyati' => (float) ($data['satis_fiyati'] ?? 0),
                                'alis_fiyati' => 0,
                                'kdv_orani' => (float) ($data['kdv_orani'] ?? 20),
                                'para_birimi' => 'TRY',
                                'durum' => HesapDurumu::Aktif->value,
                                'stok_takip' => true,
                                'stok_miktari' => 0,
                                'minimum_stok' => 0,
                                'maksimum_stok' => 0,
                                'negative_flag' => false,
                            ]);

                            static::$secenekCache = [];
                            static::$stokKaydiCache = [];
                            Cache::forget('muhasebe:fatura:stok-ilk-secenekleri:v1:'.$firmaId);

                            return (int) $stok->getKey();
                        });
                    })
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set, Get $get): void {
                        if (! $state) {
                            $set('stok_kodu_secim', null);

                            return;
                        }

                        $stok = static::stokKaydi(
                            (int) $state,
                            (int) ($get('../../firma_id') ?: static::aktifFirmaId()),
                        );
                        if (! $stok) {
                            return;
                        }

                        $set('stok_kodu_secim', (string) $stok->id);
                        $set('miktar', $get('miktar') ?: 1);
                        $set('birim', static::faturaSatirVarsayilanBirimKodu(static::formFirmaId($get), (int) $stok->id));
                        if ($stok->kdv_orani !== null) {
                            $set('kdv_orani', (float) $stok->kdv_orani);
                        }
                        if ($stok->satis_fiyati !== null) {
                            $set('birim_fiyat', (float) $stok->satis_fiyati);
                        }

                        $varsayilanDepo = static::varsayilanDepoIdForForm(
                            static::formFirmaId($get),
                            (int) $stok->id,
                        );
                        if ($varsayilanDepo !== null) {
                            $set('depo_id', $varsayilanDepo);
                        }

                        $fiyatBirimleri = static::olculuFiyatBirimiSecenekleri((int) $stok->id);
                        $set('fiyat_birimi_id', array_key_first($fiyatBirimleri));
                        $set('olcu_satis_birimi', static::faturaSatirVarsayilanBirimKodu(static::formFirmaId($get), (int) $stok->id));
                        $set('fiyat_miktari', null);
                        $set('olcu_donusum_snapshot', null);
                        $set('dogrudan_ortak_adet_fiyati', false);
                        // Ölçü tanımı stok kartında kalır; fatura satırı ölçü dağılımı seçmez.
                        $set('olcu_dagilimlari', []);
                        if (! static::kalemSeriTakibiAktifMi($get)) {
                            foreach (['seri_nolari', 'garanti_baslangic_tarihi', 'garanti_bitis_tarihi'] as $alan) {
                                $set($alan, null);
                            }
                        }
                        static::kalemleriHesaplaFormdan($get, $set, 'stok_id');
                    }), 'Stok Adı', ['span' => 4]),
                Section::make('Depo / Seri')
                    ->compact()
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Get $get): bool => static::depoAlanGosterilmeli(
                            static::formFirmaId($get),
                            static::formStokId($get),
                        )
                        || static::kalemSeriTakibiAktifMi($get))
                    ->schema([
                        $h(Select::make('depo_id')
                            ->label('Depo')
                            ->placeholder('Deposuz')
                            ->options(fn (Get $get): array => static::depoSecenekleriForForm(
                                static::formFirmaId($get),
                                static::formStokId($get),
                            ))
                            ->default(fn (Get $get): ?int => static::varsayilanDepoIdForForm(
                                static::formFirmaId($get),
                                static::formStokId($get),
                            ))
                            ->visible(fn (Get $get): bool => static::depoAlanGosterilmeli(
                                static::formFirmaId($get),
                                static::formStokId($get),
                            ))
                            ->required(fn (Get $get): bool => static::depoAlanGosterilmeli(
                                static::formFirmaId($get),
                                static::formStokId($get),
                            ))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->afterStateHydrated(function (Select $component, $state, Get $get): void {
                                if (filled($state)) {
                                    return;
                                }

                                $options = static::depoSecenekleriForForm(
                                    static::formFirmaId($get),
                                    static::formStokId($get),
                                );
                                $varsayilan = static::varsayilanDepoIdForForm(
                                    static::formFirmaId($get),
                                    static::formStokId($get),
                                );
                                if ($varsayilan !== null && array_key_exists($varsayilan, $options)) {
                                    $component->state($varsayilan);
                                } elseif (count($options) === 1) {
                                    $component->state(array_key_first($options));
                                }
                            })
                            ->extraAttributes(['class' => 'fatura-olcu-depo-field'])
                            ->columnSpan(['default' => 12, 'md' => 2, 'xl' => 2]), 'Depo', ['span' => 2]),
                        Hidden::make('kaynak_fatura_kalemi_id')->dehydrated(),
                        /* $h(Select::make('parti_siralama')
                            ->label('Parti / parça sıralaması')
                            ->options([
                                'son_kullanma' => 'Son kullanma tarihi (yakın önce)',
                                'tarih_yeni' => 'Kayıt tarihi (yeni önce)',
                                'tarih_eski' => 'Kayıt tarihi (eski önce)',
                                'miktar_cok' => 'Kalan miktar (çoktan aza)',
                                'miktar_az' => 'Kalan miktar (azdan çoğa)',
                                'olcu_buyuk' => 'Ölçü / m² (büyükten küçüğe)',
                                'olcu_kucuk' => 'Ölçü / m² (küçükten büyüğe)',
                                'kod' => 'Parça / parti kodu',
                                'parti' => 'Parti / lot numarası',
                                'maliyet_cok' => 'm² maliyeti (yüksekten düşüğe)',
                                'maliyet_az' => 'm² maliyeti (düşükten yükseğe)',
                                'desen' => 'Renk / desen',
                            ])
                            ->default('son_kullanma')
                            ->live()
                            ->native(false)
                            ->dehydrated(false)
                            ->visible(fn (Get $get): bool => static::kalemPartiTakibiAktifMi($get)), 'Parti / parça sıralaması', ['span' => 2]),
                        $h(TextInput::make('parca_kodu')
                            ->label('Parti / Lot No')
                            ->maxLength(128)
                            ->visible(fn (Get $get): bool => static::kalemPartiTakibiAktifMi($get))
                            ->datalist(fn (Get $get): array => static::parcaKoduSecenekleri($get))
                            ->helperText(fn (Get $get): string => static::aktifFizikselParcaVarMi($get)
                                ? 'Bu stok fiziksel parça bazında izleniyor. Satışta parça seçimi zorunludur.'
                                : 'Satışta boş bırakırsanız son kullanma tarihi yakın olan parti otomatik seçilir.'), 'Parti / Lot No', ['span' => 2]),
                        $h(Repeater::make('parca_dagilimi')
                            ->label('Parti dağılımı')
                            ->schema([
                                Select::make('parca_kodu')
                                    ->label('Parti / Stok parçası')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search, Get $get): array => static::parcaKoduSecenekleri($get, $search))
                                    ->getOptionLabelUsing(fn ($value, Get $get): ?string => static::parcaKoduEtiketi($get, (string) $value))
                                    ->native(false)
                                    ->required(),
                                TextInput::make('miktar')->label('Miktar')->numeric()->required()->minValue(0.0001),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Parti ekle')
                            ->visible(fn (Get $get): bool => static::kalemPartiTakibiAktifMi($get))
                            ->helperText(fn (Get $get): string => static::aktifFizikselParcaVarMi($get)
                                ? 'Fiziksel parça bulunan stokta satışın hangi parçadan yapılacağı açıkça seçilmelidir. Arama ve yukarıdaki sıralamayı kullanabilirsiniz.'
                                : 'Aynı ürünü birden fazla partiden satacaksanız dağılımı girin. Boş bırakılırsa otomatik seçim yapılır.'), 'Parti dağılımı', ['span' => 4]),
                        $h(DatePicker::make('uretim_tarihi')
                            ->label('Üretim tarihi')
                            ->visible(fn (Get $get): bool => static::kalemPartiTakibiAktifMi($get))
                            ->nullable(), 'Üretim tarihi', ['span' => 1]),
                        $h(DatePicker::make('son_kullanma_tarihi')
                            ->label('Son kullanma tarihi')
                            ->visible(fn (Get $get): bool => static::kalemPartiTakibiAktifMi($get))
                            ->nullable(), 'Son kullanma tarihi', ['span' => 1]),
                        $h(TextInput::make('blok_no')->label('Blok no')->maxLength(128)
                            ->visible(fn (Get $get): bool => static::kalemFizikselParcaTakibiAktifMi($get)), 'Blok no', ['span' => 1]),
                        $h(TextInput::make('ocak_tedarikci')->label('Ocak / tedarikçi')->maxLength(191)
                            ->visible(fn (Get $get): bool => static::kalemFizikselParcaTakibiAktifMi($get)), 'Ocak / tedarikçi', ['span' => 1]),
                        $h(TextInput::make('kalite_sinifi')->label('Kalite sınıfı')->maxLength(64)
                            ->visible(fn (Get $get): bool => static::kalemFizikselParcaTakibiAktifMi($get)), 'Kalite sınıfı', ['span' => 1]),
                        $h(TextInput::make('renk_desen')->label('Renk / desen')->maxLength(191)
                            ->visible(fn (Get $get): bool => static::kalemFizikselParcaTakibiAktifMi($get)), 'Renk / desen', ['span' => 1]),
                        $h(TextInput::make('kalinlik_cm')->label('Kalınlık (cm)')->numeric()->minValue(0)
                            ->visible(fn (Get $get): bool => static::kalemFizikselParcaTakibiAktifMi($get)), 'Kalınlık (cm)', ['span' => 1]),
                        $h(TextInput::make('metrekare')->label('Toplam m²')->numeric()->minValue(0)
                            ->visible(fn (Get $get): bool => static::kalemFizikselParcaTakibiAktifMi($get)), 'Toplam m²', ['span' => 1]),
                        $h(TextInput::make('plaka_no')->label('Plaka no')->maxLength(128)
                            ->visible(fn (Get $get): bool => static::kalemFizikselParcaTakibiAktifMi($get)), 'Plaka no', ['span' => 1]),
                        $h(TextInput::make('parca_no')->label('Parça no')->maxLength(128)
                            ->visible(fn (Get $get): bool => static::kalemFizikselParcaTakibiAktifMi($get)), 'Parça no', ['span' => 1]),
                        */ $h(TagsInput::make('seri_nolari')
                            ->label('Seri No Barkodları')
                            ->placeholder('Seri no yazın ve Enter’a basın')
                            ->splitKeys(['Enter', 'Tab'])
                            ->suggestions(fn (Get $get): array => static::seriNoSecenekleri($get))
                            ->visible(fn (Get $get): bool => static::kalemSeriTakibiAktifMi($get))
                            ->dehydrateStateUsing(fn ($state): array => array_values(array_unique(array_filter(array_map('trim', (array) $state)))))
                            ->helperText('Seri numarasını doğrudan yazıp Enter ile ekleyebilirsiniz. Daha önce kayıtlı seri numaraları yazarken öneri olarak görünür; gelen faturada yeni seri numarası da eklenebilir.'), 'Seri No Barkodları', ['span' => 2]),
                        $h(DatePicker::make('garanti_baslangic_tarihi')
                            ->label('Garanti başlangıcı')
                            ->visible(fn (Get $get): bool => static::kalemSeriTakibiAktifMi($get))
                            ->nullable(), 'Garanti başlangıcı', ['span' => 1]),
                        $h(DatePicker::make('garanti_bitis_tarihi')
                            ->label('Garanti bitişi')
                            ->visible(fn (Get $get): bool => static::kalemSeriTakibiAktifMi($get))
                            ->nullable(), 'Garanti bitişi', ['span' => 1]),
                        $h(Select::make('fiyat_birimi_id')
                            ->label('Fiyat birimi')
                            ->hidden()
                            ->extraAttributes(['class' => 'fatura-olcu-fiyat-field'])
                            ->options(fn (Get $get): array => static::olculuFiyatBirimiSecenekleri(static::formStokId($get)))
                            ->default(fn (Get $get): ?int => array_key_first(static::olculuFiyatBirimiSecenekleri(static::formStokId($get))))
                            ->visible(fn (Get $get): bool => static::kalemOlculuMu($get))
                            ->required(fn (Get $get): bool => static::kalemOlculuMu($get))
                            ->live()
                            ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'fiyat_birimi_id')),
                            'Fiyat birimi', ['dar' => true, 'span' => 2]),
                        $h(Toggle::make('dogrudan_ortak_adet_fiyati')
                            ->label('Ortak adet fiyatı')
                            ->hidden()
                            ->extraAttributes(['class' => 'fatura-olcu-toggle-field'])
                            ->helperText('Farklı ölçü katsayılarında seçili tüm parçalar için aynı adet fiyatını uygular.')
                            ->default(false)
                            ->visible(fn (Get $get): bool => static::kalemOlculuMu($get))
                            ->live()
                            ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'dogrudan_ortak_adet_fiyati')),
                            'Ortak adet fiyatı', ['dar' => true, 'span' => 2]),
                    ])
                    ->columns(['default' => 1, 'md' => 12])
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'fatura-kalem-detay-paneli']),
                $h(Select::make('olcu_satis_birimi')
                    ->label('Satış ölçüsü')
                    ->options(fn (Get $get): array => static::faturaSatirBirimSecenekleri(
                        static::formFirmaId($get),
                        static::formStokId($get),
                        true,
                    ))
                    ->visible(fn (Get $get): bool => static::kalemOlculuParcaliMi($get))
                    ->native(false)
                    ->searchable(false)
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Select $component, $state, Get $get): void {
                        if (blank($state) && filled($get('birim'))) {
                            $component->state($get('birim'));
                        }
                    })
                    ->afterStateUpdated(function (Get $get, callable $set, $state): void {
                        $set('birim', $state);
                        static::kalemleriHesaplaFormdan($get, $set, 'olcu_satis_birimi');
                    })
                    ->extraAttributes([
                        'class' => 'fatura-kalem-olcu-satis-birimi',
                        'title' => 'M² seçerseniz miktar m² üzerinden, Adet seçerseniz adet üzerinden hesaplanır.',
                        'aria-label' => 'Satış ölçüsü. M² veya Adet seçebilirsiniz.',
                    ]), 'Satış ölçüsü', ['dar' => true, 'span' => 1]),
                        $h(Select::make('birim')
                    ->label('Birim')
                    ->placeholder('Seçin')
                    ->helperText(fn (Get $get): ?HtmlString => static::birimYedekSecenekUyarisi(
                        (int) ($get('../../firma_id') ?: static::aktifFirmaId()),
                    ))
                    ->columnSpan(['default' => 2, 'md' => 2, 'xl' => 2])
                    ->extraAttributes(['class' => 'fatura-kalem-birim'])
                    ->options(fn (Get $get): array => static::faturaSatirBirimSecenekleri(
                        static::formFirmaId($get),
                        static::formStokId($get),
                        $kanakkuTekSatir,
                    ))
                    ->visible(fn (Get $get): bool => ! static::kalemOlculuParcaliMi($get))
                    ->default(fn (Get $get): string => static::faturaSatirVarsayilanBirimKodu(
                        static::formFirmaId($get),
                        static::formStokId($get),
                    ))
                    ->afterStateHydrated(function (Select $component, $state, Get $get): void {
                        if (blank($state)) {
                            $component->state(static::faturaSatirVarsayilanBirimKodu(
                                static::formFirmaId($get),
                                static::formStokId($get),
                            ));
                        }
                    })
                    ->searchable(fn (Get $get): bool => ! $kanakkuTekSatir && ! static::kalemOlculuParcaliMi($get))
                    ->searchPrompt('Ara')
                    ->searchDebounce(300)
                    ->optionsLimit(30)
                    ->native($kanakkuTekSatir)
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => static::birimAramaSonuclari(
                        $search,
                        (int) ($get('../../firma_id') ?: static::aktifFirmaId()),
                    ))
                    ->getOptionLabelUsing(fn ($value, Get $get): ?string => static::birimEtiketi(
                        $value,
                        (int) ($get('../../firma_id') ?: static::aktifFirmaId()),
                    ))
                    ->createOptionForm([
                        TextInput::make('kod')->label('Kod')->required()->maxLength(64),
                        TextInput::make('ad')->label('Ad')->required()->maxLength(128),
                    ])
                    ->createOptionUsing(function (array $data, ComponentContainer $form): string {
                        $firmaId = (int) (data_get($form->getRawState(), 'firma_id') ?: static::aktifFirmaId());
                        if ($firmaId < 1) {
                            throw ValidationException::withMessages(['birim' => 'Fatura için firma seçin veya aktif firma oturumu açın.']);
                        }
                        $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
                        $ad = trim((string) ($data['ad'] ?? ''));
                        if ($kod === '' || $ad === '') {
                            throw ValidationException::withMessages(['birim' => 'Kod ve ad zorunludur.']);
                        }
                        if (Birim::tenantScopeOlmadan(fn () => Birim::query()
                            ->where('tanim_firma_kapsami', $firmaId)
                            ->whereRaw('UPPER(kod) = ?', [$kod])
                            ->exists())) {
                            throw ValidationException::withMessages(['kod' => 'Bu kod bu firmada zaten var.']);
                        }

                        $kayit = Birim::query()->create([
                            'firma_id' => $firmaId,
                            'kod' => $kod,
                            'ad' => $ad,
                            'aktif_mi' => true,
                            'is_sabit' => false,
                        ]);

                        static::$secenekCache = [];
                        static::$birimEtiketCache = [];
                        Cache::forget('muhasebe:fatura:birim-secenekleri:v1:'.$firmaId);
                        Cache::forget('muhasebe:fatura:birim-etiketi:v1:'.$firmaId.':'.$kod);

                        return (string) $kayit->kod;
                    })
                    ->live()
                    ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'birim')), 'Birim', ['dar' => true, 'span' => 1]),
                Hidden::make('islem_birimi_id')->dehydrated(),
                $h(TextInput::make('miktar')
                    ->label('Miktar')
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->numeric()
                    ->extraInputAttributes(['class' => 'fatura-kalem-miktar'])
                    ->extraAttributes(['class' => 'fatura-kalem-miktar-alani'])
                    ->default(1)
                    ->afterStateHydrated(function (TextInput $component, $state): void {
                        if (blank($state)) {
                            $component->state(1);
                        }
                    })
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'miktar')), 'Miktar', ['dar' => true, 'span' => 1]),
                $h(TextInput::make('birim_fiyat')
                    ->label('Birim Fiyat')
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->extraAttributes(['class' => 'fatura-kalem-birim-fiyat-alani'])
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'birim_fiyat')), 'Birim Fiyat', ['dar' => true, 'span' => 2]),
                Hidden::make('fiyat_miktari')->dehydrated(),
                Hidden::make('ana_miktar')->dehydrated(),
                Hidden::make('adet_esdegeri')->dehydrated(),
                Hidden::make('olcu_donusum_snapshot')->dehydrated(),
                TextInput::make('brut_fiyat_gosterim')
                    ->label('Brüt Fiyat')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false)
                    ->visible($kanakkuTekSatir)
                    ->columnSpan(['default' => 1, 'xl' => 2]),
                $h(TextInput::make('indirim_orani')
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->label('İskonto Oranı')
                    ->extraAttributes(['class' => 'fatura-kalem-indirim-orani-alani'])
                    ->numeric()
                    ->default(0)
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'indirim_orani')), 'İskonto Oranı', ['dar' => true, 'span' => 1]),
                $h(TextInput::make('indirim_tutari')
                    ->label('İskonto Tutarı')
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->extraAttributes(['class' => 'fatura-kalem-indirim-tutari-alani'])
                    ->numeric()
                    ->default(0)
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'indirim_tutari')), 'İskonto Tutarı', ['dar' => true, 'span' => 2]),
                $h(
                    Select::make('kdv_orani')
                        ->label('KDV Oranı')
                        ->extraAttributes(['class' => 'fatura-kalem-kdv-orani-alani'])
                        ->required()
                        ->options(fn (Get $get): array => static::vergiOraniSecenekleri((int) ($get('../../firma_id') ?: static::aktifFirmaId())))
                        ->default(fn () => StokKartiKaynagi::vergiOranFormAnahtari(20.0))
                        ->columnSpan(['default' => 2, 'md' => 2, 'xl' => 2])
                        ->placeholder('Seçin')
                        ->searchable(! $kanakkuTekSatir)
                        ->searchPrompt('Ara')
                        ->native($kanakkuTekSatir)
                        ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? 0 : (float) $state)
                        ->live()
                        ->afterStateUpdated(fn (Get $get, callable $set) => static::kalemleriHesaplaFormdan($get, $set, 'kdv_orani')),
                    'KDV Oranı', ['dar' => true, 'span' => 3],
                ),
                TextInput::make('kdv_tutari')
                    ->label('KDV Tutarı')
                    ->numeric()
                    ->default(0)
                    ->readOnly()
                    ->extraAttributes(['class' => 'fatura-kalem-kdv-tutari-gosterim-alani'])
                    ->columnSpan(['default' => 1, 'xl' => 1]),
                Hidden::make('toplam')->default(0),
                TextInput::make('net_toplam_gosterim')
                    ->label('Net Toplam')
                    ->numeric()
                    ->default(0)
                    ->readOnly()
                    ->dehydrated(false)
                    ->extraAttributes(['class' => 'fatura-kalem-net-toplam-alani'])
                    ->columnSpan(['default' => 1, 'xl' => 1]),

                TextInput::make('kalem_tipi')->default('stok_kalemi')->dehydrated()->hidden(),
                TextInput::make('satir_indirim_tutari')->numeric()->default(0)->hidden(),
                TextInput::make('net_tutar')->numeric()->default(0)->hidden(),
                TextInput::make('satir_toplami')->numeric()->default(0)->hidden(),
                TextInput::make('satir_genel_toplam')->numeric()->default(0)->hidden(),
                TextInput::make('para_birimi')->default('TRY')->maxLength(3)->hidden(),
            ])
            ->defaultItems(1)
            ->addActionLabel($kanakkuTekSatir ? 'Stok kalemi ekle' : 'Kalem Ekle')
            ->columnSpanFull();

        if ($kanakkuTekSatir) {
            $repeater = $repeater
                ->hiddenLabel()
                ->columns(['default' => 1, 'sm' => 18, 'lg' => 18])
                ->extraAttributes(['class' => 'fatura-kalemler-repeater kanakku-kalemler-repeater kanakku-kalemler-force teklif-line-repeater teknik-servis-line-repeater masraf-fatura-line-repeater']);
        } else {
            $repeater = $repeater
                ->columns(['default' => 1, 'md' => 12, 'xl' => 12])
                ->extraAttributes(['class' => 'fatura-kalemler-repeater']);
        }

        return $repeater;
    }

    private static function formKanakkuGiden(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Section::make()
                    ->heading('Fatura')
                    ->extraAttributes(['class' => 'kanakku-fatura-ust'])
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Section::make('Müşteri bilgileri')
                                    ->schema([
                                        Placeholder::make('kanakku_logo')
                                            ->label('')
                                            ->content(new HtmlString(
                                                '<div class="kanakku-invoice-logo-slot rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-950/40 dark:text-gray-400">Logo / firma görseli (isteğe bağlı)</div>'
                                            )),
                                        Select::make('firma_id')
                                            ->relationship('firma', 'ad')
                                            ->searchable()
                                            ->visible(static::yoneticiMi()),
                                        Select::make('cari_id')
                                            ->label('Cari seç')
                                            ->searchable()
                                            ->getSearchResultsUsing(fn (string $search): array => static::cariAramaSonuclari($search))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::cariEtiketi($value))
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                                $paraBirimi = static::cariParaBirimi((int) $state, (int) ($get('firma_id') ?: static::aktifFirmaId()));
                                                if ($paraBirimi !== null && array_key_exists($paraBirimi, static::paraBirimiSecenekleri((int) ($get('firma_id') ?: static::aktifFirmaId())))) {
                                                    $set('para_birimi', $paraBirimi);
                                                }
                                            })
                                            ->required(fn ($get) => in_array((string) $get('tur'), [FaturaTuru::Gelen->value, FaturaTuru::Giden->value, FaturaTuru::Gider->value, FaturaTuru::SatisIadesi->value, FaturaTuru::AlisIadesi->value, FaturaTuru::IadeFatura->value], true)),
                                        Select::make('bagli_fatura_id')
                                            ->label(fn (Get $get): string => (string) $get('tur') === FaturaTuru::AlisIadesi->value ? 'Kaynak Alış Faturası' : 'Kaynak Satış Faturası')
                                            ->searchable()
                                            ->options(fn (Get $get): array => static::kaynakIadeFaturasiSecenekleri(
                                                (int) ($get('firma_id') ?: static::aktifFirmaId()),
                                                (int) ($get('cari_id') ?: 0),
                                                (string) ($get('tur') ?: FaturaTuru::SatisIadesi->value),
                                            ))
                                            ->getOptionLabelUsing(fn ($value, Get $get): ?string => static::kaynakIadeFaturasiSecenekleri(
                                                (int) ($get('firma_id') ?: static::aktifFirmaId()),
                                                (int) ($get('cari_id') ?: 0),
                                                (string) ($get('tur') ?: FaturaTuru::SatisIadesi->value),
                                            )[(int) $value] ?? null)
                                            ->live()
                                            ->visible(fn (Get $get): bool => in_array((string) $get('tur'), [FaturaTuru::SatisIadesi->value, FaturaTuru::AlisIadesi->value], true))
                                            ->required(fn (Get $get): bool => in_array((string) $get('tur'), [FaturaTuru::SatisIadesi->value, FaturaTuru::AlisIadesi->value], true))
                                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                                $set('kalemler', $state
                                                    ? static::kaynakIadeFaturasiKalemleriniFormata((int) $state, (int) ($get('firma_id') ?: static::aktifFirmaId()), (string) ($get('tur') ?: FaturaTuru::SatisIadesi->value))
                                                    : []);
                                            }),
                                        static::projeAlani(),
                                        static::cariEBelgeUyariAlani(),
                                    ])
                                    ->columns(1)
                                    ->columnSpan(['default' => 12, 'lg' => 7]),
                                Section::make('Fatura detayı')
                                    ->schema([
                                        TextInput::make('fatura_no')->maxLength(64),
                                        TextInput::make('belge_no')->maxLength(64),
                                        TextInput::make('irsaliye_no')->maxLength(64),
                                        Select::make('e_belge_tipi')->options([
                                            'e_fatura' => 'E-Fatura',
                                            'e_arsiv' => 'E-Arşiv',
                                            'kagit' => 'Kağıt Fatura',
                                        ])->default('e_fatura'),
                                        DatePicker::make('tarih')->required(),
                                        DatePicker::make('vade_tarihi'),
                                        Select::make('para_birimi')
                                            ->label('Para birimi')
                                            ->options(fn (Get $get): array => static::paraBirimiSecenekleri((int) ($get('firma_id') ?: static::aktifFirmaId())))
                                            ->default('TRY')
                                            ->searchable(),
                                        TextInput::make('doviz_kuru')->numeric()->default(1),
                                        Select::make('tur')
                                            ->label('Fatura türü')
                                            ->options(fn (): array => static::faturaTuruSecenekleri())
                                            ->required()
                                            ->disabled()
                                            ->dehydrated(),
                                        Select::make('durum')
                                            ->options(fn (): array => static::faturaDurumuSecenekleri())
                                            ->default(FaturaDurumu::Taslak->value)
                                            ->required(),
                                    ])
                                    ->columns(2)
                                    ->columnSpan(['default' => 12, 'lg' => 5]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Kalemler')
                    ->description('Kalemler tek satır, sade tablo görünümünde listelenir.')
                    ->extraAttributes(['class' => 'kanakku-kalemler-section'])
                    ->schema([
                        Placeholder::make('kalemler_tablo_baslik')
                            ->hiddenLabel()
                            ->dehydrated(false)
                            ->content(new HtmlString('
                                <div class="kanakku-kalemler-header-grid">
                                    <span>Sıra No</span>
                                    <span>Stok Kodu</span>
                                    <span>Stok Adı</span>
                                    <span>Birim</span>
                                    <span>Miktar</span>
                                    <span>Birim Fiyat</span>
                                    <span>Brüt Fiyat</span>
                                    <span>İskonto Oranı</span>
                                    <span>İskonto Tutarı</span>
                                    <span>KDV Oranı</span>
                                    <span>KDV Tutarı</span>
                                    <span>Net Toplam</span>
                                </div>
                            ')),
                        static::buildKalemlerRepeater(true),
                    ])
                    ->columnSpanFull(),
                Section::make('Tutar Özeti')
                    ->compact()
                    ->extraAttributes(['class' => 'fatura-tutar-ozeti-section'])
                    ->schema(static::tutarOzetiAlanlari())
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Açıklamalar')
                    ->schema([
                        Textarea::make('aciklama'),
                        Textarea::make('notlar'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    private static function formVarsayilan(Form $form): Form
    {
        $kalemDetaylariGoster = static::kalemDetaylariGoster();

        if (static::hizliDuzenlemeModu()) {
            return $form
                ->columns(1)
                ->schema([
                    Select::make('durum')
                        ->options(fn (): array => static::faturaDurumuSecenekleri())
                        ->default(FaturaDurumu::Taslak->value)
                        ->required()
                        ->native(),
                ]);
        }

        return $form
            ->columns(2)
            ->schema([
                Section::make('Taraf')
                    ->extraAttributes(['class' => 'fatura-taraf-durum-section'])
                    ->schema([
                        Select::make('cari_id')
                            ->label('Cari')
                            ->searchable()
                            ->options(fn (): array => static::cariAramaSonuclari(''))
                            ->optionsLimit(50)
                            ->getSearchResultsUsing(fn (string $search): array => static::cariAramaSonuclari($search))
                            ->getOptionLabelUsing(fn ($value): ?string => static::cariEtiketi($value))
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $paraBirimi = static::cariParaBirimi((int) $state, (int) ($get('firma_id') ?: static::aktifFirmaId()));
                                if ($paraBirimi !== null && array_key_exists($paraBirimi, static::paraBirimiSecenekleri((int) ($get('firma_id') ?: static::aktifFirmaId())))) {
                                    $set('para_birimi', $paraBirimi);
                                }
                            })
                            ->required(fn ($get) => in_array((string) $get('tur'), [FaturaTuru::Gelen->value, FaturaTuru::Giden->value, FaturaTuru::Gider->value, FaturaTuru::SatisIadesi->value, FaturaTuru::AlisIadesi->value, FaturaTuru::IadeFatura->value], true)),
                        Select::make('bagli_fatura_id')
                            ->label(fn (Get $get): string => (string) $get('tur') === FaturaTuru::AlisIadesi->value ? 'Kaynak Alış Faturası' : 'Kaynak Satış Faturası')
                            ->searchable()
                            ->options(fn (Get $get): array => static::kaynakIadeFaturasiSecenekleri(
                                (int) ($get('firma_id') ?: static::aktifFirmaId()),
                                (int) ($get('cari_id') ?: 0),
                                (string) ($get('tur') ?: FaturaTuru::SatisIadesi->value),
                            ))
                            ->getOptionLabelUsing(fn ($value, Get $get): ?string => static::kaynakIadeFaturasiSecenekleri(
                                (int) ($get('firma_id') ?: static::aktifFirmaId()),
                                (int) ($get('cari_id') ?: 0),
                                (string) ($get('tur') ?: FaturaTuru::SatisIadesi->value),
                            )[(int) $value] ?? null)
                            ->live()
                            ->visible(fn (Get $get): bool => in_array((string) $get('tur'), [FaturaTuru::SatisIadesi->value, FaturaTuru::AlisIadesi->value], true))
                            ->required(fn (Get $get): bool => in_array((string) $get('tur'), [FaturaTuru::SatisIadesi->value, FaturaTuru::AlisIadesi->value], true))
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $set('kalemler', $state
                                    ? static::kaynakIadeFaturasiKalemleriniFormata((int) $state, (int) ($get('firma_id') ?: static::aktifFirmaId()), (string) ($get('tur') ?: FaturaTuru::SatisIadesi->value))
                                    : []);
                            }),
                        static::projeAlani(),
                        static::cariEBelgeUyariAlani(),
                        static::cariAdresAlani(),
                    ])
                    ->disabled(fn (Get $get, string $operation, HasForms $livewire): bool => static::formOnayliMi($get, $operation, $livewire))
                    ->columns(2)
                    ->columnSpan(1)
                    ->columnStart(['default' => 1, 'md' => 1]),

                Section::make('Fatura kimligi')
                    ->compact()
                    ->extraAttributes(['class' => 'fatura-kimligi-section'])
                    ->schema([
                        Select::make('firma_id')
                            ->relationship('firma', 'ad')
                            ->inlineLabel()
                            ->searchable()
                            ->visible(static::yoneticiMi()),
                        Select::make('tur')
                            ->inlineLabel()
                            ->options(fn (): array => static::faturaTuruSecenekleri())
                            ->required()
                            ->live()
                            ->disabled(fn (): bool => static::turAlaniKilitliMi())
                            ->dehydrated(),
                        Select::make('durum')
                            ->inlineLabel()
                            ->options(fn (): array => static::faturaDurumuSecenekleri())
                            ->default(FaturaDurumu::Taslak->value)
                            ->required(),
                        DateTimePicker::make('tarih')
                            ->inlineLabel()
                            ->seconds(false)
                            ->displayFormat('d.m.Y H:i')
                            ->required(),
                        DatePicker::make('vade_tarihi')
                            ->native()
                            ->hintActions(static::vadeHizliAksiyonlari())
                            ->suffixAction(
                                Action::make('vade_tarihi_temizle')
                                    ->label('Temizle')
                                    ->icon('heroicon-m-x-mark')
                                    ->color('danger')
                                    ->action(function (Set $set): void {
                                        $set('vade_tarihi', null);
                                    }),
                            ),
                        TextInput::make('fatura_no')->inlineLabel()->maxLength(64),
                        TextInput::make('belge_no')->inlineLabel()->maxLength(64),
                        TextInput::make('irsaliye_no')->inlineLabel()->maxLength(64),
                        Select::make('e_belge_tipi')->options([
                            'e_fatura' => 'E-Fatura',
                            'e_arsiv' => 'E-Arşiv',
                            'kagit' => 'Kağıt Fatura',
                        ])->inlineLabel()->default('e_fatura'),
                        Select::make('para_birimi')
                            ->label('Para birimi')
                            ->inlineLabel()
                            ->options(fn (Get $get): array => static::paraBirimiSecenekleri((int) ($get('firma_id') ?: static::aktifFirmaId())))
                            ->default('TRY')
                            ->searchable(),
                        TextInput::make('doviz_kuru')->inlineLabel()->numeric()->default(1),
                    ])
                    ->disabled(fn (Get $get, string $operation, HasForms $livewire): bool => static::formOnayliMi($get, $operation, $livewire))
                    ->columns(2)
                    ->columnSpan(1)
                    ->columnStart(['default' => 1, 'md' => 2]),

                ...($kalemDetaylariGoster ? [
                    Section::make()
                        ->schema([
                            static::buildKalemlerRepeater(false),
                        ])
                    // İlişki kaydının create operasyonunda Repeater seviyesinde
                    // açık tutulması, yukarıdaki ortak repeater ayarıyla
                    // sağlanır. Düzenleme/görüntüleme akışlarında kapalıdır.
                        ->disabled(fn (Get $get, string $operation, HasForms $livewire): bool => static::formOnayliMi($get, $operation, $livewire))
                        ->columnSpanFull(),
                ] : []),

                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make('Aciklamalar')
                            ->schema([
                                Textarea::make('aciklama'),
                                Textarea::make('notlar'),
                            ])
                            ->disabled(fn (Get $get, string $operation, HasForms $livewire): bool => static::formOnayliMi($get, $operation, $livewire))
                            ->columns(1)
                            ->columnSpan(1),

                        Section::make('Tutar Özeti')
                            ->compact()
                            ->extraAttributes(['class' => 'fatura-tutar-ozeti-section'])
                            ->schema(static::tutarOzetiAlanlari())
                            ->columns(1)
                            ->columnSpan(1),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function cariEBelgeUyariAlani(): Placeholder
    {
        return Placeholder::make('cari_e_belge_uyarilari')
            ->label('')
            ->dehydrated(false)
            ->visible(fn (Get $get): bool => static::cariEBelgeUyarilari((int) ($get('cari_id') ?? 0)) !== [])
            ->content(fn (Get $get): HtmlString => static::cariEBelgeUyariHtml((int) ($get('cari_id') ?? 0)))
            ->columnSpanFull();
    }

    private static function cariAdresAlani(): Placeholder
    {
        return Placeholder::make('cari_adresi')
            ->label('Adres')
            ->dehydrated(false)
            ->visible(fn (Get $get): bool => static::cariAdresKaydi(
                (int) ($get('cari_id') ?? 0),
                (int) ($get('firma_id') ?: static::aktifFirmaId()),
            ) !== null)
            ->content(fn (Get $get): HtmlString => static::cariAdresHtml(
                (int) ($get('cari_id') ?? 0),
                (int) ($get('firma_id') ?: static::aktifFirmaId()),
            ))
            ->columnSpanFull()
            ->extraAttributes(['class' => 'fatura-cari-adres']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function cariAdresKaydi(int $cariId, int $firmaId): ?array
    {
        if ($cariId < 1 || $firmaId < 1) {
            return null;
        }

        $cacheKey = $firmaId.'|'.$cariId;
        if (array_key_exists($cacheKey, static::$cariAdresCache)) {
            return static::$cariAdresCache[$cacheKey];
        }

        $cari = Cari::query()
            ->where('firma_id', $firmaId)
            ->whereKey($cariId)
            ->first(['id', 'adres', 'il', 'ilce', 'ulke', 'posta_kodu', 'vergi_dairesi', 'vergi_no', 'tc_no']);

        if (! $cari) {
            return static::$cariAdresCache[$cacheKey] = null;
        }

        $kayit = [
            'adres' => trim((string) ($cari->adres ?? '')),
            'il' => trim((string) ($cari->il ?? '')),
            'ilce' => trim((string) ($cari->ilce ?? '')),
            'ulke' => trim((string) ($cari->ulke ?? '')),
            'posta_kodu' => trim((string) ($cari->posta_kodu ?? '')),
            'vergi_dairesi' => trim((string) ($cari->vergi_dairesi ?? '')),
            'vergi_no' => trim((string) ($cari->vergi_no ?? '')),
            'tc_no' => trim((string) ($cari->tc_no ?? '')),
        ];

        return static::$cariAdresCache[$cacheKey] = array_filter(
            $kayit,
            static fn (string $deger): bool => $deger !== '',
        ) === [] ? null : $kayit;
    }

    private static function cariAdresHtml(int $cariId, int $firmaId): HtmlString
    {
        $kayit = static::cariAdresKaydi($cariId, $firmaId);
        if ($kayit === null) {
            return new HtmlString('');
        }

        $yer = collect([
            $kayit['ilce'] ?? null,
            $kayit['il'] ?? null,
            $kayit['posta_kodu'] ?? null,
            $kayit['ulke'] ?? null,
        ])->filter()->map(fn (string $deger): string => e($deger))->implode(' · ');

        $vergi = collect([
            filled($kayit['vergi_dairesi'] ?? null) ? 'Vergi Dairesi: '.(string) $kayit['vergi_dairesi'] : null,
            filled($kayit['vergi_no'] ?? null) ? 'Vergi No: '.(string) $kayit['vergi_no'] : null,
            filled($kayit['tc_no'] ?? null) ? 'T.C. Kimlik No: '.(string) $kayit['tc_no'] : null,
        ])->filter()->map(fn (string $deger): string => e($deger))->implode(' · ');

        $adres = filled($kayit['adres'] ?? null)
            ? nl2br(e((string) $kayit['adres']))
            : '<span class="text-gray-500">Açık adres belirtilmemiş.</span>';

        return new HtmlString(
            '<div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-200">'
            .'<div class="font-medium">Cari adresi</div>'
            .'<div class="mt-1">'.$adres.'</div>'
            .($yer !== '' ? '<div class="mt-1 text-xs text-gray-500 dark:text-gray-400">'.$yer.'</div>' : '')
            .($vergi !== '' ? '<div class="mt-2 border-t border-gray-200 pt-2 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300"><span class="font-medium">Vergi bilgileri:</span> '.$vergi.'</div>' : '')
            .'</div>'
        );
    }

    /**
     * @return array<int, string>
     */
    private static function cariEBelgeUyarilari(int $cariId): array
    {
        if ($cariId < 1) {
            return [];
        }

        $cacheKey = static::baglamliSecenekCacheKey('cari_e_belge_uyarilari', $cariId);
        if (array_key_exists($cacheKey, static::$cariEBelgeUyariCache)) {
            return static::$cariEBelgeUyariCache[$cacheKey];
        }

        $cari = Cari::query()
            ->whereKey($cariId)
            ->first(['id', 'ad', 'vergi_dairesi', 'vergi_no', 'tc_no', 'email', 'adres', 'il', 'ilce']);

        return static::$cariEBelgeUyariCache[$cacheKey] = app(EBelgeHazirlikKontrolServisi::class)->cariUyarilari($cari);
    }

    private static function cariEBelgeUyariHtml(int $cariId): HtmlString
    {
        $uyarilar = static::cariEBelgeUyarilari($cariId);
        if ($uyarilar === []) {
            return new HtmlString('');
        }

        $liste = collect($uyarilar)
            ->map(fn (string $uyari): string => '<li>'.e($uyari).'</li>')
            ->implode('');

        return new HtmlString('<div class="rounded-md border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:border-danger-800 dark:bg-danger-950/40 dark:text-danger-300"><div class="font-medium">Cari e-belge uygunluğu için önerilen düzeltmeler:</div><ul class="mt-1 list-disc space-y-1 ps-5">'.$liste.'</ul></div>');
    }

    /**
     * @return array<string, string>
     */
    private static function faturaTuruSecenekleri(): array
    {
        return self::$faturaTuruSecenekleri ??= collect(FaturaTuru::uiNihaiTurler())
            ->mapWithKeys(fn (FaturaTuru $c): array => [$c->value => $c->etiket()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function faturaDurumuSecenekleri(): array
    {
        return self::$faturaDurumuSecenekleri ??= collect(FaturaDurumu::cases())
            ->mapWithKeys(fn (FaturaDurumu $c): array => [$c->value => match ($c) {
                FaturaDurumu::Taslak => 'Taslak',
                FaturaDurumu::Onayli => 'Onaylı',
                FaturaDurumu::Beklemede => 'Beklemede',
                FaturaDurumu::Iptal => 'İptal',
                FaturaDurumu::Iade => 'İade',
            }])
            ->all();
    }

    /**
     * @return array<int, Action>
     */
    private static function vadeHizliAksiyonlari(): array
    {
        $vadeler = [
            ['anahtar' => 'vade_30_gun', 'etiket' => '30 Gün', 'gun' => 30, 'ay' => 0],
            ['anahtar' => 'vade_45_gun', 'etiket' => '45 Gün', 'gun' => 45, 'ay' => 0],
            ['anahtar' => 'vade_2_ay', 'etiket' => '2 Ay', 'gun' => 0, 'ay' => 2],
            ['anahtar' => 'vade_3_ay', 'etiket' => '3 Ay', 'gun' => 0, 'ay' => 3],
            ['anahtar' => 'vade_6_ay', 'etiket' => '6 Ay', 'gun' => 0, 'ay' => 6],
        ];

        return array_map(
            fn (array $vade): Action => Action::make($vade['anahtar'])
                ->label($vade['etiket'])
                ->color('gray')
                ->action(fn (Get $get, Set $set): string => $set(
                    'vade_tarihi',
                    static::vadeTarihiHesapla($get('tarih'), (int) $vade['gun'], (int) $vade['ay']),
                )),
            $vadeler,
        );
    }

    private static function vadeTarihiHesapla(mixed $tarih, int $gun, int $ay): string
    {
        $baslangic = $tarih instanceof Carbon
            ? $tarih->copy()
            : Carbon::parse($tarih ?: today());

        return ($ay > 0 ? $baslangic->addMonthsNoOverflow($ay) : $baslangic->addDays($gun))
            ->toDateString();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->select([
                    'id',
                    'firma_id',
                    'cari_id',
                    'fatura_no',
                    'tur',
                    'durum',
                    'tarih',
                    'genel_toplam',
                    'acik_tutar',
                ])
                ->with(['cari:id,ad,kod'])
                ->latest('id'))
            ->columns([
                TextColumn::make('fatura_no')->searchable(),
                TextColumn::make('tur')->badge()->formatStateUsing(fn ($state): string => $state instanceof FaturaTuru
                    ? $state->etiket()
                    : (FaturaTuru::tryFrom((string) $state)?->etiket() ?? (string) $state)),
                TextColumn::make('durum')->badge(),
                TextColumn::make('cari.ad')->label('Cari')->toggleable(),
                TextColumn::make('tarih')
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('genel_toplam')->money('TRY'),
                TextColumn::make('acik_tutar')->money('TRY'),
            ])
            ->filters([
                SelectFilter::make('tur')->options(fn (): array => static::faturaTuruSecenekleri()),
                SelectFilter::make('durum')->options(fn (): array => static::faturaDurumuSecenekleri()),
            ])
            ->actions([
                TableAction::make('kopyala')
                    ->label('Kopyala')
                    ->icon('heroicon-o-document-duplicate')
                    // İptal faturaları tablosunda kullanıcıya görünür bir
                    // kopyalama aksiyonu sun; gerçek yetki kontrolü action
                    // callback'inde ayrıca uygulanır.
                    ->visible(fn (Fatura $record): bool => $record->durum === FaturaDurumu::Iptal)
                    ->iconButton()
                    ->color('warning')
                    ->tooltip('İptal faturayı taslak olarak kopyala')
                    ->action(function (Fatura $record): void {
                        static::faturaYetkisiniDogrula('guncelle');
                        $kopya = app(FaturaKopyalamaServisi::class)->kopyala($record);
                        Notification::make()->success()->title('Fatura taslak olarak kopyalandı')->send();
                    }),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaturalar::route('/'),
            'create' => CreateFatura::route('/create'),
            'createGelen' => CreateGelenFatura::route('/create/gelen-fatura'),
            'createGiden' => CreateGidenFatura::route('/create/giden-fatura'),
            'createBekleyen' => CreateBekleyenFatura::route('/create/bekleyen-fatura'),
            'createIptal' => CreateIptalFatura::route('/create/iptal-fatura'),
            'createGidenIade' => CreateGidenIadeFaturasi::route('/create/giden-iade-faturasi'),
            'createGelenIade' => CreateGelenIadeFaturasi::route('/create/gelen-iade-faturasi'),
            'createProforma' => CreateProformaFatura::route('/create/proforma-fatura'),
            'createGider' => CreateGiderFaturasi::route('/create/gider-faturasi'),
            // Görüntüleme ekranı, edit formunun salt-okunur varyantını kullanır.
            'view' => EditFatura::route('/{record}'),
            'edit' => EditFatura::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    private static function hizliDuzenlemeModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.edit') && ! static::kalemDetaylariGoster();
    }

    private static function formOnayliMi(Get $get, string $operation = '', ?HasForms $livewire = null): bool
    {
        // Yeni fatura oluştururken durum "onaylı" seçilebilir. Bu aşamada
        // alanları kilitlemek, Filament'in disabled alanları dehydration'dan
        // çıkarması nedeniyle kalemlerin create isteğine hiç gitmemesine yol
        // açar. Operation bilgisi Livewire yeniden çizimlerinde de sabit
        // kaldığı için URL/request path kontrolünden daha güvenilirdir.
        if ($operation === 'create' || static::faturaOlusturmaRotasiMi($livewire)) {
            return false;
        }

        if ((bool) ($livewire?->goruntule ?? false)) {
            return true;
        }

        // Düzenleme sırasında kullanıcı durum alanında "Onaylı" seçtiğinde
        // formu anında disabled yapmak, Filament'in bu alanı dehydration'dan
        // çıkarmasına ve eski taslak değerinin kaydedilmesine neden olur.
        // Kilit yalnızca veritabanındaki mevcut kayıt zaten onaylıysa uygulanır.
        $kayitDurumu = $livewire?->record?->durum;
        if ($kayitDurumu instanceof FaturaDurumu) {
            return $kayitDurumu === FaturaDurumu::Onayli;
        }

        $durum = $get('durum');

        return ($durum instanceof FaturaDurumu ? $durum->value : (string) $durum) === FaturaDurumu::Onayli->value;
    }

    private static function faturaOlusturmaRotasiMi(?HasForms $livewire = null): bool
    {
        return $livewire instanceof CreateFatura
            || str_starts_with(trim((string) request()->path(), '/'), 'admin/muhasebe/fatura-kaynagis/create');
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        $routeName = (string) (request()->route()?->getName() ?? '');
        $editRotasi = str_ends_with($routeName, '.edit');
        $kalemleriYukle = ! $editRotasi || static::kalemDetaylariGoster();

        if ($editRotasi && ! static::kalemDetaylariGoster()) {
            return static::getEloquentQuery()
                ->whereKey($key)
                ->select([
                    'id',
                    'firma_id',
                    'durum',
                ])
                ->first();
        }

        $iliskiYukleri = $editRotasi && ! static::kalemDetaylariGoster()
            ? []
            : [
                'firma:id,ad',
                'cari:id,kod,ad',
                'bagliFatura:id,fatura_no,tur',
            ];

        if ($kalemleriYukle) {
            $iliskiYukleri['kalemler'] = fn ($query) => $query
                ->select([
                    'id',
                    'firma_id',
                    'fatura_id',
                    'satir_no',
                    'kalem_tipi',
                    'stok_id',
                    'aciklama',
                    'birim',
                    'miktar',
                    'birim_fiyat',
                    'indirim_orani',
                    'indirim_tutari',
                    'kdv_orani',
                    'kdv_tutari',
                    'satir_indirim_tutari',
                    'net_tutar',
                    'satir_toplami',
                    'satir_genel_toplam',
                    'para_birimi',
                    'toplam',
                ])
                ->with('stokKarti:id,kod,ad,birim,kdv_orani,satis_fiyati')
                ->orderBy('satir_no')
                ->orderBy('id');
        }

        $record = static::getEloquentQuery()
            ->whereKey($key)
            ->select([
                'id',
                'firma_id',
                'cari_id',
                'belge_no',
                'irsaliye_no',
                'seri',
                'sira_no',
                'tur',
                'durum',
                'fatura_no',
                'odeme_durumu',
                'tarih',
                'vade_tarihi',
                'doviz_kuru',
                'ara_toplam',
                'toplam_indirim',
                'kdv_toplam',
                'tevkifat_orani',
                'genel_toplam',
                'odenecek_tutar',
                'odendi_tutari',
                'acik_tutar',
                'genel_indirim_tutari',
                'kdv_dahil_fiyatlandirma_mi',
                'bagli_fatura_id',
                'para_birimi',
                'aciklama',
                'notlar',
                'iptal_nedeni',
                'iptal_edildi_at',
                'e_belge_tipi',
                'created_at',
            ])
            ->with($iliskiYukleri)
            ->first();

        if ($record) {
            if ($record->relationLoaded('cari') && $record->cari) {
                $cariId = (int) $record->cari->id;
                static::$secenekCache[static::baglamliSecenekCacheKey('cari_etiket', $cariId)][$cariId] = static::cariEtiketiHazirla($record->cari);
            }

            if ($record->relationLoaded('kalemler')) {
                foreach ($record->kalemler as $kalem) {
                    if ($kalem->stokKarti) {
                        static::$stokKaydiCache[static::baglamliSecenekCacheKey('stok_kaydi', (int) $kalem->stokKarti->id)] = $kalem->stokKarti;
                    }
                }
            }
        }

        return $record;
    }

    /**
     * @return array<int, string>
     */
    public static function cariSecenekleri(): array
    {
        $cacheKey = static::baglamliSecenekCacheKey('cari');

        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        return static::$secenekCache[$cacheKey] = Cari::query()
            ->orderBy('ad')
            ->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (StokKarti $stok): array => [(int) $stok->id => static::stokAdEtiketiHazirla($stok)])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function cariAramaSonuclari(string $search): array
    {
        $firmaId = static::aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        return Cari::query()
            ->where('firma_id', $firmaId)
            ->when(trim($search) !== '', function (Builder $query) use ($search): Builder {
                $aranan = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';

                return $query->where(function (Builder $q) use ($aranan): void {
                    $q->where('ad', 'like', $aranan)
                        ->orWhere('kod', 'like', $aranan)
                        ->orWhere('telefon', 'like', $aranan)
                        ->orWhere('gsm', 'like', $aranan);
                });
            })
            ->orderBy('ad')
            ->limit(50)
            ->get(['id', 'ad', 'kod'])
            ->mapWithKeys(fn (Cari $cari): array => [
                (int) $cari->id => static::cariEtiketiHazirla($cari),
            ])
            ->all();
    }

    public static function cariEtiketi(mixed $value): ?string
    {
        $id = (int) $value;
        if ($id < 1) {
            return null;
        }

        $cacheKey = static::baglamliSecenekCacheKey('cari_etiket', $id);
        if (isset(static::$secenekCache[$cacheKey][$id])) {
            return static::$secenekCache[$cacheKey][$id];
        }

        $cari = Cari::query()->whereKey($id)->first(['id', 'ad', 'kod']);
        if (! $cari) {
            return null;
        }

        return static::$secenekCache[$cacheKey][$id] = static::cariEtiketiHazirla($cari);
    }

    private static function cariEtiketiHazirla(Cari $cari): string
    {
        return trim(((string) ($cari->kod ?: '')) !== ''
            ? $cari->kod.' - '.$cari->ad
            : (string) $cari->ad);
    }

    /**
     * @return array<string, string>
     */
    public static function stokKodSecenekleri(): array
    {
        $cacheKey = static::baglamliSecenekCacheKey('stok_kod');

        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        return static::$secenekCache[$cacheKey] = StokKarti::query()
            ->orderBy('kod')
            ->get(['id', 'kod'])
            ->mapWithKeys(fn (StokKarti $stok) => [
                (string) $stok->id => ((string) ($stok->kod ?? '')) !== '' ? (string) $stok->kod : ('STK-'.$stok->id),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function stokIlkSecenekleri(int $firmaId): array
    {
        if ($firmaId < 1) {
            return [];
        }

        $cacheKey = static::baglamliSecenekCacheKey('stok_ilk', $firmaId);
        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        return static::$secenekCache[$cacheKey] = Cache::remember(
            'muhasebe:fatura:stok-ilk-secenekleri:v1:'.$firmaId,
            now()->addSeconds(30),
            fn (): array => StokKarti::query()
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->limit(30)
                ->get(['id', 'kod', 'ad'])
                ->mapWithKeys(fn (StokKarti $stok): array => [
                    (string) $stok->id => static::stokAdEtiketiHazirla($stok),
                ])
                ->all(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function stokKodAramaSonuclari(string $search, int $firmaId = 0): array
    {
        $term = trim($search);
        if ($firmaId < 1 || ($term !== '' && Str::length($term) < 2)) {
            return [];
        }

        $query = StokKarti::query()
            ->where('firma_id', $firmaId);

        if ($term === '') {
            $query->orderBy('kod');
        } else {
            $escapedTerm = str_replace(['%', '_'], ['\\%', '\\_'], $term);
            $prefix = $escapedTerm.'%';
            $contains = '%'.$escapedTerm.'%';

            $query
                ->where(function (Builder $q) use ($contains): void {
                    $q->where('kod', 'like', $contains)
                        ->orWhere('ad', 'like', $contains)
                        ->orWhere('barkod', 'like', $contains);
                })
                ->orderByRaw(
                    'CASE WHEN kod = ? THEN 0 WHEN barkod = ? THEN 1 WHEN kod LIKE ? THEN 2 WHEN barkod LIKE ? THEN 3 WHEN ad LIKE ? THEN 4 ELSE 5 END',
                    [$term, $term, $prefix, $prefix, $prefix],
                )
                ->orderBy('kod');
        }

        return $query
            ->limit(30)
            ->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (StokKarti $stok): array => [
                (string) $stok->id => static::stokKodEtiketiHazirla($stok),
            ])
            ->all();
    }

    public static function stokKodEtiketi(mixed $value, int $firmaId = 0): ?string
    {
        $stok = static::stokKaydi((int) $value, $firmaId);

        return $stok ? static::stokKodEtiketiHazirla($stok) : null;
    }

    private static function stokKodEtiketiHazirla(StokKarti $stok): string
    {
        return ((string) ($stok->kod ?? '')) !== '' ? (string) $stok->kod : ('STK-'.$stok->id);
    }

    /**
     * @return array<int, string>
     */
    public static function stokAdSecenekleri(): array
    {
        $cacheKey = static::baglamliSecenekCacheKey('stok_ad');

        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        return static::$secenekCache[$cacheKey] = StokKarti::query()
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function stokAdAramaSonuclari(string $search, int $firmaId = 0): array
    {
        $term = trim($search);
        if ($firmaId < 1 || ($term !== '' && Str::length($term) < 2)) {
            return [];
        }

        $query = StokKarti::query()
            ->where('firma_id', $firmaId);

        if ($term === '') {
            $query->orderBy('ad');
        } else {
            $escapedTerm = str_replace(['%', '_'], ['\\%', '\\_'], $term);
            $prefix = $escapedTerm.'%';
            $contains = '%'.$escapedTerm.'%';

            $query
                ->where(function (Builder $q) use ($contains): void {
                    $q->where('ad', 'like', $contains)
                        ->orWhere('kod', 'like', $contains)
                        ->orWhere('barkod', 'like', $contains);
                })
                ->orderByRaw(
                    'CASE WHEN kod = ? THEN 0 WHEN barkod = ? THEN 1 WHEN kod LIKE ? THEN 2 WHEN barkod LIKE ? THEN 3 WHEN ad LIKE ? THEN 4 ELSE 5 END',
                    [$term, $term, $prefix, $prefix, $prefix],
                )
                ->orderBy('ad');
        }

        return $query
            ->limit(30)
            ->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (StokKarti $stok): array => [
                (string) $stok->id => static::stokAdEtiketiHazirla($stok),
            ])
            ->all();
    }

    public static function stokAdEtiketi(mixed $value, int $firmaId = 0): ?string
    {
        $stok = static::stokKaydi((int) $value, $firmaId);

        return $stok ? static::stokAdEtiketiHazirla($stok) : null;
    }

    private static function stokAdEtiketiHazirla(StokKarti $stok): string
    {
        $kod = trim((string) ($stok->kod ?? ''));
        $ad = trim((string) ($stok->ad ?? ''));

        return $kod !== '' ? "{$kod} - {$ad}" : $ad;
    }

    /**
     * @return array<string, string>
     */
    public static function varsayilanBirimKodu(int $firmaId): string
    {
        if ($firmaId < 1) {
            return 'AD';
        }

        $kod = Birim::query()
            ->gorunurFirmaIle($firmaId)
            ->where('aktif_mi', true)
            ->whereIn('kod', ['AD', 'ADET'])
            ->orderByRaw("CASE WHEN kod = 'AD' THEN 0 ELSE 1 END")
            ->value('kod');

        return (string) ($kod ?: 'AD');
    }

    public static function birimYedekSecenekUyarisi(int $firmaId): ?HtmlString
    {
        if ($firmaId > 0 && Birim::query()
            ->gorunurFirmaIle($firmaId)
            ->where('aktif_mi', true)
            ->exists()) {
            return null;
        }

        return new HtmlString('<span class="text-warning-600 dark:text-warning-400">Tanımlı aktif birim bulunamadı. Faturayı kaydetmeden önce Tanımlar &gt; Birimler bölümünden birim ekleyin.</span>');
    }

    /**
     * @return array<string, string>
     */
    public static function birimSecenekleri(int $firmaId): array
    {
        if ($firmaId < 1) {
            return [];
        }

        $cacheKey = static::baglamliSecenekCacheKey('birim', $firmaId);

        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        $liste = Cache::remember(
            'muhasebe:fatura:birim-secenekleri:v1:'.$firmaId,
            now()->addSeconds(60),
            fn (): array => Birim::query()
                ->gorunurFirmaIle($firmaId)
                ->where('aktif_mi', true)
                ->orderBy('kod')
                ->get(['kod', 'ad'])
                ->mapWithKeys(fn (Birim $b) => [
                    $b->kod => $b->ad ?: ((string) $b->kod === 'AD' ? 'Adet' : (string) $b->kod),
                ])
                ->all()
        );

        if (! array_key_exists('AD', $liste) && ! array_key_exists('ADET', $liste)) {
            $liste = ['AD' => 'Adet'] + $liste;
        }

        return static::$secenekCache[$cacheKey] = $liste;
    }

    /**
     * @return array<string, string>
     */
    public static function birimIlkSecenekleri(int $firmaId): array
    {
        if ($firmaId < 1) {
            return [];
        }

        $cacheKey = static::baglamliSecenekCacheKey('birim_ilk', $firmaId);
        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        $liste = Cache::remember(
            'muhasebe:fatura:birim-ilk-secenekleri:v1:'.$firmaId,
            now()->addSeconds(60),
            fn (): array => Birim::query()
                ->gorunurFirmaIle($firmaId)
                ->where('aktif_mi', true)
                ->orderBy('kod')
                ->limit(30)
                ->get(['kod', 'ad'])
                ->mapWithKeys(fn (Birim $birim): array => [
                    (string) $birim->kod => $birim->ad ?: ((string) $birim->kod === 'AD' ? 'Adet' : (string) $birim->kod),
                ])
                ->all(),
        );

        if (! array_key_exists('AD', $liste) && ! array_key_exists('ADET', $liste)) {
            $liste = ['AD' => 'Adet'] + $liste;
        }

        return static::$secenekCache[$cacheKey] = $liste;
    }

    /**
     * @return array<string, string>
     */
    public static function birimAramaSonuclari(string $search, int $firmaId): array
    {
        $term = trim($search);
        if ($firmaId < 1 || ($term !== '' && Str::length($term) < 2)) {
            return [];
        }

        $query = Birim::query()
            ->gorunurFirmaIle($firmaId)
            ->where('aktif_mi', true);

        if ($term === '') {
            $query->orderBy('kod');
        } else {
            $escapedTerm = str_replace(['%', '_'], ['\\%', '\\_'], $term);
            $prefix = $escapedTerm.'%';
            $contains = '%'.$escapedTerm.'%';

            $query
                ->where(function (Builder $q) use ($contains): void {
                    $q->where('kod', 'like', $contains)
                        ->orWhere('ad', 'like', $contains);
                })
                ->orderByRaw(
                    'CASE WHEN kod = ? THEN 0 WHEN kod LIKE ? THEN 1 WHEN ad LIKE ? THEN 2 ELSE 3 END',
                    [$term, $prefix, $prefix],
                )
                ->orderBy('kod');
        }

        return $query
            ->limit(30)
            ->get(['kod', 'ad'])
            ->mapWithKeys(fn (Birim $birim): array => [
                (string) $birim->kod => $birim->ad ?: ((string) $birim->kod === 'AD' ? 'Adet' : (string) $birim->kod),
            ])
            ->all();
    }

    public static function birimEtiketi(mixed $value, int $firmaId): ?string
    {
        $kod = Str::upper(trim((string) $value));
        if ($kod === '') {
            return null;
        }

        if ($firmaId < 1) {
            return $kod === 'AD' ? 'AD' : null;
        }

        $cacheKey = $firmaId.'|'.$kod;
        if (array_key_exists($cacheKey, static::$birimEtiketCache)) {
            return static::$birimEtiketCache[$cacheKey];
        }

        $etiket = Cache::remember(
            'muhasebe:fatura:birim-etiketi:v1:'.$firmaId.':'.$kod,
            now()->addMinutes(10),
            function () use ($firmaId, $kod): ?string {
                $birim = Birim::query()
                    ->gorunurFirmaIle($firmaId)
                    ->where('kod', $kod)
                    ->first(['kod', 'ad']);

                return $birim
                    ? ((string) $birim->ad ?: ($kod === 'AD' ? 'Adet' : $kod))
                    : ($kod === 'AD' ? 'Adet' : null);
            },
        );

        return static::$birimEtiketCache[$cacheKey] = $etiket;
    }

    private static function stokKaydi(int $stokId, int $firmaId = 0): ?StokKarti
    {
        if ($stokId < 1) {
            return null;
        }

        $firmaId = $firmaId > 0 ? $firmaId : static::aktifFirmaId();
        $cacheKey = static::baglamliSecenekCacheKey('stok_kaydi', $stokId).'|'.$firmaId;

        if (! array_key_exists($cacheKey, static::$stokKaydiCache)) {
            static::$stokKaydiCache[$cacheKey] = StokKarti::query()
                ->where('firma_id', $firmaId)
                ->whereKey($stokId)
                ->first(['id', 'kod', 'ad', 'birim', 'kdv_orani', 'satis_fiyati']);
        }

        return static::$stokKaydiCache[$cacheKey];
    }

    private static function baglamliSecenekCacheKey(string $tur, int $ekId = 0): string
    {
        return implode('|', [
            $tur,
            $ekId,
            static::aktifFirmaId(),
            static::authId(),
            static::yoneticiMi() ? 1 : 0,
            app()->runningInConsole() ? 1 : 0,
        ]);
    }

    private static function aktifFirmaId(): int
    {
        return self::$aktifFirmaIdCache ??= (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
    }

    private static function projeAlani(): Select
    {
        return Select::make('isletme_proje_id')
            ->label('İşletme projesi')
            ->placeholder('Projeye bağlama (isteğe bağlı)')
            ->searchable()
            ->options(fn (): array => IsletmeProjesi::query()
                ->secilebilir()
                ->where('firma_id', static::aktifFirmaId())
                ->orderBy('ad')
                ->limit(100)
                ->get(['id', 'kod', 'ad'])
                ->mapWithKeys(fn (IsletmeProjesi $proje): array => [$proje->id => $proje->kod.' — '.$proje->ad])
                ->all())
            ->helperText('Faturayı proje gelir/gider raporlarına dahil eder.');
    }

    private static function authId(): int
    {
        return self::$authIdCache ??= (int) (Auth::id() ?? 0);
    }

    private static function yoneticiMi(): bool
    {
        if (self::$yoneticiMiCache !== null) {
            return self::$yoneticiMiCache;
        }

        $kullanici = Auth::user();

        return self::$yoneticiMiCache = (bool) ($kullanici?->super_admin_mi || $kullanici?->is_admin);
    }

    /**
     * @return array<string, string>
     */
    public static function paraBirimiSecenekleri(int $firmaId): array
    {
        if ($firmaId < 1) {
            return ['TRY' => 'TRY'];
        }

        $cacheKey = 'para_birimi|'.$firmaId;

        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        $secenekler = Cache::remember(
            'muhasebe:fatura:para-birimi-secenekleri:v1:'.$firmaId,
            now()->addSeconds(60),
            fn (): array => ParaBirimi::query()
                ->gorunurFirmaIle($firmaId)
                ->where('aktif_mi', true)
                ->orderBy('kod')
                ->get(['kod', 'ad'])
                ->mapWithKeys(fn (ParaBirimi $pb) => [
                    strtoupper((string) $pb->kod) => strtoupper((string) $pb->kod).' - '.((string) ($pb->ad ?: strtoupper((string) $pb->kod))),
                ])
                ->all()
        );

        if ($secenekler === []) {
            return static::$secenekCache[$cacheKey] = ['TRY' => 'TRY'];
        }

        return static::$secenekCache[$cacheKey] = $secenekler;
    }

    private static function cariParaBirimi(int $cariId, int $firmaId): ?string
    {
        if ($cariId < 1) {
            return null;
        }

        $kod = Cari::query()
            ->whereKey($cariId)
            ->when($firmaId > 0, fn (Builder $query): Builder => $query->where('firma_id', $firmaId))
            ->value('para_birimi');

        return $kod ? strtoupper(trim((string) $kod)) : null;
    }

    /**
     * @return array<string, string>
     */
    public static function vergiOraniSecenekleri(int $firmaId): array
    {
        if ($firmaId < 1) {
            return [
                StokKartiKaynagi::vergiOranFormAnahtari(0.0) => '%0',
                StokKartiKaynagi::vergiOranFormAnahtari(20.0) => '%20',
            ];
        }

        $cacheKey = 'vergi_orani|'.$firmaId;

        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        $secenekler = Cache::remember(
            'muhasebe:fatura:vergi-orani-secenekleri:v2:'.$firmaId,
            now()->addSeconds(60),
            function () use ($firmaId): array {
                $liste = [];
                foreach (VergiOrani::query()
                    ->gorunurFirmaIle($firmaId)
                    ->where('aktif_mi', true)
                    ->orderBy('oran')
                    ->get(['kod', 'ad', 'oran']) as $v) {
                    $oran = (float) $v->oran;
                    $liste[StokKartiKaynagi::vergiOranFormAnahtari($oran)] = (string) ($v->ad ?: '%'.$oran);
                }

                return $liste;
            }
        );

        if ($secenekler === []) {
            $secenekler[StokKartiKaynagi::vergiOranFormAnahtari(0.0)] = '%0';
            $secenekler[StokKartiKaynagi::vergiOranFormAnahtari(20.0)] = '%20';
        }

        return static::$secenekCache[$cacheKey] = $secenekler;
    }

    public static function kalemlerRepeaterAlani(bool $kanakkuTekSatir = false, bool $relationship = true): Repeater
    {
        return static::buildKalemlerRepeater($kanakkuTekSatir, $relationship);
    }

    private static function kalemPartiTakibiAktifMi(Get $get): bool
    {
        // Parti/Lot takibi uygulamadan kaldırıldı. Eski kartlarda kalan
        // değerler yeni fatura formunda fiziksel parça sorgusu başlatmamalı.
        return false;
    }

    private static function kalemSeriTakibiAktifMi(Get $get): bool
    {
        $stokId = (int) ($get('stok_id') ?? 0);

        return $stokId > 0 && (string) StokKarti::query()->whereKey($stokId)->value('stok_takip_tipi')
            === StokKarti::STOK_TAKIP_TIPI_SERI;
    }

    /** @param array<string, mixed> $kalem */
    private static function depoSecimiGerekliMi(array $kalem): bool
    {
        $stokId = (int) ($kalem['stok_id'] ?? 0);
        $firmaId = (int) ($kalem['firma_id'] ?? static::aktifFirmaId());

        return $stokId > 0
            && static::depoAlanGosterilmeli($firmaId, $stokId)
            && blank($kalem['depo_id'] ?? null);
    }

    private static function depoAyrintisiActionDikkatGerekliMi(Action $action): bool
    {
        $item = $action->getArguments()['item'] ?? null;
        $repeater = $action->getComponent()->getParentRepeater();

        return is_string($item)
            && $repeater !== null
            && static::depoAyrintisiDikkatGerekliMi($repeater->getRawItemState($item));
    }

    /** @param array<string, mixed> $kalem */
    private static function depoAyrintisiDikkatGerekliMi(array $kalem): bool
    {
        $stokId = (int) ($kalem['stok_id'] ?? 0);
        $seriGerekli = $stokId > 0
            && (string) StokKarti::withoutGlobalScopes()->whereKey($stokId)->value('stok_takip_tipi')
                === StokKarti::STOK_TAKIP_TIPI_SERI
            && count(array_filter((array) ($kalem['seri_nolari'] ?? []), fn ($seri): bool => filled(trim((string) $seri)))) === 0;

        return static::depoSecimiGerekliMi($kalem) || $seriGerekli;
    }

    private static function kalemFizikselParcaTakibiAktifMi(Get $get): bool
    {
        $stokId = static::formStokId($get);
        if ($stokId < 1 || ! static::kalemPartiTakibiAktifMi($get)) {
            return false;
        }

        return StokParcasi::withoutGlobalScopes()
            ->where('firma_id', static::formFirmaId($get))
            ->where('stok_id', $stokId)
            ->where('parca_mi', true)
            ->exists();
    }

    private static function depoModuluAktifMi(int $firmaId): bool
    {
        return $firmaId > 0 && (bool) app(FirmaAyarDeposu::class)->oku($firmaId, 'stok_depo_modulu_aktif_mi', false);
    }

    private static function formStateInt(Get $get, array $yollar): int
    {
        foreach ($yollar as $yol) {
            $deger = $get($yol);
            if ($deger !== null && $deger !== '' && (int) $deger > 0) {
                return (int) $deger;
            }
        }

        return 0;
    }

    private static function formFirmaId(Get $get): int
    {
        return static::formStateInt($get, ['firma_id', '../firma_id', '../../firma_id', '../../../firma_id', '../../../../firma_id', '../../../../../firma_id']) ?: static::aktifFirmaId();
    }

    private static function formStokId(Get $get): int
    {
        return static::formStateInt($get, ['stok_id', '../stok_id', '../../stok_id', '../../../stok_id', '../../../../stok_id', '../../../../../stok_id']);
    }

    public static function depoAlanGosterilmeli(int $firmaId, int $stokId): bool
    {
        if ($firmaId < 1 || $stokId < 1) {
            return false;
        }

        $stok = StokKarti::withoutGlobalScopes()->where('firma_id', $firmaId)->find($stokId);
        if (! $stok) {
            return false;
        }

        // Ölçülü stokta depo, ölçü bakiyesinin ayrılmaz parçasıdır. Depo
        // modülü ayarı kapalı olsa bile tek depo bulunan firmalarda bu alanı
        // gizlemek ölçü dağılımını ve fatura onayını imkânsız bırakır.
        if ($stok->olculu_takip_turu?->olculuMu() === true) {
            return Depo::tenantScopeOlmadan(fn (): bool => Depo::query()
                ->where('firma_id', $firmaId)
                ->where('aktif_mi', true)
                ->exists());
        }

        if (static::depoModuluAktifMi($firmaId)) {
            // Hedef depo, stokta henüz bakiye yokken de seçilebilir olmalıdır.
            // Bakiye sayısını görünürlük koşulu yapmak gelen faturada depo
            // seçimini tamamen saklıyordu.
            return count(static::depoSecenekleri($firmaId)) > 1;
        }

        return false;
    }

    public static function olcuDagilimiAlanGosterilmeli(int $stokId): bool
    {
        $stok = $stokId > 0 ? StokKarti::withoutGlobalScopes()->find($stokId) : null;

        return $stok?->olculu_takip_turu?->olculuMu() === true;
    }

    /** @return array<int|string, string> */
    private static function depoSecenekleri(int $firmaId): array
    {
        if (! static::depoModuluAktifMi($firmaId)) {
            return [];
        }

        return Depo::tenantScopeOlmadan(fn () => Depo::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->orderByDesc('varsayilan_mi')
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->map(fn ($ad): string => (string) $ad)
            ->all());
    }

    /** @return array<int|string, string> */
    public static function depoSecenekleriForForm(int $firmaId, int $stokId): array
    {
        if ($firmaId < 1 || $stokId < 1) {
            return [];
        }

        if (static::depoModuluAktifMi($firmaId)) {
            $stok = StokKarti::withoutGlobalScopes()->where('firma_id', $firmaId)->find($stokId);
            $olculu = $stok?->olculu_takip_turu?->olculuMu() === true;
            $depoIdleri = $olculu
                ? StokOlcuBakiyesi::withoutGlobalScopes()
                    ->where('firma_id', $firmaId)->where('stok_id', $stokId)
                    ->where('ana_miktar', '>', 0)->whereNotNull('depo_id')
                    ->distinct()->pluck('depo_id')->map(fn ($id): int => (int) $id)->all()
                : StokDepoBakiyesi::withoutGlobalScopes()
                    ->where('firma_id', $firmaId)->where('stok_id', $stokId)
                    ->where('miktar', '>', 0)->whereNotNull('depo_id')
                    ->distinct()->pluck('depo_id')->map(fn ($id): int => (int) $id)->all();

            // Gelen fatura veya henüz bakiyesi oluşmamış stokta hedef depo
            // seçilebilmesi için aktif depoların tamamı gösterilir.
            $depolar = static::depoSecenekleri($firmaId);
            if ($depoIdleri === []) {
                return $depolar;
            }

            return array_intersect_key($depolar, array_flip($depoIdleri));
        }

        $stok = StokKarti::withoutGlobalScopes()->where('firma_id', $firmaId)->find($stokId);
        if (! $stok?->olculu_takip_turu?->olculuMu() || (int) ($stok->depo_id ?? 0) < 1) {
            return [];
        }

        return Depo::tenantScopeOlmadan(fn () => Depo::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->whereKey((int) $stok->depo_id)
            ->pluck('ad', 'id')
            ->map(fn ($ad): string => (string) $ad)
            ->all());
    }

    private static function varsayilanDepoId(int $firmaId): ?int
    {
        if (! static::depoModuluAktifMi($firmaId)) {
            return null;
        }

        $ayarId = (int) (app(FirmaAyarDeposu::class)->oku($firmaId, 'stok_varsayilan_depo_id', 0) ?? 0);
        if ($ayarId > 0 && Depo::tenantScopeOlmadan(fn () => Depo::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->whereKey($ayarId)
            ->exists())) {
            return $ayarId;
        }

        return Depo::tenantScopeOlmadan(fn () => Depo::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->where('varsayilan_mi', true)
            ->value('id'));
    }

    private static function varsayilanDepoIdForForm(int $firmaId, int $stokId): ?int
    {
        if ($firmaId < 1 || $stokId < 1) {
            return null;
        }

        if (static::depoModuluAktifMi($firmaId)) {
            return static::varsayilanDepoId($firmaId);
        }

        $stok = StokKarti::withoutGlobalScopes()->where('firma_id', $firmaId)->find($stokId);

        return $stok?->olculu_takip_turu?->olculuMu() && (int) ($stok->depo_id ?? 0) > 0
            ? (int) $stok->depo_id
            : null;
    }

    /** @return array<int, string> */
    private static function parcaKoduSecenekleri(Get $get, string $arama = ''): array
    {
        $stokId = static::formStokId($get);
        if ($stokId < 1) {
            return [];
        }

        $sorgu = StokParcasi::query()
            ->where('stok_id', $stokId)
            ->where('kalan_miktar', '>', 0)
            ->with(['olcuBakiyeleri' => fn ($query) => $query->where('ana_miktar', '>', 0)->with('olcu:id,ad,kod')]);
        $depoId = static::formStateInt($get, ['depo_id', '../depo_id', '../../depo_id', '../../../depo_id', '../../../../depo_id']);
        $depoId > 0 ? $sorgu->where('depo_id', $depoId) : $sorgu->whereNull('depo_id');

        if (trim($arama) !== '') {
            $arama = trim($arama);
            $sorgu->where(function (Builder $query) use ($arama): void {
                $query->where('parca_kodu', 'like', '%'.$arama.'%')
                    ->orWhere('parca_kodu', 'like', '%'.$arama.'%')
                    ->orWhere('barkod', 'like', '%'.$arama.'%')
                    ->orWhere('plaka_no', 'like', '%'.$arama.'%')
                    ->orWhere('renk_desen', 'like', '%'.$arama.'%')
                    ->orWhere('kalite_sinifi', 'like', '%'.$arama.'%')
                    ->orWhere('metrekare', 'like', '%'.$arama.'%')
                    ->orWhere('kalan_miktar', 'like', '%'.$arama.'%')
                    ->orWhere('birim_maliyet', 'like', '%'.$arama.'%')
                    ->orWhere('uretim_tarihi', 'like', '%'.$arama.'%')
                    ->orWhere('son_kullanma_tarihi', 'like', '%'.$arama.'%')
                    ->orWhere('created_at', 'like', '%'.$arama.'%')
                    ->orWhereHas('olcuBakiyeleri', function (Builder $bakiye) use ($arama): void {
                        $bakiye->where('ana_miktar', 'like', '%'.$arama.'%')
                            ->orWhere('adet_esdegeri', 'like', '%'.$arama.'%')
                            ->orWhereHas('olcu', fn (Builder $olcu): Builder => $olcu
                                ->where('ad', 'like', '%'.$arama.'%')
                                ->orWhere('kod', 'like', '%'.$arama.'%')
                                ->orWhere('en', 'like', '%'.$arama.'%')
                                ->orWhere('boy', 'like', '%'.$arama.'%')
                                ->orWhere('yukseklik', 'like', '%'.$arama.'%'));
                    });
            });
        }

        $siralama = static::formStateString($get, [
            'parti_siralama', '../parti_siralama', '../../parti_siralama', '../../../parti_siralama', '../../../../parti_siralama',
        ], 'son_kullanma');
        match ($siralama) {
            'tarih_yeni' => $sorgu->orderByDesc('created_at'),
            'tarih_eski' => $sorgu->orderBy('created_at'),
            'miktar_cok' => $sorgu->orderByDesc('kalan_miktar'),
            'miktar_az' => $sorgu->orderBy('kalan_miktar'),
            'olcu_buyuk' => $sorgu->orderByDesc('metrekare'),
            'olcu_kucuk' => $sorgu->orderByRaw('metrekare IS NULL')->orderBy('metrekare'),
            'kod' => $sorgu->orderByRaw('COALESCE(parca_kodu, parca_kodu)'),
            'parti' => $sorgu->orderBy('parca_kodu'),
            'maliyet_cok' => $sorgu->orderByDesc('birim_maliyet'),
            'maliyet_az' => $sorgu->orderByRaw('birim_maliyet IS NULL')->orderBy('birim_maliyet'),
            'desen' => $sorgu->orderByRaw('renk_desen IS NULL')->orderBy('renk_desen'),
            default => $sorgu->orderByRaw('son_kullanma_tarihi IS NULL')->orderBy('son_kullanma_tarihi'),
        };
        $sorgu->orderBy('parca_kodu');

        return $sorgu->limit(50)->get()
            ->mapWithKeys(fn (StokParcasi $parti): array => [(string) $parti->parca_kodu => static::partiSecimEtiketi($parti)])
            ->all();
    }

    private static function parcaKoduEtiketi(Get $get, string $parcaKodu): ?string
    {
        $stokId = static::formStokId($get);
        if ($stokId < 1 || trim($parcaKodu) === '') {
            return null;
        }

        $sorgu = StokParcasi::query()
            ->where('stok_id', $stokId)
            ->where('parca_kodu', $parcaKodu)
            ->with(['olcuBakiyeleri' => fn (Builder $query): Builder => $query->where('ana_miktar', '>', 0)->with('olcu:id,ad,kod')]);
        $depoId = static::formStateInt($get, ['depo_id', '../depo_id', '../../depo_id', '../../../depo_id', '../../../../depo_id']);
        $depoId > 0 ? $sorgu->where('depo_id', $depoId) : $sorgu->whereNull('depo_id');
        $parti = $sorgu->first();

        return $parti ? static::partiSecimEtiketi($parti) : null;
    }

    private static function partiSecimEtiketi(StokParcasi $parti): string
    {
        $etiket = (bool) $parti->parca_mi ? 'Stok parçası · ' : 'Parti / lot · ';
        $etiket .= ($parti->parca_kodu ?: $parti->parca_kodu).' · Kalan: '.$parti->kalan_miktar;
        if ($parti->metrekare !== null) {
            $etiket .= ' · '.$parti->metrekare.' m²';
        }
        $bakiye = $parti->olcuBakiyeleri->first();
        if ($bakiye) {
            $etiket .= ' · '.($bakiye->olcu?->ad ?: $bakiye->olcu?->kod ?: 'Ölçü')
                .' · '.$bakiye->adet_esdegeri.' adet';
        }
        if (filled($parti->renk_desen)) {
            $etiket .= ' · '.$parti->renk_desen;
        }
        if (filled($parti->barkod) && $parti->barkod !== $parti->parca_kodu) {
            $etiket .= ' · Barkod: '.$parti->barkod;
        }

        return $etiket.' · '.optional($parti->created_at)->format('d.m.Y H:i');
    }

    /** @return array<int|string, string> */
    private static function olcuBakiyesiSecenekleri(Get $get, string $arama = ''): array
    {
        $firmaId = static::formFirmaId($get);
        $stokId = static::formStokId($get);
        $depoId = static::formStateInt($get, ['depo_id', '../depo_id', '../../depo_id', '../../../depo_id', '../../../../depo_id']);
        if ($firmaId < 1 || $stokId < 1 || $depoId < 1) {
            return [];
        }

        return StokOlcuBakiyesi::withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('stok_id', $stokId)
            ->where('depo_id', $depoId)
            ->where('ana_miktar', '>', 0)
            ->when(trim($arama) !== '', function (Builder $query) use ($arama): void {
                $arama = trim($arama);
                $query->where(function (Builder $inner) use ($arama): void {
                    $inner->where('ana_miktar', 'like', '%'.$arama.'%')
                        ->orWhere('adet_esdegeri', 'like', '%'.$arama.'%')
                        ->orWhereHas('olcu', fn (Builder $olcu): Builder => $olcu
                            ->where('ad', 'like', '%'.$arama.'%')
                            ->orWhere('kod', 'like', '%'.$arama.'%')
                            ->orWhere('en', 'like', '%'.$arama.'%')
                            ->orWhere('boy', 'like', '%'.$arama.'%')
                            ->orWhere('yukseklik', 'like', '%'.$arama.'%'))
                        ->orWhereHas('parti', fn (Builder $parti): Builder => $parti
                            ->where('parca_kodu', 'like', '%'.$arama.'%')
                            ->orWhere('parca_kodu', 'like', '%'.$arama.'%')
                            ->orWhere('barkod', 'like', '%'.$arama.'%')
                            ->orWhere('plaka_no', 'like', '%'.$arama.'%')
                            ->orWhere('renk_desen', 'like', '%'.$arama.'%')
                            ->orWhere('kalite_sinifi', 'like', '%'.$arama.'%')
                            ->orWhere('metrekare', 'like', '%'.$arama.'%')
                            ->orWhere('birim_maliyet', 'like', '%'.$arama.'%')
                            ->orWhere('created_at', 'like', '%'.$arama.'%'));
                });
            })
            ->with(['olcu:id,ad,kod', 'parti:id,parca_kodu,parca_kodu,barkod,plaka_no,metrekare,created_at'])
            ->orderByDesc('parca_kapsami')
            ->orderByDesc('ana_miktar')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (StokOlcuBakiyesi $bakiye): array => [(int) $bakiye->id => static::olcuBakiyesiSecimEtiketi($bakiye)])
            ->all();
    }

    private static function olcuBakiyesiEtiketi(Get $get, int $bakiyeId): ?string
    {
        if ($bakiyeId < 1) {
            return null;
        }
        $bakiye = StokOlcuBakiyesi::withoutGlobalScopes()
            ->where('firma_id', static::formFirmaId($get))
            ->where('stok_id', static::formStokId($get))
            ->with(['olcu:id,ad,kod', 'parti:id,parca_kodu,parca_kodu,barkod,plaka_no,metrekare,created_at'])
            ->find($bakiyeId);

        return $bakiye ? static::olcuBakiyesiSecimEtiketi($bakiye) : null;
    }

    private static function olcuBakiyesiSecimEtiketi(StokOlcuBakiyesi $bakiye): string
    {
        $parca = $bakiye->parti;
        $etiket = $parca ? 'Parça: '.($parca->parca_kodu ?: $parca->parca_kodu).' · ' : 'Toplu ölçü · ';
        $etiket .= ($bakiye->olcu?->ad ?: $bakiye->olcu?->kod ?: 'Ölçü')
            .' · '.$bakiye->ana_miktar.' ana · '.$bakiye->adet_esdegeri.' adet';
        if ($parca?->metrekare !== null) {
            $etiket .= ' · '.$parca->metrekare.' m²';
        }

        return $etiket;
    }

    private static function aktifFizikselParcaVarMi(Get $get): bool
    {
        $stokId = static::formStokId($get);
        if ($stokId < 1) {
            return false;
        }

        $sorgu = StokParcasi::query()
            ->where('stok_id', $stokId)
            ->where('parca_mi', true)
            ->where('kalan_miktar', '>', 0);
        $depoId = static::formStateInt($get, ['depo_id', '../depo_id', '../../depo_id', '../../../depo_id', '../../../../depo_id']);
        $depoId > 0 ? $sorgu->where('depo_id', $depoId) : $sorgu->whereNull('depo_id');

        return $sorgu->exists();
    }

    private static function formStateString(Get $get, array $yollar, string $varsayilan = ''): string
    {
        foreach ($yollar as $yol) {
            $deger = $get($yol);
            if (is_string($deger) && trim($deger) !== '') {
                return trim($deger);
            }
        }

        return $varsayilan;
    }

    /** @return array<int|string, string> */
    private static function seriNoSecenekleri(Get $get): array
    {
        $stokId = (int) ($get('stok_id') ?? 0);
        if ($stokId < 1) {
            return [];
        }

        $sorgu = StokSeriNo::query()
            ->where('stok_id', $stokId)
            ->where('durum', 'stokta')
            ->orderBy('seri_no');
        $depoId = (int) ($get('depo_id') ?? 0);
        $depoId > 0 ? $sorgu->where('depo_id', $depoId) : $sorgu->whereNull('depo_id');

        return $sorgu->limit(500)->pluck('seri_no', 'seri_no')
            ->all();
    }

    /**
     * @return array<int, Field>
     */
    public static function tutarOzetiFormAlanlari(): array
    {
        return static::tutarOzetiAlanlari();
    }

    /**
     * @return array<int, Field>
     */
    private static function tutarOzetiAlanlari(): array
    {
        return [
            TextInput::make('mal_hizmet_toplam_tutari_gosterim')
                ->label('Toplam Tutar')
                ->inlineLabel()
                ->numeric()
                ->default(0)
                ->readOnly()
                ->dehydrated(false),
            TextInput::make('toplam_indirim')
                ->label('Toplam İskonto')
                ->inlineLabel()
                ->numeric()
                ->default(0)
                ->readOnly(),
            TextInput::make('kdv_toplam')
                ->label('Toplam KDV')
                ->inlineLabel()
                ->numeric()
                ->default(0)
                ->required()
                ->readOnly(),
            TextInput::make('genel_toplam')
                ->label('KDV Dahil Tutar')
                ->inlineLabel()
                ->numeric()
                ->default(0)
                ->required()
                ->readOnly(),
            Select::make('tevkifat_orani')
                ->label('Tevkifat Oranı')
                ->inlineLabel()
                ->options(fn (Get $get): array => static::vergiOraniSecenekleri((int) ($get('firma_id') ?: static::aktifFirmaId())))
                ->searchable()
                ->createOptionForm([
                    TextInput::make('oran')
                        ->label('Manuel Oran (%)')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->maxValue(100),
                ])
                ->createOptionUsing(fn (array $data): string => (string) ((float) ($data['oran'] ?? 0)))
                ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? 0 : (float) $state)
                ->default(fn () => StokKartiKaynagi::vergiOranFormAnahtari(0.0))
                ->live()
                ->afterStateUpdated(fn (Get $get, callable $set) => static::ozetiHesaplaFormdan($get, $set, false)),
            TextInput::make('tevkifat_tutari_gosterim')
                ->label('Tevkifat Tutarı')
                ->inlineLabel()
                ->numeric()
                ->default(0)
                ->readOnly()
                ->dehydrated(false),
            TextInput::make('odenecek_tutar')
                ->label('Ödenecek Tutar')
                ->inlineLabel()
                ->numeric()
                ->default(0)
                ->readOnly(),
            TextInput::make('odendi_tutari')->numeric()->default(0)->hidden(),
            TextInput::make('acik_tutar')->numeric()->default(0)->hidden(),
            TextInput::make('genel_indirim_tutari')->numeric()->default(0)->hidden(),
            TextInput::make('ara_toplam')->numeric()->default(0)->hidden(),
        ];
    }

    /**
     * @param  array<string, mixed>  $kalem
     * @return array<string, mixed>
     */
    public static function hesaplaKalemSatiri(array $kalem, string $guncellenenAlan = ''): array
    {
        $miktar = (float) ($kalem['miktar'] ?? 0);
        $birimFiyat = (float) ($kalem['birim_fiyat'] ?? 0);
        $olcuFiyat = static::olcuFiyatState($kalem, $guncellenenAlan);
        $olcuBirimFiyati = null;
        if ($olcuFiyat !== []) {
            $kalem = array_merge($kalem, $olcuFiyat);
            $birimFiyat = (float) $kalem['birim_fiyat'];
            $olcuBirimFiyati = (string) $kalem['birim_fiyat'];
        }
        $kdvOrani = (float) ($kalem['kdv_orani'] ?? 0);
        $kdvTutari = (float) ($kalem['kdv_tutari'] ?? 0);
        $indirimOrani = (float) ($kalem['indirim_orani'] ?? 0);
        $indirimTutari = (float) ($kalem['indirim_tutari'] ?? 0);

        $brut = static::paraYuvarla((float) ($olcuFiyat['fiyat_miktari'] ?? $miktar) * $birimFiyat);

        if ($guncellenenAlan === 'indirim_tutari') {
            $indirimTutari = min(max($indirimTutari, 0), $brut);
            $indirimOrani = $brut > 0 ? ($indirimTutari / $brut) * 100 : 0;
        } else {
            // Kayit/hydrate asamasinda oran kirpilmalarindan kaynakli kaymayi engelle:
            // Varsayilan olarak indirim_tutari otoriterdir, oran bu tutardan turetilir.
            if (
                $guncellenenAlan === ''
                && array_key_exists('indirim_tutari', $kalem)
            ) {
                $indirimTutari = min(max((float) ($kalem['indirim_tutari'] ?? 0), 0), $brut);
                $indirimOrani = $brut > 0 ? ($indirimTutari / $brut) * 100 : 0;
            } else {
                $indirimTutari = static::paraYuvarla($brut * $indirimOrani / 100);
            }
        }

        $indirimTutari = static::paraYuvarla(min(max($indirimTutari, 0), $brut));
        $kdvMatrahi = static::paraYuvarla(max($brut - $indirimTutari, 0));
        if ($guncellenenAlan === 'kdv_tutari') {
            $kdvTutari = max(0, $kdvTutari);
            $kdvOrani = $kdvMatrahi > 0 ? ($kdvTutari / $kdvMatrahi) * 100 : 0;
        } elseif ($guncellenenAlan === '' && array_key_exists('kdv_tutari', $kalem)) {
            $kdvTutari = max(0, (float) ($kalem['kdv_tutari'] ?? 0));
            $kdvOrani = $kdvMatrahi > 0 ? ($kdvTutari / $kdvMatrahi) * 100 : 0;
        } else {
            $kdvTutari = static::paraYuvarla($kdvMatrahi * $kdvOrani / 100);
        }
        $kdvTutari = static::paraYuvarla($kdvTutari);
        $toplam = static::paraYuvarla($kdvMatrahi + $kdvTutari);

        $kalem['miktar'] = $miktar;
        $kalem['birim_fiyat'] = $olcuBirimFiyati ?? $birimFiyat;
        $kalem['brut_fiyat_gosterim'] = $brut;
        $kalem['kdv_orani'] = $kdvOrani;
        $kalem['indirim_orani'] = $indirimOrani;
        $kalem['indirim_tutari'] = $indirimTutari;
        $kalem['kdv_tutari'] = $kdvTutari;
        $kalem['toplam'] = $toplam;
        $kalem['net_tutar'] = $kdvMatrahi;
        $kalem['satir_toplami'] = $brut;
        $kalem['satir_genel_toplam'] = $toplam;
        $kalem['satir_indirim_tutari'] = $indirimTutari;
        $kalem['para_birimi'] = strtoupper((string) ($kalem['para_birimi'] ?? 'TRY'));

        return $kalem;
    }

    /** @return array<string, mixed> */
    private static function olcuFiyatState(array $kalem, string $guncellenenAlan): array
    {
        $stokId = (int) ($kalem['stok_id'] ?? 0);
        $dagilimlar = static::seciliOlcuDagilimlariniAyikla(
            is_array($kalem['olcu_dagilimlari'] ?? null) ? $kalem['olcu_dagilimlari'] : [],
        );
        if ($stokId < 1 || $dagilimlar === []) {
            return [];
        }

        $stok = StokKarti::withoutGlobalScopes()->find($stokId);
        if (! $stok?->olculu_takip_turu?->olculuMu()) {
            return [];
        }

        $ana = '0';
        $adet = '0';
        $faktorler = [];
        foreach ($dagilimlar as $dagilim) {
            $olcu = StokOlcusu::withoutGlobalScopes()
                ->where('firma_id', $stok->firma_id)
                ->where('stok_id', $stok->id)
                ->find((int) ($dagilim['stok_olcusu_id'] ?? 0));
            if (! $olcu || ! $olcu->aktif_mi) {
                throw ValidationException::withMessages(['olcu_dagilimlari' => 'Aktif ve geçerli bir ölçü seçilmelidir.']);
            }
            $faktor = (string) $olcu->bir_adet_ana_miktar;
            if ($faktor === '' || bccomp($faktor, '0', 8) <= 0) {
                throw ValidationException::withMessages(['fiyat_birimi_id' => 'Ölçünün dönüşüm katsayısı geçersizdir.']);
            }
            $girilen = bcadd((string) ($dagilim['girilen_miktar'] ?? '0'), '0', 16);
            if (bccomp($girilen, '0', 16) <= 0) {
                throw ValidationException::withMessages(['olcu_dagilimlari' => 'Ölçü miktarı sıfırdan büyük olmalıdır.']);
            }
            $satirAna = (int) ($dagilim['islem_birimi_id'] ?? 0) === (int) $stok->ikincil_birim_id
                ? bcmul($girilen, $faktor, 16)
                : $girilen;
            $satirAdet = bcdiv($satirAna, $faktor, 16);
            if (! (bool) $stok->parcali_kullanima_izin
                && bccomp($satirAdet, bcadd((string) floor((float) $satirAdet), '0', 8), 8) !== 0) {
                throw ValidationException::withMessages(['miktar' => 'Parçalı kullanım kapalı olduğu için küsuratlı adet kullanılamaz.']);
            }
            $ana = bcadd($ana, $satirAna, 16);
            $adet = bcadd($adet, $satirAdet, 16);
            $faktorler[] = $faktor;
        }

        $anaBirimId = (int) ($stok->ana_birim_id ?? 0);
        $adetBirimId = (int) ($stok->ikincil_birim_id ?? 0);
        $gelenFiyatBirimi = $kalem['fiyat_birimi_id'] ?? null;
        $fiyatBirimiId = (int) ($gelenFiyatBirimi ?? 0);
        if ($gelenFiyatBirimi !== null && $gelenFiyatBirimi !== '' && ! in_array($fiyatBirimiId, [$anaBirimId, $adetBirimId], true)) {
            throw ValidationException::withMessages(['fiyat_birimi_id' => 'Ölçülü fiyat birimi seçili stok kartının ana veya adet birimi olmalıdır.']);
        }
        if ($fiyatBirimiId < 1) {
            $fiyatBirimiId = $anaBirimId;
        }
        $fiyatBirimi = $fiyatBirimiId === $adetBirimId ? 'adet' : 'ana';
        $fiyatMiktari = $fiyatBirimi === 'adet' ? $adet : $ana;
        $dogrudan = (bool) ($kalem['dogrudan_ortak_adet_fiyati'] ?? false);
        $fiyat = (string) ($kalem['birim_fiyat'] ?? '0');

        try {
            $donusum = app(FaturaOlcuFiyatlandirmaServisi::class)->cokluOlcuDonusumu(
                $faktorler,
                $fiyat,
                $fiyatBirimi,
                $dogrudan,
            );
        } catch (IsKuraliIstisnasi $e) {
            throw ValidationException::withMessages(['fiyat_birimi_id' => $e->getMessage()]);
        }

        $onceki = json_decode((string) ($kalem['olcu_donusum_snapshot'] ?? ''), true);
        if ($guncellenenAlan === 'fiyat_birimi_id'
            && is_array($onceki)
            && (int) ($onceki['fiyat_birimi_id'] ?? 0) > 0
            && (int) $onceki['fiyat_birimi_id'] !== $fiyatBirimiId) {
            $eskiToplam = app(FaturaOlcuFiyatlandirmaServisi::class)->toplam(
                (string) ($onceki['birim_fiyat'] ?? $fiyat),
                (string) ($onceki['fiyat_miktari'] ?? '0'),
            );
            $fiyat = app(FaturaOlcuFiyatlandirmaServisi::class)->anaBirimFiyati($eskiToplam, $fiyatMiktari);
        }

        return [
            'fiyat_birimi_id' => $fiyatBirimiId,
            'fiyat_miktari' => bcadd($fiyatMiktari, '0', 8),
            'birim_fiyat' => bcadd($fiyat, '0', 8),
            'ana_miktar' => bcadd($ana, '0', 8),
            'adet_esdegeri' => bcadd($adet, '0', 8),
            'olcu_donusum_snapshot' => json_encode([
                'fiyat_birimi_id' => $fiyatBirimiId,
                'fiyat_birimi' => $fiyatBirimi,
                'birim_fiyat' => bcadd($fiyat, '0', 8),
                'fiyat_miktari' => bcadd($fiyatMiktari, '0', 8),
                'ana_miktar' => bcadd($ana, '0', 8),
                'adet_esdegeri' => bcadd($adet, '0', 8),
                'kat_sayilari' => $donusum['kat_sayilari'],
                'donusum_turu' => $donusum['donusum_turu'],
                'dogrudan_ortak_adet_fiyati' => $dogrudan,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Kartlarda yalnız kullanıcı tarafından işaretlenen veya miktarı girilen ölçüler
     * faturanın kalıcı dağılımına aktarılır.
     *
     * @param  array<int|string, mixed>  $dagilimlar
     * @return array<int, array<string, mixed>>
     */
    public static function seciliOlcuDagilimlariniAyikla(array $dagilimlar): array
    {
        $secili = [];

        foreach ($dagilimlar as $dagilim) {
            if (! is_array($dagilim)) {
                continue;
            }

            if (! (bool) ($dagilim['faturada_kullan'] ?? false) && ! static::olcuDagilimiMiktariPozitifMi($dagilim)) {
                continue;
            }

            unset($dagilim['faturada_kullan']);
            $secili[] = $dagilim;
        }

        return array_values($secili);
    }

    /**
     * Ölçü kartlarındaki görüntüleme alanlarını çıkarır ve ölçülü stokta eksik
     * seçim varsa bunu fatura oluşturulmadan önce anlaşılır biçimde bildirir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function olcuKartlariniFaturaVerisineDonustur(array $data, int $firmaId): array
    {
        $cikis = in_array((string) ($data['tur'] ?? ''), [FaturaTuru::Giden->value, FaturaTuru::AlisIadesi->value], true);
        $kalemler = is_array($data['kalemler'] ?? null) ? $data['kalemler'] : [];

        foreach ($kalemler as $indeks => $kalem) {
            if (! is_array($kalem)) {
                continue;
            }

            $stok = StokKarti::withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->find((int) ($kalem['stok_id'] ?? 0));
            if (! $stok?->olculu_takip_turu?->olculuMu()) {
                continue;
            }

            $dagilimlar = static::seciliOlcuDagilimlariniAyikla(
                is_array($kalem['olcu_dagilimlari'] ?? null) ? $kalem['olcu_dagilimlari'] : [],
            );
            if ($dagilimlar === []) {
                $otomatik = static::faturaSatirOlcuDagilimi($kalem, $cikis);
                if ($otomatik !== null) {
                    $dagilimlar = [$otomatik];
                }
            }
            if ($cikis && count($dagilimlar) === 1 && (int) ($dagilimlar[0]['stok_olcu_bakiyesi_id'] ?? 0) < 1) {
                $otomatik = static::faturaSatirOlcuDagilimi($kalem, true);
                if ($otomatik !== null) {
                    $dagilimlar = [$otomatik];
                }
            }
            if ($dagilimlar === []) {
                throw ValidationException::withMessages([
                    "kalemler.{$indeks}.olcu_dagilimlari" => 'Ölçülü stok için en az bir ölçü kartını seçip fatura miktarını girin.',
                ]);
            }
            if ($cikis && collect($dagilimlar)->contains(fn (array $dagilim): bool => (int) ($dagilim['stok_olcu_bakiyesi_id'] ?? 0) < 1)) {
                throw ValidationException::withMessages([
                    "kalemler.{$indeks}.olcu_dagilimlari" => 'Giden faturada seçilen her ölçü için çıkış yapılacak stok bakiyesi seçilmelidir.',
                ]);
            }

            $kalem['olcu_dagilimlari'] = $dagilimlar;
            $kalemler[$indeks] = $kalem;
        }

        $data['kalemler'] = $kalemler;

        return $data;
    }

    /** @param array<string, mixed> $dagilim */
    private static function olcuDagilimiMiktariPozitifMi(array $dagilim): bool
    {
        $miktar = str_replace(',', '.', trim((string) ($dagilim['girilen_miktar'] ?? '')));

        return $miktar !== '' && is_numeric($miktar) && (float) $miktar > 0;
    }

    private static function olcuKartlariFormdaVarMi(Get $get): bool
    {
        $dagilimlar = $get('olcu_dagilimlari');

        return is_array($dagilimlar) && $dagilimlar !== [];
    }

    /** @return array<int, array<string, int|null>> */
    public static function olcuKartlariniBaslat(int $firmaId, int $stokId, ?int $depoId = null): array
    {
        $stok = StokKarti::withoutGlobalScopes()->where('firma_id', $firmaId)->find($stokId);
        if (! $stok?->olculu_takip_turu?->olculuMu()) {
            return [];
        }

        $olculer = StokOlcusu::withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('stok_id', $stokId)
            ->where('aktif_mi', true)
            ->orderBy('id')
            ->get(['id']);
        if ((string) ($stok->olcu_yapisi ?? 'sabit') === 'sabit') {
            $olculer = $olculer->take(1);
        }

        $bakiyeler = StokOlcuBakiyesi::withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('stok_id', $stokId)
            ->where('ana_miktar', '>', 0)
            ->when($depoId, fn (Builder $query): Builder => $query->where('depo_id', $depoId))
            ->orderByDesc('ana_miktar')
            ->get(['id', 'stok_olcusu_id', 'stok_parcasi_id', 'depo_id'])
            ->groupBy('stok_olcusu_id');

        return $olculer->map(function (StokOlcusu $olcu) use ($bakiyeler, $stok, $depoId): array {
            /** @var StokOlcuBakiyesi|null $bakiye */
            $bakiye = $bakiyeler->get((int) $olcu->id)?->first();

            return [
                'stok_olcusu_id' => (int) $olcu->id,
                'stok_olcu_bakiyesi_id' => $bakiye?->id ? (int) $bakiye->id : null,
                'stok_parcasi_id' => $bakiye?->stok_parcasi_id ? (int) $bakiye->stok_parcasi_id : null,
                'depo_id' => $bakiye?->depo_id ? (int) $bakiye->depo_id : ($depoId ?: (int) ($stok->depo_id ?? 0) ?: null),
                'islem_birimi_id' => (int) ($stok->ikincil_birim_id ?: $stok->ana_birim_id),
                'girilen_miktar' => null,
                'faturada_kullan' => false,
            ];
        })->values()->all();
    }

    /** @param array<string, mixed> $state */
    private static function olcuKartiBasligi(array $state): string
    {
        $olcu = StokOlcusu::withoutGlobalScopes()->find((int) ($state['stok_olcusu_id'] ?? 0));
        if (! $olcu) {
            return 'Yeni ölçü seçin';
        }

        return trim($olcu->kod.' · '.$olcu->ad);
    }

    private static function olcuKartTakipTuru(Get $get): string
    {
        $stok = StokKarti::withoutGlobalScopes()->find(static::formStokId($get));

        return $stok?->olculu_takip_turu?->value ?? 'standart';
    }

    private static function olcuKartKaydi(Get $get): ?StokOlcusu
    {
        $olcuId = static::formStateInt($get, ['stok_olcusu_id', '../stok_olcusu_id', '../../stok_olcusu_id']);
        if ($olcuId < 1) {
            return null;
        }

        return StokOlcusu::withoutGlobalScopes()
            ->where('firma_id', static::formFirmaId($get))
            ->where('stok_id', static::formStokId($get))
            ->find($olcuId);
    }

    private static function olcuKartAlani(Get $get, string $alan): string
    {
        $olcu = static::olcuKartKaydi($get);
        if (! $olcu || blank($olcu->{$alan})) {
            return '—';
        }

        $sonEk = match ($alan) {
            'en', 'boy', 'yukseklik' => ' '.($olcu->olcu_birimi ?: ''),
            'bir_adet_agirlik' => ' '.($olcu->agirlik_birimi ?: 'kg'),
            default => '',
        };

        return (string) $olcu->{$alan}.$sonEk;
    }

    private static function olcuKartBakiyeOzeti(Get $get): string
    {
        $olcu = static::olcuKartKaydi($get);
        if (! $olcu) {
            return '—';
        }

        $bakiyeId = static::formStateInt($get, ['stok_olcu_bakiyesi_id', '../stok_olcu_bakiyesi_id']);
        $sorgu = StokOlcuBakiyesi::withoutGlobalScopes()
            ->where('firma_id', static::formFirmaId($get))
            ->where('stok_id', static::formStokId($get))
            ->where('stok_olcusu_id', $olcu->id);
        if ($bakiyeId > 0) {
            $sorgu->whereKey($bakiyeId);
        } else {
            $depoId = static::formStateInt($get, ['depo_id', '../depo_id', '../../depo_id', '../../../depo_id']);
            if ($depoId > 0) {
                $sorgu->where('depo_id', $depoId);
            }
        }

        $ana = '0';
        $adet = '0';
        foreach ($sorgu->get(['ana_miktar', 'adet_esdegeri']) as $bakiye) {
            $ana = bcadd($ana, (string) $bakiye->ana_miktar, 8);
            $adet = bcadd($adet, (string) $bakiye->adet_esdegeri, 8);
        }

        $stok = StokKarti::withoutGlobalScopes()->find(static::formStokId($get));
        $anaBirim = $stok?->ana_birim_id
            ? (string) Birim::withoutGlobalScopes()->whereKey($stok->ana_birim_id)->value('kod')
            : 'ana birim';

        return $adet.' adet · '.$ana.' '.$anaBirim;
    }

    /** @return array<int, Field> */
    private static function olcuTanimlamaFormu(): array
    {
        return [
            Hidden::make('ana_firma_id')
                ->default(fn (Get $get): int => static::formFirmaId($get))
                ->dehydrated(),
            Hidden::make('ana_stok_id')
                ->default(fn (Get $get): int => static::formStokId($get))
                ->dehydrated(),
            TextInput::make('kod')
                ->label('Ölçü kodu')
                ->required()
                ->maxLength(64),
            TextInput::make('ad')
                ->label('Ölçü adı')
                ->maxLength(191)
                ->helperText('Boş bırakırsanız ölçü kodu ad olarak kullanılır.'),
            Select::make('olcu_birimi')
                ->label('Ölçü birimi')
                ->options(['mm' => 'mm', 'cm' => 'cm', 'm' => 'metre'])
                ->default('cm')
                ->required(fn (Get $get): bool => static::olcuKartTakipTuru($get) !== 'agirlik')
                ->visible(fn (Get $get): bool => static::olcuKartTakipTuru($get) !== 'agirlik'),
            TextInput::make('en')
                ->label('En')
                ->numeric()
                ->required(fn (Get $get): bool => in_array(static::olcuKartTakipTuru($get), ['alan', 'hacim'], true))
                ->visible(fn (Get $get): bool => in_array(static::olcuKartTakipTuru($get), ['alan', 'hacim'], true)),
            TextInput::make('boy')
                ->label('Boy')
                ->numeric()
                ->required(fn (Get $get): bool => in_array(static::olcuKartTakipTuru($get), ['uzunluk', 'alan', 'hacim'], true))
                ->visible(fn (Get $get): bool => in_array(static::olcuKartTakipTuru($get), ['uzunluk', 'alan', 'hacim'], true)),
            TextInput::make('yukseklik')
                ->label('Kalınlık / yükseklik')
                ->numeric()
                ->required(fn (Get $get): bool => static::olcuKartTakipTuru($get) === 'hacim')
                ->visible(fn (Get $get): bool => static::olcuKartTakipTuru($get) === 'hacim'),
            TextInput::make('bir_adet_agirlik')
                ->label('Bir adet ağırlığı')
                ->numeric()
                ->required(fn (Get $get): bool => static::olcuKartTakipTuru($get) === 'agirlik')
                ->visible(fn (Get $get): bool => static::olcuKartTakipTuru($get) === 'agirlik'),
            Select::make('agirlik_birimi')
                ->label('Ağırlık birimi')
                ->options(['g' => 'g', 'kg' => 'kg', 'ton' => 'ton'])
                ->default('kg')
                ->required(fn (Get $get): bool => static::olcuKartTakipTuru($get) === 'agirlik')
                ->visible(fn (Get $get): bool => static::olcuKartTakipTuru($get) === 'agirlik'),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function faturaIcinOlcuOlustur(int $firmaId, int $stokId, array $data): int
    {
        if ($firmaId < 1 || $stokId < 1) {
            throw ValidationException::withMessages(['stok_olcusu_id' => 'Ölçü eklemek için önce stok seçin.']);
        }

        $stok = StokKarti::withoutGlobalScopes()->where('firma_id', $firmaId)->find($stokId);
        if (! $stok?->olculu_takip_turu?->olculuMu()) {
            throw ValidationException::withMessages(['stok_olcusu_id' => 'Yeni ölçü yalnız ölçülü stok kartına eklenebilir.']);
        }

        $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
        if ($kod === '') {
            throw ValidationException::withMessages(['stok_olcusu_id' => 'Ölçü kodu zorunludur.']);
        }
        if (StokOlcusu::withoutGlobalScopes()->where('firma_id', $firmaId)->where('stok_id', $stokId)->where('kod', $kod)->exists()) {
            throw ValidationException::withMessages(['stok_olcusu_id' => 'Bu ölçü kodu stok kartında zaten kullanılıyor.']);
        }

        $olcu = app(StokOlcuBakiyeServisi::class)->olcuOlustur($firmaId, $stok, [
            'kod' => $kod,
            'ad' => trim((string) ($data['ad'] ?? '')) ?: $kod,
            'olcu_birimi' => $data['olcu_birimi'] ?? null,
            'en' => $data['en'] ?? null,
            'boy' => $data['boy'] ?? null,
            'yukseklik' => $data['yukseklik'] ?? null,
            'bir_adet_agirlik' => $data['bir_adet_agirlik'] ?? null,
            'agirlik_birimi' => $data['agirlik_birimi'] ?? 'kg',
            'agirlik_turu' => 'sabit',
            'aktif_mi' => true,
        ]);

        return (int) $olcu->id;
    }

    private static function kalemOlculuMu(Get $get): bool
    {
        $stok = StokKarti::withoutGlobalScopes()->find(static::formStokId($get));

        return (bool) $stok?->olculu_takip_turu?->olculuMu();
    }

    private static function kalemOlculuParcaliMi(Get $get): bool
    {
        $stok = StokKarti::withoutGlobalScopes()->find(static::formStokId($get));

        return (bool) ($stok?->olculu_takip_turu?->olculuMu() && $stok->parcali_kullanima_izin);
    }

    /** @return array<string, string> */
    private static function faturaSatirBirimSecenekleri(int $firmaId, int $stokId, bool $tekSatir): array
    {
        $stok = $stokId > 0 ? StokKarti::withoutGlobalScopes()->find($stokId) : null;
        if ($stok?->olculu_takip_turu?->olculuMu() && (bool) $stok->parcali_kullanima_izin) {
            $ids = array_values(array_filter([(int) $stok->ikincil_birim_id, (int) $stok->ana_birim_id]));
            $olcuBirimleri = Birim::withoutGlobalScopes()
                ->whereIn('id', $ids)
                ->get(['id', 'kod', 'ad'])
                ->mapWithKeys(fn (Birim $birim): array => [
                    (string) $birim->kod => $birim->ad ?: ((string) $birim->kod === 'AD' ? 'Adet' : (string) $birim->kod),
                ])
                ->all();

            if ($olcuBirimleri !== []) {
                return $olcuBirimleri;
            }
        }

        return $tekSatir ? static::birimSecenekleri($firmaId) : static::birimIlkSecenekleri($firmaId);
    }

    private static function faturaSatirVarsayilanBirimKodu(int $firmaId, int $stokId): string
    {
        $stok = $stokId > 0 ? StokKarti::withoutGlobalScopes()->find($stokId) : null;
        if ($stok?->olculu_takip_turu?->olculuMu()) {
            $birimId = (int) ($stok->varsayilan_islem_birimi_id ?: $stok->ikincil_birim_id ?: $stok->ana_birim_id);
            $kod = $birimId > 0 ? Birim::withoutGlobalScopes()->whereKey($birimId)->value('kod') : null;
            if (filled($kod)) {
                return (string) $kod;
            }
        }

        return static::varsayilanBirimKodu($firmaId);
    }

    private static function faturaSatirOlcuBirimId(StokKarti $stok, mixed $birim): int
    {
        $kod = Str::upper(trim((string) $birim));
        $id = Birim::withoutGlobalScopes()->where('kod', $kod)->value('id');
        if ($id && in_array((int) $id, [(int) $stok->ana_birim_id, (int) $stok->ikincil_birim_id], true)) {
            return (int) $id;
        }

        return (int) ($stok->varsayilan_islem_birimi_id ?: $stok->ikincil_birim_id ?: $stok->ana_birim_id);
    }

    /** @return array<string, mixed>|null */
    private static function faturaSatirOlcuDagilimi(array $kalem, bool $cikis = false): ?array
    {
        $stok = StokKarti::withoutGlobalScopes()->find((int) ($kalem['stok_id'] ?? 0));
        if (! $stok?->olculu_takip_turu?->olculuMu()) {
            return null;
        }

        $olcu = StokOlcusu::withoutGlobalScopes()
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->id)
            ->where('aktif_mi', true)
            ->orderBy('id')
            ->first();
        if (! $olcu) {
            return null;
        }

        $birimId = static::faturaSatirOlcuBirimId($stok, $kalem['birim'] ?? null);
        $miktar = str_replace(',', '.', trim((string) ($kalem['miktar'] ?? '')));
        if ($miktar === '' || ! is_numeric($miktar) || (float) $miktar <= 0) {
            return null;
        }

        $faktor = (string) $olcu->bir_adet_ana_miktar;
        if ($faktor === '' || bccomp($faktor, '0', 8) <= 0) {
            return null;
        }
        if (! (bool) $stok->parcali_kullanima_izin) {
            $ana = (int) $birimId === (int) $stok->ikincil_birim_id
                ? bcmul($miktar, $faktor, 8)
                : bcadd($miktar, '0', 8);
            $adet = bcdiv($ana, $faktor, 8);
            if (bccomp($adet, bcadd((string) floor((float) $adet), '0', 8), 8) !== 0) {
                throw ValidationException::withMessages(['miktar' => 'Parçalı kullanım kapalı olduğu için küsuratlı adet kullanılamaz.']);
            }
        }

        $depoId = (int) ($kalem['depo_id'] ?? $stok->depo_id ?: 0);
        $bakiyeId = null;
        if ($cikis) {
            $bakiyeler = StokOlcuBakiyesi::withoutGlobalScopes()
                ->where('firma_id', $stok->firma_id)
                ->where('stok_id', $stok->id)
                ->where('stok_olcusu_id', $olcu->id)
                ->when($depoId > 0, fn ($query) => $query->where('depo_id', $depoId))
                ->where('ana_miktar', '>', 0)
                ->get(['id']);
            if ($bakiyeler->count() === 1) {
                $bakiyeId = (int) $bakiyeler->first()->id;
                $depoId = (int) StokOlcuBakiyesi::withoutGlobalScopes()->whereKey($bakiyeId)->value('depo_id');
            }
        }

        return [
            'stok_olcusu_id' => (int) $olcu->id,
            'stok_olcu_bakiyesi_id' => $bakiyeId,
            'depo_id' => $depoId ?: null,
            'islem_birimi_id' => $birimId,
            'girilen_miktar' => $miktar,
            'faturada_kullan' => true,
        ];
    }

    /** @return array<int, string> */
    public static function olculuFiyatBirimiSecenekleri(int $stokId): array
    {
        $stok = StokKarti::withoutGlobalScopes()->find($stokId);
        if (! $stok?->olculu_takip_turu?->olculuMu()) {
            return [];
        }

        return Birim::withoutGlobalScopes()
            ->whereIn('id', array_values(array_filter([(int) $stok->ana_birim_id, (int) $stok->ikincil_birim_id])))
            ->get(['id', 'ad', 'kod'])
            ->mapWithKeys(fn (Birim $birim): array => [(int) $birim->id => $birim->ad.' ('.$birim->kod.')'])
            ->all();
    }

    /** @return array<int, string> */
    public static function olculuIslemBirimiSecenekleri(int $stokId): array
    {
        $stok = StokKarti::withoutGlobalScopes()->find($stokId);
        if (! $stok?->olculu_takip_turu?->olculuMu()) {
            return [];
        }

        return Birim::withoutGlobalScopes()
            ->whereIn('id', array_values(array_filter([(int) $stok->ana_birim_id, (int) $stok->ikincil_birim_id])))
            ->get(['id', 'ad', 'kod'])
            ->mapWithKeys(fn (Birim $birim): array => [(int) $birim->id => $birim->ad.' ('.$birim->kod.')'])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hesaplaFormKalemleriVeOzet(array $data): array
    {
        $kalemler = is_array($data['kalemler'] ?? null) ? $data['kalemler'] : [];

        $hesapliKalemler = [];
        $malHizmetToplam = 0.0;
        $araToplam = 0.0; // servis doğrulaması için net matrah toplamı
        $toplamIndirim = 0.0;
        $kdvToplam = 0.0;
        $genelToplam = 0.0;

        foreach (array_values($kalemler) as $index => $kalem) {
            $satir = static::hesaplaKalemSatiri((array) $kalem);
            $satir['sira_no'] = $index + 1;
            $satir['satir_no'] = $index + 1;
            $hesapliKalemler[] = $satir;

            $brut = (float) ($satir['satir_toplami'] ?? 0);
            $matrah = (float) ($satir['net_tutar'] ?? 0);
            $malHizmetToplam += $brut;
            $araToplam = static::paraYuvarla($araToplam + $matrah);
            $toplamIndirim = static::paraYuvarla($toplamIndirim + (float) ($satir['indirim_tutari'] ?? 0));
            $kdvToplam = static::paraYuvarla($kdvToplam + (float) ($satir['kdv_tutari'] ?? 0));
            $genelToplam = static::paraYuvarla($genelToplam + (float) ($satir['toplam'] ?? 0));
        }

        $tevkifatOrani = (float) ($data['tevkifat_orani'] ?? 0);
        $tevkifatTutari = static::paraYuvarla($kdvToplam * $tevkifatOrani / 100);
        $odendi = (float) ($data['odendi_tutari'] ?? 0);
        $odenecek = static::paraYuvarla($genelToplam - $tevkifatTutari);
        $acik = static::paraYuvarla($odenecek - $odendi);

        $data['kalemler'] = $hesapliKalemler;
        $data['ara_toplam'] = $araToplam;
        $data['mal_hizmet_toplam_tutari_gosterim'] = $malHizmetToplam;
        $data['toplam_indirim'] = $toplamIndirim;
        $data['kdv_toplam'] = $kdvToplam;
        $data['genel_toplam'] = $genelToplam;
        $data['tevkifat_orani'] = $tevkifatOrani;
        $data['tevkifat_tutari_gosterim'] = $tevkifatTutari;
        $data['genel_indirim_tutari'] = $toplamIndirim;
        $data['odenecek_tutar'] = $odenecek;
        $data['acik_tutar'] = $acik;
        $data['para_birimi'] = strtoupper((string) ($data['para_birimi'] ?? 'TRY'));

        return $data;
    }

    private static function ozetiHesaplaFormdan(Get $get, callable $set, bool $repeaterIcinden = true): void
    {
        $kalemler = $repeaterIcinden ? $get('../../kalemler') : $get('kalemler');
        if (! is_array($kalemler)) {
            return;
        }

        $ozet = static::hesaplaFormKalemleriVeOzet([
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
        } else {
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

    private static function kalemleriHesaplaFormdan(Get $get, callable $set, string $guncellenenAlan = ''): void
    {
        $olcuDagilimi = static::faturaSatirOlcuDagilimi([
            'stok_id' => $get('stok_id'),
            'birim' => $get('olcu_satis_birimi') ?: $get('birim'),
            'miktar' => $get('miktar'),
            'depo_id' => $get('depo_id'),
        ]);
        if ($olcuDagilimi !== null) {
            $set('olcu_dagilimlari', [$olcuDagilimi]);
            $set('islem_birimi_id', $olcuDagilimi['islem_birimi_id']);
            $set('fiyat_birimi_id', $olcuDagilimi['islem_birimi_id']);
        }

        $satir = [
            'stok_id' => $get('stok_id'),
            'miktar' => $get('miktar'),
            'birim_fiyat' => $get('birim_fiyat'),
            'fiyat_birimi_id' => $get('fiyat_birimi_id'),
            'fiyat_miktari' => $get('fiyat_miktari'),
            'ana_miktar' => $get('ana_miktar'),
            'adet_esdegeri' => $get('adet_esdegeri'),
            'olcu_donusum_snapshot' => $get('olcu_donusum_snapshot'),
            'dogrudan_ortak_adet_fiyati' => $get('dogrudan_ortak_adet_fiyati'),
            'olcu_dagilimlari' => $olcuDagilimi !== null ? [$olcuDagilimi] : $get('olcu_dagilimlari'),
            'kdv_orani' => $get('kdv_orani'),
            'kdv_tutari' => $get('kdv_tutari'),
            'indirim_orani' => $get('indirim_orani'),
            'indirim_tutari' => $get('indirim_tutari'),
            'para_birimi' => $get('../../para_birimi') ?: 'TRY',
        ];

        $hesapli = static::hesaplaKalemSatiri($satir, $guncellenenAlan);
        $set('brut_fiyat_gosterim', $hesapli['brut_fiyat_gosterim']);
        $set('kdv_tutari', $hesapli['kdv_tutari']);
        $set('indirim_tutari', $hesapli['indirim_tutari']);
        $set('indirim_orani', $hesapli['indirim_orani']);
        $set('toplam', $hesapli['toplam']);
        $set('net_toplam_gosterim', $hesapli['toplam']);
        $set('net_tutar', $hesapli['net_tutar']);
        $set('satir_toplami', $hesapli['satir_toplami']);
        $set('satir_genel_toplam', $hesapli['satir_genel_toplam']);
        $set('satir_indirim_tutari', $hesapli['satir_indirim_tutari']);
        $set('fiyat_birimi_id', $hesapli['fiyat_birimi_id'] ?? $get('fiyat_birimi_id'));
        $set('fiyat_miktari', $hesapli['fiyat_miktari'] ?? $get('fiyat_miktari'));
        $set('ana_miktar', $hesapli['ana_miktar'] ?? $get('ana_miktar'));
        $set('adet_esdegeri', $hesapli['adet_esdegeri'] ?? $get('adet_esdegeri'));
        $set('olcu_donusum_snapshot', $hesapli['olcu_donusum_snapshot'] ?? $get('olcu_donusum_snapshot'));
        $set('para_birimi', $hesapli['para_birimi']);

        static::ozetiHesaplaFormdan($get, $set, true);
    }

    private static function olcuDagilimiDegisinceHesapla(Get $get, callable $set): void
    {
        $dagilimlar = static::seciliOlcuDagilimlariniAyikla(
            is_array($get('olcu_dagilimlari')) ? $get('olcu_dagilimlari') : [],
        );
        if ($dagilimlar === []) {
            return;
        }

        foreach ($dagilimlar as $dagilim) {
            if (! is_array($dagilim)
                || (int) ($dagilim['stok_olcusu_id'] ?? 0) < 1
                || (int) ($dagilim['islem_birimi_id'] ?? 0) < 1
                || bccomp((string) ($dagilim['girilen_miktar'] ?? '0'), '0', 8) <= 0) {
                return;
            }
        }

        static::kalemleriHesaplaFormdan($get, $set, 'olcu_dagilimlari');
    }

    private static function paraYuvarla(float $tutar): float
    {
        return round($tutar, self::PARA_BASAMAK);
    }

    /** @return array<int, string> */
    public static function kaynakIadeFaturasiSecenekleri(int $firmaId, int $cariId, string $iadeTuru): array
    {
        if ($firmaId < 1 || $cariId < 1) {
            return [];
        }
        $kaynakTuru = $iadeTuru === FaturaTuru::AlisIadesi->value ? FaturaTuru::Gelen->value : FaturaTuru::Giden->value;
        return Fatura::withoutGlobalScopes()
            ->where('firma_id', $firmaId)->where('cari_id', $cariId)
            ->where('tur', $kaynakTuru)->where('durum', FaturaDurumu::Onayli->value)
            ->whereNull('iptal_edildi_at')
            ->orderByDesc('tarih')->limit(100)->get(['id', 'fatura_no', 'belge_no', 'tarih'])
            ->mapWithKeys(fn (Fatura $fatura): array => [(int) $fatura->id => trim(($fatura->fatura_no ?: $fatura->belge_no ?: '#'.$fatura->id).' — '.optional($fatura->tarih)->format('d.m.Y'))])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public static function kaynakIadeFaturasiKalemleriniFormata(int $faturaId, int $firmaId, string $iadeTuru): array
    {
        $kaynakTuru = $iadeTuru === FaturaTuru::AlisIadesi->value ? FaturaTuru::Gelen->value : FaturaTuru::Giden->value;
        $fatura = Fatura::withoutGlobalScopes()->where('firma_id', $firmaId)->whereKey($faturaId)
            ->where('tur', $kaynakTuru)->where('durum', FaturaDurumu::Onayli->value)->whereNull('iptal_edildi_at')
            ->with(['kalemler.olcuDagilimlari'])->first();
        if (! $fatura) {
            return [];
        }

        return $fatura->kalemler->map(function ($kaynak): array {
            return [
                'kaynak_fatura_kalemi_id' => (int) $kaynak->id,
                'kalem_tipi' => $kaynak->kalem_tipi, 'hizmet_mi' => (bool) $kaynak->hizmet_mi,
                'stok_id' => $kaynak->stok_id ? (int) $kaynak->stok_id : null,
                'depo_id' => $kaynak->depo_id ? (int) $kaynak->depo_id : null,
                'parca_kodu' => $kaynak->parca_kodu, 'parca_dagilimi' => $kaynak->parca_dagilimi,
                'miktar' => (string) $kaynak->miktar, 'birim' => $kaynak->birim,
                'birim_fiyat' => (string) $kaynak->birim_fiyat, 'indirim_orani' => (string) $kaynak->indirim_orani,
                'fiyat_birimi_id' => $kaynak->fiyat_birimi_id ? (int) $kaynak->fiyat_birimi_id : null,
                'fiyat_miktari' => $kaynak->fiyat_miktari !== null ? (string) $kaynak->fiyat_miktari : null,
                'ana_miktar' => $kaynak->ana_miktar !== null ? (string) $kaynak->ana_miktar : null,
                'adet_esdegeri' => $kaynak->adet_esdegeri !== null ? (string) $kaynak->adet_esdegeri : null,
                'olcu_donusum_snapshot' => $kaynak->olcu_donusum_snapshot,
                'kdv_orani' => (string) $kaynak->kdv_orani, 'aciklama' => $kaynak->aciklama,
                'olcu_dagilimlari' => $kaynak->olcuDagilimlari->map(fn ($d): array => [
                    'stok_olcusu_id' => (int) $d->stok_olcusu_id,
                    'stok_olcu_bakiyesi_id' => $d->stok_olcu_bakiyesi_id ? (int) $d->stok_olcu_bakiyesi_id : null,
                    'depo_id' => $d->depo_id ? (int) $d->depo_id : null,
                    'stok_parcasi_id' => $d->stok_parcasi_id ? (int) $d->stok_parcasi_id : null,
                    'kaynak_olcu_dagilimi_id' => (int) $d->id, 'islem_birimi_id' => (int) $d->islem_birimi_id,
                    'girilen_miktar' => (string) $d->girilen_miktar,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /** @return array<int, string> */
    public static function kaynakSatisFaturasiSecenekleri(int $firmaId, int $cariId): array
    {
        if ($firmaId < 1 || $cariId < 1) {
            return [];
        }

        return Fatura::withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('cari_id', $cariId)
            ->where('tur', FaturaTuru::Giden->value)
            ->where('durum', FaturaDurumu::Onayli->value)
            ->whereNull('iptal_edildi_at')
            ->orderByDesc('tarih')
            ->limit(100)
            ->get(['id', 'fatura_no', 'belge_no', 'tarih'])
            ->mapWithKeys(fn (Fatura $fatura): array => [(int) $fatura->id => trim(($fatura->fatura_no ?: $fatura->belge_no ?: '#'.$fatura->id).' — '.optional($fatura->tarih)->format('d.m.Y'))])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public static function kaynakSatisFaturasiKalemleriniFormata(int $faturaId, int $firmaId): array
    {
        $fatura = Fatura::withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->whereKey($faturaId)
            ->where('cari_id', '>', 0)
            ->where('tur', FaturaTuru::Giden->value)
            ->where('durum', FaturaDurumu::Onayli->value)
            ->with(['kalemler.olcuDagilimlari'])
            ->first();
        if (! $fatura) {
            return [];
        }

        return $fatura->kalemler->map(function ($kaynak): array {
            $dagilimlar = $kaynak->olcuDagilimlari->map(fn ($d): array => [
                'stok_olcusu_id' => (int) $d->stok_olcusu_id,
                'stok_olcu_bakiyesi_id' => $d->stok_olcu_bakiyesi_id ? (int) $d->stok_olcu_bakiyesi_id : null,
                'depo_id' => $d->depo_id ? (int) $d->depo_id : null,
                'stok_parcasi_id' => $d->stok_parcasi_id ? (int) $d->stok_parcasi_id : null,
                'kaynak_olcu_dagilimi_id' => (int) $d->id,
                'islem_birimi_id' => (int) $d->islem_birimi_id,
                'girilen_miktar' => (string) $d->girilen_miktar,
            ])->values()->all();

            return [
                'kaynak_fatura_kalemi_id' => (int) $kaynak->id,
                'kalem_tipi' => $kaynak->kalem_tipi,
                'hizmet_mi' => (bool) $kaynak->hizmet_mi,
                'stok_id' => $kaynak->stok_id ? (int) $kaynak->stok_id : null,
                'depo_id' => $kaynak->depo_id ? (int) $kaynak->depo_id : null,
                'parca_kodu' => $kaynak->parca_kodu,
                'parca_dagilimi' => $kaynak->parca_dagilimi,
                'miktar' => (string) $kaynak->miktar,
                'birim' => $kaynak->birim,
                'birim_fiyat' => (string) $kaynak->birim_fiyat,
                'fiyat_birimi_id' => $kaynak->fiyat_birimi_id ? (int) $kaynak->fiyat_birimi_id : null,
                'fiyat_miktari' => $kaynak->fiyat_miktari !== null ? (string) $kaynak->fiyat_miktari : null,
                'ana_miktar' => $kaynak->ana_miktar !== null ? (string) $kaynak->ana_miktar : null,
                'adet_esdegeri' => $kaynak->adet_esdegeri !== null ? (string) $kaynak->adet_esdegeri : null,
                'olcu_donusum_snapshot' => $kaynak->olcu_donusum_snapshot,
                'indirim_orani' => (string) $kaynak->indirim_orani,
                'kdv_orani' => (string) $kaynak->kdv_orani,
                'olcu_dagilimlari' => $dagilimlar,
                'aciklama' => $kaynak->aciklama,
            ];
        })->values()->all();
    }

    public static function slugPathFromTuru(string $tur): string
    {
        return match ($tur) {
            FaturaTuru::Gelen->value, FaturaTuru::GelenFatura->value => 'gelen-faturalar',
            FaturaTuru::Giden->value, FaturaTuru::GidenFatura->value => 'giden-faturalar',
            FaturaTuru::BekleyenFatura->value => 'bekleyen-faturalar',
            FaturaTuru::IptalFatura->value => 'iptal-faturalar',
            FaturaTuru::IadeFatura->value, FaturaTuru::SatisIadesi->value => 'giden-iade-faturalari',
            FaturaTuru::AlisIadesi->value => 'gelen-iade-faturalari',
            FaturaTuru::Proforma->value, FaturaTuru::ProformaFatura->value => 'proforma-faturalar',
            FaturaTuru::Gider->value, FaturaTuru::GiderFaturasi->value => 'gider-faturalari',
            default => 'tum-faturalar',
        };
    }
}
