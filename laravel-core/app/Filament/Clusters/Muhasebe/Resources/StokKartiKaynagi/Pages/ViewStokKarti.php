<?php

namespace App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Pages\KritikStoklarSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokHareketiParcasi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokOlcuBakiyesi;
use App\Models\Muhasebe\StokParcasi;
use App\Models\Muhasebe\StokSeriNo;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\OlculuStokTakipTuru;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Muhasebe\Servisler\StokParcaDonusumServisi;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ViewStokKarti extends ViewRecord
{
    protected static string $resource = StokKartiKaynagi::class;

    protected static ?string $title = 'Stok kartı';

    protected static string $view = 'filament.clusters.muhasebe.resources.stok-karti-kaynagi.pages.view-stok-karti';

    private ?HtmlString $gorsellerHtmlCache = null;

    private ?HtmlString $stokHareketleriHtmlCache = null;

    private ?HtmlString $stokPartiHareketleriHtmlCache = null;

    private ?HtmlString $faturaKalemleriHtmlCache = null;

    private ?HtmlString $stokPartileriHtmlCache = null;

    private ?HtmlString $stokSerileriHtmlCache = null;

    private ?HtmlString $stokOlcuBakiyeleriHtmlCache = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        /** @var StokKarti $s */
        $s = $this->record;
        $s->loadMissing([
            'firma:id,ad',
            'kategori:id,ad,kod',
            'gorseller' => fn ($query) => $query
                ->select(['id', 'stok_karti_id', 'dosya_yolu', 'alt_metin', 'sira', 'kapak_mi', 'aktif_mi'])
                ->where('aktif_mi', true)
                ->orderByDesc('kapak_mi')
                ->orderBy('sira')
                ->orderBy('id')
                ->limit(8),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        /** @var StokKarti $r */
        $r = $this->record;

        return (string) ($r->ad ?: 'Stok #'.$r->getKey());
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    private function donusturulebilirAnaPartiSorgusu(StokKarti $stok): Builder
    {
        return StokParcasi::query()
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->id)
            ->where('parca_mi', false)
            ->where('kalan_miktar', '>', 0)
            ->whereDoesntHave('parcalar', fn (Builder $query): Builder => $query->where('kalan_miktar', '>', 0));
    }

    private function anaParcaSecenekleri(StokKarti $stok, string $arama = ''): array
    {
        return $this->donusturulebilirAnaPartiSorgusu($stok)
            ->when(trim($arama) !== '', fn (Builder $query): Builder => $query->where(function (Builder $inner) use ($arama): void {
                $arama = trim($arama);
                $inner->where('parca_kodu', 'like', '%'.$arama.'%')->orWhere('barkod', 'like', '%'.$arama.'%');
            }))
            ->with('depo:id,ad')
            ->latest('id')->limit(50)->get()
            ->mapWithKeys(fn (StokParcasi $parti): array => [$parti->id => $this->anaParcaEtiketi($parti)])
            ->all();
    }

    private function anaParcaEtiketi(StokParcasi|int|null $parti, ?StokKarti $stok = null): ?string
    {
        if (is_int($parti)) {
            $parti = $stok ? $this->donusturulebilirAnaPartiSorgusu($stok)->with('depo:id,ad')->find($parti) : null;
        }

        return $parti ? $parti->parca_kodu.' · Kalan: '.$parti->kalan_miktar.' · '.($parti->depo?->ad ?: 'Genel stok') : null;
    }

    private function partiDonusumFormunuDoldur(Forms\Set $set, StokKarti $stok, int $partiId, int $istenenParcaSayisi): void
    {
        $parti = $partiId > 0 ? $this->donusturulebilirAnaPartiSorgusu($stok)->find($partiId) : null;
        if (! $parti) {
            $set('parcalar', []);

            return;
        }
        $servis = app(StokParcaDonusumServisi::class);
        $parcaSayisi = max($servis->partiMinimumParcaSayisi($parti), min(5000, max(1, $istenenParcaSayisi)));
        $set('parca_sayisi', $parcaSayisi);
        $set('parcalar', $servis->partiDonusumOnerisi($parti, $parcaSayisi));
    }

    private function stokPartiSecenekleri(StokKarti $stok, string $arama = ''): array
    {
        return StokParcasi::query()->where('firma_id', $stok->firma_id)->where('stok_id', $stok->id)
            ->when(trim($arama) !== '', fn (Builder $query): Builder => $query->where(function (Builder $inner) use ($arama): void {
                $arama = trim($arama);
                $inner->where('parca_kodu', 'like', '%'.$arama.'%')->orWhere('parca_kodu', 'like', '%'.$arama.'%')
                    ->orWhere('barkod', 'like', '%'.$arama.'%')->orWhere('kalan_miktar', 'like', '%'.$arama.'%');
            }))
            ->orderBy('parca_kodu')->limit(50)->get()
            ->mapWithKeys(fn (StokParcasi $parti): array => [$parti->id => $this->stokPartiEtiketi($parti)])
            ->all();
    }

    private function stokPartiEtiketi(StokParcasi|int|null $parti, ?StokKarti $stok = null): ?string
    {
        if (is_int($parti)) {
            $parti = $stok ? StokParcasi::query()->where('firma_id', $stok->firma_id)->where('stok_id', $stok->id)->find($parti) : null;
        }

        return $parti ? ($parti->parca_kodu ?: $parti->parca_kodu).' · Kalan: '.$this->formatMiktar((string) $parti->kalan_miktar) : null;
    }

    public function getSubheading(): ?string
    {
        /** @var StokKarti $r */
        $r = $this->record;

        return 'Kod: '.($r->kod ?: '—').' · Birim: '.($r->birim ?: '—');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Kritik / negatif uyarıları')
                    ->compact()
                    ->schema([
                        TextEntry::make('_u_negatif')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Bu kartta negatif stok bayrağı açık. Sayım veya giriş hareketlerini kontrol edin.')
                            ->color('danger')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->visible(fn (StokKarti $r): bool => (bool) $r->negative_flag),
                        TextEntry::make('_u_kritik')
                            ->label('')
                            ->getStateUsing(fn (StokKarti $r): string => 'Mevcut stok ('.$this->miktarStr($r).') minimumun ('.$this->minStr($r).') altında veya eşit; tedarik / satış planını gözden geçirin.')
                            ->color('warning')
                            ->icon('heroicon-o-bell-alert')
                            ->visible(fn (StokKarti $r): bool => $this->kritikDurumdaMi($r) && ! $r->negative_flag),
                        TextEntry::make('_u_kritik_neg')
                            ->label('')
                            ->getStateUsing(fn (StokKarti $r): string => 'Negatif stok bayrağı ve kritik seviye aynı anda görünüyor; önce stok tutarlılığını giderin, ardından minimum seviyeyi değerlendirin.')
                            ->color('danger')
                            ->icon('heroicon-o-exclamation-circle')
                            ->visible(fn (StokKarti $r): bool => (bool) $r->negative_flag && $this->kritikDurumdaMi($r)),
                    ])
                    ->visible(fn (StokKarti $r): bool => (bool) $r->negative_flag || $this->kritikDurumdaMi($r))
                    ->columns(1),

                Section::make('Anlık göstergeler')
                    ->compact()
                    ->description('Fiyat satış/alış içindir; maliyet ve stok değeri ayrı alanlardır.')
                    ->schema([
                        TextEntry::make('stok_miktari')
                            ->label('Mevcut stok')
                            ->formatStateUsing(fn ($state): string => $this->formatMiktar((string) ($state ?? '0'))),
                        TextEntry::make('minimum_stok')
                            ->label('Minimum stok')
                            ->formatStateUsing(fn ($state): string => $state !== null ? $this->formatMiktar((string) $state) : '—'),
                        TextEntry::make('_kpi_kritik')
                            ->label('Kritik durum')
                            ->getStateUsing(fn (StokKarti $r): string => $this->kritikDurumdaMi($r) ? 'Evet' : 'Hayır')
                            ->badge()
                            ->color(fn (StokKarti $r): string => $this->kritikDurumdaMi($r) ? 'warning' : 'success'),
                        TextEntry::make('guncel_birim_maliyet')
                            ->label('Güncel birim maliyet')
                            ->money(fn (StokKarti $r) => $r->para_birimi ?: 'TRY'),
                        TextEntry::make('stok_degeri')
                            ->label('Stok değeri')
                            ->money(fn (StokKarti $r) => $r->para_birimi ?: 'TRY')
                            ->helperText('Miktar × maliyet yaklaşımıyla kartta tutulan değer.'),
                        TextEntry::make('son_hareket_tarihi')
                            ->label('Son stok hareket tarihi')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3,
                    ]),

                Section::make('Stok detayları')
                    ->compact()
                    ->schema([
                        Section::make('Genel')
                            ->compact()
                            ->schema([
                                Section::make('Kimlik')
                                    ->compact()
                                    ->schema([
                                        TextEntry::make('firma.ad')->label('Firma'),
                                        TextEntry::make('kod')->label('Kod'),
                                        TextEntry::make('ad')->label('Ad'),
                                        TextEntry::make('kisa_ad')->label('Kısa ad'),
                                        TextEntry::make('barkod')->label('Barkod'),
                                        TextEntry::make('tur')
                                            ->label('Tür')
                                            ->formatStateUsing(fn (?StokKartiTuru $state) => $state?->etiket() ?? '—'),
                                        TextEntry::make('kategori.ad')->label('Kategori'),
                                        TextEntry::make('kategori_kodu')->label('Kategori kodu'),
                                        TextEntry::make('birim')->label('Birim'),
                                        TextEntry::make('durum')
                                            ->label('Durum')
                                            ->formatStateUsing(fn (?HesapDurumu $state) => match ($state) {
                                                HesapDurumu::Aktif => 'Aktif',
                                                HesapDurumu::Pasif => 'Pasif',
                                                default => '—',
                                            }),
                                        TextEntry::make('stok_takip')
                                            ->label('Stok takibi')
                                            ->formatStateUsing(fn (?bool $state): string => $state ? 'Açık' : 'Kapalı')
                                            ->badge()
                                            ->color(fn (?bool $state): string => $state ? 'success' : 'gray'),
                                        TextEntry::make('stok_takip_tipi')
                                            ->label('Takip şekli')
                                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                                StokKarti::STOK_TAKIP_TIPI_PARTI => 'Parti / Lot',
                                                StokKarti::STOK_TAKIP_TIPI_SERI => 'Seri No Barkodu',
                                                default => 'Basit stok',
                                            })
                                            ->badge()
                                            ->color(fn (?string $state): string => match ($state) {
                                                StokKarti::STOK_TAKIP_TIPI_PARTI, StokKarti::STOK_TAKIP_TIPI_SERI => 'info',
                                                default => 'gray',
                                            }),
                                        TextEntry::make('negative_flag')
                                            ->label('Negatif stok bayrağı')
                                            ->formatStateUsing(fn (?bool $state): string => $state ? 'İşaretli' : 'Yok')
                                            ->badge()
                                            ->color(fn (?bool $state): string => $state ? 'danger' : 'success'),
                                    ])->columns(2),
                                Section::make('Görseller')
                                    ->compact()
                                    ->schema([
                                        TextEntry::make('_gorseller_html')
                                            ->label('')
                                            ->getStateUsing(fn (): int => 1)
                                            ->formatStateUsing(fn (TextEntry $c): HtmlString => $this->gorsellerHtml($c->getRecord()))
                                            ->html()
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Fiyat ve KDV')
                                    ->compact()
                                    ->description('Liste fiyatları; maliyet ayrı sekmede.')
                                    ->schema([
                                        TextEntry::make('alis_fiyati')
                                            ->label('Alış fiyatı')
                                            ->money(fn (StokKarti $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('satis_fiyati')
                                            ->label('Satış fiyatı')
                                            ->money(fn (StokKarti $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('para_birimi')->label('Para birimi'),
                                        TextEntry::make('kdv_orani')->label('KDV oranı (%)'),
                                    ])->columns(2),
                                Section::make('Stok seviyeleri')
                                    ->compact()
                                    ->schema([
                                        TextEntry::make('stok_miktari')
                                            ->label('Mevcut stok')
                                            ->formatStateUsing(fn ($state): string => $this->formatMiktar((string) ($state ?? '0'))),
                                        TextEntry::make('minimum_stok')
                                            ->label('Minimum stok')
                                            ->formatStateUsing(fn ($state): string => $state !== null ? $this->formatMiktar((string) $state) : '—'),
                                        TextEntry::make('kritik_seviye_miktar')
                                            ->label('Kritik seviye (miktar)')
                                            ->formatStateUsing(fn ($state): string => $state !== null ? $this->formatMiktar((string) $state) : '—'),
                                        TextEntry::make('_birim_m2')
                                            ->label('Birim m²')
                                            ->getStateUsing(fn (StokKarti $r): ?string => $r->birimMetrekare() !== null ? number_format($r->birimMetrekare(), 4, ',', '.') : null)
                                            ->placeholder('—')
                                            ->visible(fn (StokKarti $r): bool => $r->birimMetrekare() !== null),
                                        TextEntry::make('_toplam_m2')
                                            ->label('Toplam m²')
                                            ->getStateUsing(fn (StokKarti $r): ?string => $r->toplamMetrekare() !== null ? number_format($r->toplamMetrekare(), 4, ',', '.') : null)
                                            ->placeholder('—')
                                            ->visible(fn (StokKarti $r): bool => $r->toplamMetrekare() !== null),
                                    ])->columns(2),
                                Section::make('Parti / lot stokları')
                                    ->compact()
                                    ->description('Sadece parti takibi açık ürünlerde görünür. Satışlar son kullanma tarihi yaklaşan partiden başlar.')
                                    ->schema([
                                        TextEntry::make('_stok_parcalari')
                                            ->label('')
                                            ->getStateUsing(fn (): int => 1)
                                            ->formatStateUsing(fn (TextEntry $c): HtmlString => $this->stokPartileriTablosuHtml($c->getRecord()))
                                            ->html()
                                            ->columnSpanFull(),
                                    ])
                                    ->visible(fn (StokKarti $r): bool => false)
                                    ->columnSpanFull(),
                                Section::make('Ölçü stokları')
                                    ->compact()
                                    ->description('Ölçü bakiyeleri salt okunur gösterilir; düzeltmeler stok hareketi üzerinden yapılır.')
                                    ->schema([
                                        TextEntry::make('_stok_olcu_bakiyeleri')
                                            ->label('')
                                            ->getStateUsing(fn (): int => 1)
                                            ->formatStateUsing(fn (TextEntry $c): HtmlString => $this->stokOlcuBakiyeleriTablosuHtml($c->getRecord()))
                                            ->html()
                                            ->columnSpanFull(),
                                    ])
                                    ->visible(fn (StokKarti $r): bool => $r->olculu_takip_turu instanceof OlculuStokTakipTuru && $r->olculu_takip_turu->olculuMu() && MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::STOK_OLCU_GORUNTULE))
                                    ->columnSpanFull(),
                                Section::make('Seri No Barkodları')
                                    ->compact()
                                    ->description('Stokta bulunan seri numaraları.')
                                    ->schema([
                                        TextEntry::make('_stok_serileri')
                                            ->label('')
                                            ->getStateUsing(fn (): int => 1)
                                            ->formatStateUsing(fn (TextEntry $c): HtmlString => $this->stokSerileriTablosuHtml($c->getRecord()))
                                            ->html()
                                            ->columnSpanFull(),
                                    ])
                                    ->visible(fn (StokKarti $r): bool => (string) ($r->stok_takip_tipi ?? '') === StokKarti::STOK_TAKIP_TIPI_SERI)
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Hareketler')
                            ->compact()
                            ->schema([
                                Section::make('Parti hareket geçmişi')
                                    ->compact()
                                    ->description('Parti bazında son giriş ve çıkış hareketleri.')
                                    ->schema([
                                        TextEntry::make('_stok_parca_hareketleri')
                                            ->label('')
                                            ->getStateUsing(fn (): int => 1)
                                            ->formatStateUsing(fn (TextEntry $c): HtmlString => $this->stokPartiHareketleriTablosuHtml($c->getRecord()))
                                            ->html()
                                            ->columnSpanFull(),
                                    ])
                                    ->visible(fn (StokKarti $r): bool => false)
                                    ->columnSpanFull(),
                                TextEntry::make('_stok_hareket')
                                    ->label('')
                                    ->getStateUsing(fn (): int => 1)
                                    ->formatStateUsing(fn (TextEntry $c): HtmlString => $this->stokHareketleriTablosuHtml($c->getRecord()))
                                    ->html()
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Maliyet / değer')
                            ->compact()
                            ->schema([
                                Section::make('Maliyet ve değerleme')
                                    ->compact()
                                    ->description('Buradaki alanlar maliyet muhasebesi içindir; satır fiyatları “Fiyat ve KDV” bölümündedir.')
                                    ->schema([
                                        TextEntry::make('guncel_birim_maliyet')
                                            ->label('Güncel birim maliyet')
                                            ->money(fn (StokKarti $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('son_giris_maliyeti')
                                            ->label('Son giriş maliyeti')
                                            ->money(fn (StokKarti $r) => $r->para_birimi ?: 'TRY')
                                            ->placeholder('—'),
                                        TextEntry::make('stok_degeri')
                                            ->label('Stok değeri')
                                            ->money(fn (StokKarti $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('son_hareket_tarihi')
                                            ->label('Son hareket')
                                            ->dateTime('d.m.Y H:i')
                                            ->placeholder('—'),
                                    ])->columns(2),
                            ]),
                        Section::make('Bağlantılar')
                            ->compact()
                            ->schema([
                                TextEntry::make('_fatura_kalem')
                                    ->label('')
                                    ->getStateUsing(fn (): int => 1)
                                    ->formatStateUsing(fn (TextEntry $c): HtmlString => $this->faturaKalemleriTablosuHtml($c->getRecord()))
                                    ->html()
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Notlar')
                            ->compact()
                            ->schema([
                                Section::make('Açıklama ve kayıt')
                                    ->compact()
                                    ->schema([
                                        TextEntry::make('aciklama')->label('Açıklama')->columnSpanFull()->placeholder('—'),
                                        TextEntry::make('created_at')->label('Oluşturulma')->dateTime('d.m.Y H:i'),
                                        TextEntry::make('updated_at')->label('Güncellenme')->dateTime('d.m.Y H:i'),
                                    ])->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var StokKarti $r */
        $r = $this->record;

        return [
            Actions\EditAction::make()->label('Düzenle'),
            Actions\Action::make('stokListesi')
                ->label('Stok listesi')
                ->icon('heroicon-o-arrow-left')
                ->url(StokKartiKaynagi::getUrl('index'))
                ->color('gray'),
            ...[
                Actions\Action::make('stokParcalariniOlustur')
                    ->label('Stoğu parçalara ayır')
                    ->icon('heroicon-o-squares-plus')
                    ->color('warning')
                    ->visible(fn (): bool => $this->partiDuzeltmeYetkisiVarMi()
                        && $r->partiTakibiAktifMi()
                        && bccomp(app(StokParcaDonusumServisi::class)->toplamMiktar($r), '0', 8) > 0
                        && ! StokParcasi::query()->where('firma_id', $r->firma_id)->where('stok_id', $r->id)->where('kalan_miktar', '>', 0)->exists())
                    ->modalHeading('Stoğu fiziksel stok parçalarına ayır')
                    ->modalDescription('Sistem eşit dağılım önerir. Kayıt tamamlanmadan önce miktarları kontrol edip onaylayın.')
                    ->form([
                        Forms\Components\TextInput::make('parca_sayisi')
                            ->label('Parça sayısı')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5000)
                            ->default(fn (): int => app(StokParcaDonusumServisi::class)->minimumParcaSayisi($r))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) use ($r): void {
                                $servis = app(StokParcaDonusumServisi::class);
                                $adet = max($servis->minimumParcaSayisi($r), min(5000, max(1, (int) $state)));
                                $set('parca_sayisi', $adet);
                                $set('parcalar', $servis->donusumOnerisi($r, $adet));
                            }),
                        Forms\Components\Repeater::make('parcalar')
                            ->label('Önerilen stok parçaları')
                            ->schema([
                                Forms\Components\Hidden::make('stok_olcu_bakiyesi_id'),
                                Forms\Components\Hidden::make('stok_olcusu_id'),
                                Forms\Components\Hidden::make('takip_turu'),
                                Forms\Components\Hidden::make('olcu_birimi'),
                                Forms\Components\Hidden::make('agirlik_birimi'),
                                Forms\Components\TextInput::make('olcu_kaynagi')
                                    ->label('Ölçü kaynağı')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('parca_kodu')->label('Parça kodu')->placeholder('Otomatik üretilir')->maxLength(128),
                                Forms\Components\TextInput::make('ana_miktar')->label('Ana miktar')->numeric()->minValue(0)->required(),
                                Forms\Components\TextInput::make('maliyet')->label('m² maliyeti')->numeric()->minValue(0),
                                Forms\Components\TextInput::make('en')->label('En')->numeric()->minValue(0)
                                    ->visible(fn (Forms\Get $get): bool => in_array((string) $get('takip_turu'), ['alan', 'hacim'], true)),
                                Forms\Components\TextInput::make('boy')->label('Boy')->numeric()->minValue(0)
                                    ->visible(fn (Forms\Get $get): bool => in_array((string) $get('takip_turu'), ['uzunluk', 'alan', 'hacim'], true)),
                                Forms\Components\TextInput::make('yukseklik')->label('Kalınlık / yükseklik')->numeric()->minValue(0)
                                    ->visible(fn (Forms\Get $get): bool => (string) $get('takip_turu') === 'hacim'),
                                Forms\Components\TextInput::make('bir_adet_agirlik')->label('Bir adet ağırlığı')->numeric()->minValue(0)
                                    ->visible(fn (Forms\Get $get): bool => (string) $get('takip_turu') === 'agirlik'),
                                Forms\Components\TextInput::make('renk_desen')->label('Renk / desen')->maxLength(191),
                                Forms\Components\TextInput::make('kalite_sinifi')->label('Kalite sınıfı')->maxLength(64),
                            ])
                            ->columns(4)
                            ->default(fn (): array => app(StokParcaDonusumServisi::class)->donusumOnerisi(
                                $r,
                                app(StokParcaDonusumServisi::class)->minimumParcaSayisi($r),
                            ))
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->columnSpanFull(),
                        Forms\Components\Checkbox::make('onay')
                            ->label('Dağılımı kontrol ettim ve onaylıyorum.')
                            ->accepted()
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) use ($r): void {
                        $this->partiDuzeltmeYetkisiniDogrula();
                        try {
                            app(StokParcaDonusumServisi::class)->donustur($r, array_values((array) ($data['parcalar'] ?? [])));
                            Notification::make()->title('Stok parçaları oluşturuldu')->success()->send();
                        } catch (IsKuraliIstisnasi $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                Actions\Action::make('anaParcayiParcalaraAyir')
                    ->label('Ana partiyi parçalara ayır')
                    ->icon('heroicon-o-rectangle-group')
                    ->color('warning')
                    ->visible(fn (): bool => false)
                    ->modalHeading('Mevcut ana partiyi stok parçalarına ayır')
                    ->modalDescription('Yalnız seçilen partinin kalan bakiyesi dönüştürülür. Önceki satış hareketleri korunur; parti bilgileri yeni parçalara aktarılır.')
                    ->form([
                        Forms\Components\Select::make('ana_parca_id')
                            ->label('Ana parti')
                            ->getSearchResultsUsing(fn (string $search): array => $this->anaParcaSecenekleri($r, $search))
                            ->getOptionLabelUsing(fn ($value): ?string => $this->anaParcaEtiketi((int) $value, $r))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) use ($r): void {
                                $this->partiDonusumFormunuDoldur($set, $r, (int) $state, (int) ($get('parca_sayisi') ?: 1));
                            })
                            ->required(),
                        Forms\Components\TextInput::make('parca_sayisi')
                            ->label('Parça sayısı')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5000)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) use ($r): void {
                                $this->partiDonusumFormunuDoldur($set, $r, (int) ($get('ana_parca_id') ?? 0), (int) $state);
                            }),
                        Forms\Components\Repeater::make('parcalar')
                            ->label('Önerilen stok parçaları')
                            ->schema([
                                Forms\Components\Hidden::make('stok_olcu_bakiyesi_id'),
                                Forms\Components\Hidden::make('stok_olcusu_id'),
                                Forms\Components\Hidden::make('takip_turu'),
                                Forms\Components\Hidden::make('olcu_birimi'),
                                Forms\Components\Hidden::make('agirlik_birimi'),
                                Forms\Components\TextInput::make('olcu_kaynagi')->label('Ölçü kaynağı')->disabled()->dehydrated(false),
                                Forms\Components\TextInput::make('parca_kodu')->label('Parça kodu')->maxLength(128),
                                Forms\Components\TextInput::make('ana_miktar')->label('Ana miktar')->numeric()->minValue(0)->required(),
                                Forms\Components\TextInput::make('maliyet')->label('m² maliyeti')->numeric()->minValue(0),
                                Forms\Components\TextInput::make('en')->label('En')->numeric()->minValue(0)
                                    ->visible(fn (Forms\Get $get): bool => in_array((string) $get('takip_turu'), ['alan', 'hacim'], true)),
                                Forms\Components\TextInput::make('boy')->label('Boy')->numeric()->minValue(0)
                                    ->visible(fn (Forms\Get $get): bool => in_array((string) $get('takip_turu'), ['uzunluk', 'alan', 'hacim'], true)),
                                Forms\Components\TextInput::make('yukseklik')->label('Kalınlık / yükseklik')->numeric()->minValue(0)
                                    ->visible(fn (Forms\Get $get): bool => (string) $get('takip_turu') === 'hacim'),
                                Forms\Components\TextInput::make('bir_adet_agirlik')->label('Bir adet ağırlığı')->numeric()->minValue(0)
                                    ->visible(fn (Forms\Get $get): bool => (string) $get('takip_turu') === 'agirlik'),
                                Forms\Components\TextInput::make('renk_desen')->label('Renk / desen')->maxLength(191),
                                Forms\Components\TextInput::make('kalite_sinifi')->label('Kalite sınıfı')->maxLength(64),
                            ])
                            ->columns(4)
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->default([])
                            ->columnSpanFull(),
                        Forms\Components\Checkbox::make('onay')
                            ->label('Partinin kalan dağılımını kontrol ettim ve onaylıyorum.')
                            ->accepted()
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) use ($r): void {
                        $this->partiDuzeltmeYetkisiniDogrula();
                        try {
                            $parti = $this->donusturulebilirAnaPartiSorgusu($r)->findOrFail((int) $data['ana_parca_id']);
                            app(StokParcaDonusumServisi::class)->partiyiDonustur($parti, array_values((array) ($data['parcalar'] ?? [])));
                            Notification::make()->title('Ana parti stok parçalarına dönüştürüldü')->success()->send();
                        } catch (IsKuraliIstisnasi $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                Actions\Action::make('partiSayimDuzeltmesi')
                    ->label('Parti sayımı düzelt')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('gray')
                    ->visible(fn (): bool => $this->partiDuzeltmeYetkisiVarMi() && $r->partiTakibiAktifMi() && StokParcasi::query()->where('firma_id', $r->firma_id)->where('stok_id', $r->id)->exists())
                    ->modalHeading('Parti sayımını düzelt')
                    ->modalDescription('Saydığınız gerçek miktarı girin. Sistem yalnızca fark kadar stok düzeltmesi oluşturur.')
                    ->form([
                        Forms\Components\Select::make('stok_parcasi_id')
                            ->label('Parti / Lot')
                            ->getSearchResultsUsing(fn (string $search): array => $this->stokPartiSecenekleri($r, $search))
                            ->getOptionLabelUsing(fn ($value): ?string => $this->stokPartiEtiketi((int) $value, $r))
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('hedef_miktar')
                            ->label('Sayım miktarı')
                            ->numeric()
                            ->required()
                            ->helperText('Seçtiğiniz partide saydığınız gerçek miktarı yazın.'),
                    ])
                    ->action(function (array $data) use ($r): void {
                        $this->partiDuzeltmeYetkisiniDogrula();
                        try {
                            $parti = StokParcasi::query()->where('firma_id', $r->firma_id)->where('stok_id', $r->id)->findOrFail((int) $data['stok_parcasi_id']);
                            app(StokHareketServisi::class)->partiMiktariniDuzelt((int) $r->firma_id, $parti, $data['hedef_miktar']);
                            Notification::make()->title('Parti sayımı güncellendi')->success()->send();
                        } catch (IsKuraliIstisnasi $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                Actions\Action::make('mevcutStoguPartiyeAktar')
                    ->label('Mevcut stoğu partiye bağla')
                    ->icon('heroicon-o-tag')
                    ->color('warning')
                    ->visible(fn (): bool => $this->partiDuzeltmeYetkisiVarMi() && $r->partiTakibiAktifMi()
                        && bccomp((string) ($r->stok_miktari ?? 0), '0', 4) > 0
                        && ! StokParcasi::query()->where('firma_id', $r->firma_id)->where('stok_id', $r->id)->where('kalan_miktar', '>', 0)->exists())
                    ->modalHeading('Mevcut stoğu partiye bağla')
                    ->modalDescription('Mevcut stok miktarı değişmez. Sadece bu stok için parti kaydı oluşturulur.')
                    ->form([
                        Forms\Components\TextInput::make('parca_kodu')->label('Parti / Lot No')->required()->maxLength(128)->helperText('Bu ürün ve depo için daha önce kullanılmamış bir numara girin.'),
                        Forms\Components\TextInput::make('miktar')->label('Miktar')->numeric()->required()->default(fn (): string => (string) $r->stok_miktari),
                        Forms\Components\TextInput::make('birim_maliyet')->label('Birim maliyet')->numeric()->default(fn (): string => (string) ($r->guncel_birim_maliyet ?? 0)),
                        Forms\Components\Select::make('depo_id')
                            ->label('Depo')
                            ->options(fn (): array => [0 => 'Genel stok'] + Depo::query()->where('firma_id', $r->firma_id)->aktif()->orderBy('ad')->pluck('ad', 'id')->all())
                            ->default((int) ($r->depo_id ?? 0)),
                        Forms\Components\DatePicker::make('uretim_tarihi')->label('Üretim tarihi'),
                        Forms\Components\DatePicker::make('son_kullanma_tarihi')->label('Son kullanma tarihi'),
                        Forms\Components\TextInput::make('blok_no')->label('Blok no')->maxLength(128),
                        Forms\Components\TextInput::make('ocak_tedarikci')->label('Ocak / tedarikçi')->maxLength(191),
                        Forms\Components\TextInput::make('kalite_sinifi')->label('Kalite sınıfı')->maxLength(64),
                        Forms\Components\TextInput::make('renk_desen')->label('Renk / desen')->maxLength(191),
                        Forms\Components\TextInput::make('metrekare')->label('Toplam m²')->numeric()->minValue(0),
                        Forms\Components\TextInput::make('plaka_no')->label('Plaka no')->maxLength(128),
                        Forms\Components\TextInput::make('parca_no')->label('Parça no')->maxLength(128),
                    ])
                    ->action(function (array $data) use ($r): void {
                        $this->partiDuzeltmeYetkisiniDogrula();
                        try {
                            app(StokHareketServisi::class)->mevcutStoguPartiyeAktar((int) $r->firma_id, $r, $data);
                            Notification::make()->title('Mevcut stok partiye bağlandı')->success()->send();
                        } catch (IsKuraliIstisnasi $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                Actions\Action::make('kritikStoklar')
                    ->label('Kritik stoklar')
                    ->icon('heroicon-o-bell-alert')
                    ->url(KritikStoklarSayfasi::getUrl())
                    ->visible(fn (): bool => $this->kritikDurumdaMi($r) && KritikStoklarSayfasi::canAccess())
                    ->color('warning'),
            ],
        ];
    }

    private function kritikDurumdaMi(StokKarti $s): bool
    {
        if (! $s->stok_takip || $s->durum !== HesapDurumu::Aktif) {
            return false;
        }
        if ($s->minimum_stok === null) {
            return false;
        }

        return bccomp((string) ($s->stok_miktari ?? '0'), (string) $s->minimum_stok, 4) <= 0;
    }

    private function partiDuzeltmeYetkisiVarMi(): bool
    {
        return MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::STOK_PARTI_DUZELT);
    }

    private function partiDuzeltmeYetkisiniDogrula(): void
    {
        abort_unless($this->partiDuzeltmeYetkisiVarMi(), 403, 'Stok parti düzenleme yetkiniz bulunmuyor.');
    }

    private function formatMiktar(string $v): string
    {
        return str_replace('.', ',', rtrim(rtrim(bcadd($v, '0', 4), '0'), '.'));
    }

    private function gorsellerHtml(StokKarti $stok): HtmlString
    {
        if ($this->gorsellerHtmlCache !== null) {
            return $this->gorsellerHtmlCache;
        }

        $images = collect($stok->galeriGorseliKayitlari());

        if ($images->isEmpty()) {
            return $this->gorsellerHtmlCache = new HtmlString('<div class="text-sm text-gray-500">Görsel yok.</div>');
        }

        $cards = $images->map(function ($image): string {
            $url = $image->url ?: Storage::disk('public')->url((string) $image->dosya_yolu);
            $alt = e((string) ($image->alt_metin ?: 'Görsel'));
            $cover = $image->kapak_mi
                ? '<div class="mt-2 text-xs font-semibold text-primary-600">Kapak Görsel</div>'
                : '';

            return '<div style="width:140px">'
                .'<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">'
                .'<img src="'.e($url).'" alt="'.$alt.'" style="width:140px;height:140px;object-fit:cover;border-radius:12px;border:1px solid #e5e7eb;">'
                .'</a>'
                .$cover
                .'<div class="mt-1 text-xs text-gray-500">'.$alt.'</div>'
                .'</div>';
        })->implode('');

        return $this->gorsellerHtmlCache = new HtmlString('<div style="display:flex;gap:12px;flex-wrap:wrap;">'.$cards.'</div>');
    }

    private function miktarStr(StokKarti $r): string
    {
        return $this->formatMiktar((string) ($r->stok_miktari ?? '0'));
    }

    private function stokPartileriTablosuHtml(StokKarti $stok): HtmlString
    {
        if ($this->stokPartileriHtmlCache !== null) {
            return $this->stokPartileriHtmlCache;
        }

        $partiler = StokParcasi::query()
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->id)
            ->where('kalan_miktar', '>', 0)
            ->with('depo:id,ad')
            ->orderByRaw('son_kullanma_tarihi IS NULL')
            ->orderBy('son_kullanma_tarihi')
            ->orderBy('id')
            ->limit(50)
            ->get();

        if ($partiler->isEmpty()) {
            return $this->stokPartileriHtmlCache = new HtmlString('<div class="text-sm text-gray-500">Henüz parti kaydı yok. Alış veya açılış stokunda parti numarası girebilirsiniz.</div>');
        }

        $rows = $partiler->map(fn (StokParcasi $parti): string => sprintf(
            '<tr class="border-b border-gray-100 dark:border-white/10"><td class="px-3 py-2 text-sm">%s</td><td class="px-3 py-2 text-sm">%s</td><td class="px-3 py-2 text-sm text-end">%s</td><td class="px-3 py-2 text-sm">%s</td></tr>',
            e($parti->parca_kodu),
            e($parti->depo?->ad ?? 'Genel stok'),
            e($this->formatMiktar((string) $parti->kalan_miktar)),
            e($parti->son_kullanma_tarihi?->format('d.m.Y') ?? '—')
        ))->implode('');

        return $this->stokPartileriHtmlCache = new HtmlString(
            '<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm"><thead><tr class="bg-gray-50 dark:bg-white/5 text-start"><th class="px-3 py-2 font-medium">Parti / Lot</th><th class="px-3 py-2 font-medium">Depo</th><th class="px-3 py-2 font-medium text-end">Kalan</th><th class="px-3 py-2 font-medium">Son kullanma</th></tr></thead><tbody>'.$rows.'</tbody></table></div>'
        );
    }

    private function stokSerileriTablosuHtml(StokKarti $stok): HtmlString
    {
        if ($this->stokSerileriHtmlCache !== null) {
            return $this->stokSerileriHtmlCache;
        }

        $seriler = StokSeriNo::query()
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->id)
            ->where('durum', 'stokta')
            ->with('depo:id,ad')
            ->orderBy('seri_no')
            ->limit(200)
            ->get();
        if ($seriler->isEmpty()) {
            return $this->stokSerileriHtmlCache = new HtmlString('<div class="text-sm text-gray-500">Henüz stokta Seri No Barkodu yok.</div>');
        }

        $rows = $seriler->map(fn (StokSeriNo $seri): string => sprintf(
            '<tr class="border-b border-gray-100 dark:border-white/10"><td class="px-3 py-2 text-sm font-medium"><div>%s</div><div class="text-xs text-gray-500">Seri No Barkodu: %s</div></td><td class="px-3 py-2 text-sm">%s</td><td class="px-3 py-2 text-sm">%s</td></tr>',
            e($seri->seri_no), e($seri->barkod ?: '—'), e($seri->depo?->ad ?? 'Genel stok'), e($seri->garanti_bitis_tarihi?->format('d.m.Y') ?? '—')
        ))->implode('');

        return $this->stokSerileriHtmlCache = new HtmlString('<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm"><thead><tr class="bg-gray-50 dark:bg-white/5 text-start"><th class="px-3 py-2 font-medium">Seri No Barkodu</th><th class="px-3 py-2 font-medium">Depo</th><th class="px-3 py-2 font-medium">Garanti bitişi</th></tr></thead><tbody>'.$rows.'</tbody></table></div>');
    }

    private function stokOlcuBakiyeleriTablosuHtml(StokKarti $stok): HtmlString
    {
        if ($this->stokOlcuBakiyeleriHtmlCache !== null) {
            return $this->stokOlcuBakiyeleriHtmlCache;
        }
        $bakiyeler = StokOlcuBakiyesi::query()
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->id)
            ->with(['olcu:id,kod,ad,aktif_mi', 'depo:id,ad', 'parti:id,parca_kodu'])
            ->orderBy('id')
            ->limit(200)
            ->get();
        if ($bakiyeler->isEmpty()) {
            return $this->stokOlcuBakiyeleriHtmlCache = new HtmlString('<div class="text-sm text-gray-500">Henüz ölçü bakiyesi oluşmadı.</div>');
        }
        $toplamAna = '0';
        $toplamAdet = '0';
        $rows = $bakiyeler->map(function (StokOlcuBakiyesi $b) use (&$toplamAna, &$toplamAdet): string {
            $toplamAna = bcadd($toplamAna, (string) $b->ana_miktar, 8);
            $toplamAdet = bcadd($toplamAdet, (string) $b->adet_esdegeri, 8);

            return sprintf(
                '<tr class="border-b border-gray-100 dark:border-white/10"><td class="px-3 py-2 text-sm">%s</td><td class="px-3 py-2 text-sm">%s</td><td class="px-3 py-2 text-sm">%s</td><td class="px-3 py-2 text-sm text-end">%s</td><td class="px-3 py-2 text-sm text-end">%s</td><td class="px-3 py-2 text-sm text-end">%s</td><td class="px-3 py-2 text-sm text-end">%s</td><td class="px-3 py-2 text-sm text-end">%s</td></tr>',
                e($b->olcu?->ad ?: $b->olcu?->kod ?: '—'), e($b->depo?->ad ?: 'Genel stok'), e($b->parti?->parca_kodu ?: '—'),
                e($this->formatMiktar((string) $b->ana_miktar)), e($this->formatMiktar((string) $b->adet_esdegeri)),
                e($this->formatMiktar((string) $b->rezerve_ana_miktar)), e($this->formatMiktar(bcsub((string) $b->ana_miktar, (string) $b->rezerve_ana_miktar, 8))), e((string) $b->durum)
            );
        })->implode('');
        $ozet = '<tr class="font-semibold bg-gray-50 dark:bg-white/5"><td class="px-3 py-2" colspan="3">Toplam</td><td class="px-3 py-2 text-end">'.$this->formatMiktar($toplamAna).'</td><td class="px-3 py-2 text-end">'.$this->formatMiktar($toplamAdet).'</td><td class="px-3 py-2" colspan="3">—</td></tr>';

        return $this->stokOlcuBakiyeleriHtmlCache = new HtmlString('<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm"><thead><tr class="bg-gray-50 dark:bg-white/5 text-start"><th class="px-3 py-2 font-medium">Ölçü</th><th class="px-3 py-2 font-medium">Depo</th><th class="px-3 py-2 font-medium">Parti / lot</th><th class="px-3 py-2 font-medium text-end">Ana miktar</th><th class="px-3 py-2 font-medium text-end">Adet eşdeğeri</th><th class="px-3 py-2 font-medium text-end">Rezerv</th><th class="px-3 py-2 font-medium text-end">Kullanılabilir</th><th class="px-3 py-2 font-medium">Durum</th></tr></thead><tbody>'.$rows.$ozet.'</tbody></table></div>');
    }

    private function minStr(StokKarti $r): string
    {
        return $r->minimum_stok !== null ? $this->formatMiktar((string) $r->minimum_stok) : '—';
    }

    private function stokHareketleriTablosuHtml(StokKarti $stok): HtmlString
    {
        if ($this->stokHareketleriHtmlCache !== null) {
            return $this->stokHareketleriHtmlCache;
        }

        $hareketler = StokHareketi::query()
            ->select(['id', 'firma_id', 'stok_id', 'tarih', 'islem_turu', 'miktar', 'birim_maliyet', 'belge_turu', 'belge_id'])
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->id)
            ->where('durum', StokHareketDurumu::Aktif)
            ->orderByDesc('tarih')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $rows = '';
        foreach ($hareketler as $h) {
            $tur = $h->islem_turu instanceof StokHareketIslemTuru ? $h->islem_turu->value : (string) $h->islem_turu;
            $belge = '—';
            if ($h->belge_turu === StokBelgeTuru::Fatura && $h->belge_id) {
                $url = FaturaKaynagi::getUrl('view', ['record' => (int) $h->belge_id]);
                $belge = '<a href="'.e($url).'" class="text-primary-600 hover:underline">Fatura #'.e((string) $h->belge_id).'</a>';
            } else {
                $bt = $h->belge_turu instanceof StokBelgeTuru ? $h->belge_turu->value : (string) $h->belge_turu;
                $belge = e($bt.($h->belge_id ? ' #'.$h->belge_id : ''));
            }
            $rows .= sprintf(
                '<tr class="border-b border-gray-100 dark:border-white/10">
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm text-end font-medium">%s</td>
                    <td class="px-3 py-2 text-sm text-end text-gray-600 dark:text-gray-400">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                </tr>',
                e(optional($h->tarih)->format('d.m.Y H:i') ?? '—'),
                e($tur),
                e($this->formatMiktar((string) ($h->miktar ?? '0'))),
                e(number_format((float) ($h->birim_maliyet ?? 0), 2, ',', '.')),
                $belge
            );
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="px-3 py-4 text-sm text-gray-500">Kayıt yok.</td></tr>';
        }
        $html = '<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm">
            <thead><tr class="bg-gray-50 dark:bg-white/5 text-start">
                <th class="px-3 py-2 font-medium">Tarih</th>
                <th class="px-3 py-2 font-medium">İşlem</th>
                <th class="px-3 py-2 font-medium text-end">Miktar</th>
                <th class="px-3 py-2 font-medium text-end">Birim maliyet</th>
                <th class="px-3 py-2 font-medium">Belge</th>
            </tr></thead><tbody>'.$rows.'</tbody></table></div>';
        $html .= '<p class="mt-2 text-xs text-gray-500">Son 12 hareket. Negatif ve kritik durumlar üst bölümde renkli uyarı ile ayrılır.</p>';

        return $this->stokHareketleriHtmlCache = new HtmlString($html);
    }

    private function stokPartiHareketleriTablosuHtml(StokKarti $stok): HtmlString
    {
        if ($this->stokPartiHareketleriHtmlCache !== null) {
            return $this->stokPartiHareketleriHtmlCache;
        }

        $hareketler = StokHareketiParcasi::query()
            ->where('firma_id', $stok->firma_id)
            ->whereHas('stokParcasi', fn ($query) => $query->where('stok_id', $stok->id))
            ->with(['stokParcasi:id,parca_kodu', 'stokHareketi:id,tarih,islem_turu,belge_turu,belge_id'])
            ->latest('id')
            ->limit(30)
            ->get();

        if ($hareketler->isEmpty()) {
            return $this->stokPartiHareketleriHtmlCache = new HtmlString('<div class="text-sm text-gray-500">Henüz parti hareketi yok.</div>');
        }

        $rows = $hareketler->map(function (StokHareketiParcasi $hareket): string {
            $islem = $hareket->stokHareketi?->islem_turu;
            $giris = in_array($islem, [StokHareketIslemTuru::Alis, StokHareketIslemTuru::Acilis, StokHareketIslemTuru::SatisIadesi, StokHareketIslemTuru::TransferGiris], true);
            $belgeTuru = $hareket->stokHareketi?->belge_turu;
            $belge = $belgeTuru instanceof StokBelgeTuru ? $belgeTuru->value : (string) $belgeTuru;
            if ($belgeTuru === StokBelgeTuru::Fatura && $hareket->stokHareketi?->belge_id) {
                $belge = '<a href="'.e(FaturaKaynagi::getUrl('view', ['record' => (int) $hareket->stokHareketi->belge_id])).'" class="text-primary-600 hover:underline">Fatura #'.e((string) $hareket->stokHareketi->belge_id).'</a>';
            } elseif ($hareket->stokHareketi?->belge_id) {
                $belge .= ' #'.(int) $hareket->stokHareketi->belge_id;
            }

            return sprintf(
                '<tr class="border-b border-gray-100 dark:border-white/10"><td class="px-3 py-2 text-sm">%s</td><td class="px-3 py-2 text-sm font-medium">%s</td><td class="px-3 py-2 text-sm">%s</td><td class="px-3 py-2 text-sm text-end">%s</td><td class="px-3 py-2 text-sm">%s</td></tr>',
                e(optional($hareket->stokHareketi?->tarih)->format('d.m.Y H:i') ?? '—'),
                e($hareket->stokParcasi?->parca_kodu ?? '—'),
                $giris ? '<span class="text-success-600">Giriş</span>' : '<span class="text-danger-600">Çıkış</span>',
                e($this->formatMiktar((string) $hareket->miktar)),
                $belge
            );
        })->implode('');

        return $this->stokPartiHareketleriHtmlCache = new HtmlString('<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm"><thead><tr class="bg-gray-50 dark:bg-white/5 text-start"><th class="px-3 py-2 font-medium">Tarih</th><th class="px-3 py-2 font-medium">Parti / Lot</th><th class="px-3 py-2 font-medium">Yön</th><th class="px-3 py-2 font-medium text-end">Miktar</th><th class="px-3 py-2 font-medium">Belge</th></tr></thead><tbody>'.$rows.'</tbody></table></div>');
    }

    private function faturaKalemleriTablosuHtml(StokKarti $stok): HtmlString
    {
        if ($this->faturaKalemleriHtmlCache !== null) {
            return $this->faturaKalemleriHtmlCache;
        }

        $kalemler = FaturaKalemi::query()
            ->select(['id', 'firma_id', 'fatura_id', 'stok_id', 'miktar', 'birim_fiyat'])
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->id)
            ->with(['fatura:id,fatura_no,tarih,para_birimi'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $rows = '';
        foreach ($kalemler as $k) {
            $f = $k->fatura;
            if (! $f) {
                continue;
            }
            $pb = strtoupper((string) ($f->para_birimi ?: 'TRY'));
            $url = e(FaturaKaynagi::getUrl('view', ['record' => $f->id]));
            $fno = e($f->fatura_no ?: '#'.$f->id);
            $rows .= sprintf(
                '<tr class="border-b border-gray-100 dark:border-white/10">
                    <td class="px-3 py-2 text-sm"><a href="%s" class="text-primary-600 hover:underline font-medium">%s</a></td>
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                </tr>',
                $url,
                $fno,
                e(optional($f->tarih)->format('d.m.Y') ?? '—'),
                e($this->formatMiktar((string) ($k->miktar ?? '0'))),
                e(number_format((float) ($k->birim_fiyat ?? 0), 2, ',', '.').' '.$pb)
            );
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="px-3 py-4 text-sm text-gray-500">Bu stoka bağlı fatura kalemi yok.</td></tr>';
        }
        $html = '<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm">
            <thead><tr class="bg-gray-50 dark:bg-white/5 text-start">
                <th class="px-3 py-2 font-medium">Fatura</th>
                <th class="px-3 py-2 font-medium">Tarih</th>
                <th class="px-3 py-2 font-medium text-end">Miktar</th>
                <th class="px-3 py-2 font-medium text-end">Birim fiyat</th>
            </tr></thead><tbody>'.$rows.'</tbody></table></div>';
        $html .= '<p class="mt-2 text-xs text-gray-500">Son 10 kalem satırı; fiyat kolonu satış/liste fiyatıdır, maliyet değildir.</p>';

        return $this->faturaKalemleriHtmlCache = new HtmlString($html);
    }
}
