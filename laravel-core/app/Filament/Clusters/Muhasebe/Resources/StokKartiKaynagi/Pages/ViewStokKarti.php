<?php

namespace App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Pages\KritikStoklarSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokOlcuBakiyesi;
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
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ViewStokKarti extends ViewRecord
{
    protected static string $resource = StokKartiKaynagi::class;

    protected static ?string $title = 'Stok kartı';

    protected static string $view = 'filament.clusters.muhasebe.resources.stok-karti-kaynagi.pages.view-stok-karti';

    private ?HtmlString $gorsellerHtmlCache = null;

    private ?HtmlString $stokHareketleriHtmlCache = null;

    private ?HtmlString $faturaKalemleriHtmlCache = null;

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
                                                StokKarti::STOK_TAKIP_TIPI_SERI => 'Seri No Barkodu',
                                                default => 'Basit stok',
                                            })
                                            ->badge()
                                            ->color(fn (?string $state): string => match ($state) {
                                                StokKarti::STOK_TAKIP_TIPI_SERI => 'info',
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
                                        TextEntry::make('_fiyat_html')
                                            ->label('')
                                            ->getStateUsing(fn (): int => 1)
                                            ->formatStateUsing(fn (TextEntry $c): HtmlString => $this->fiyatBilgisiHtml($c->getRecord()))
                                            ->html()
                                            ->columnSpanFull(),
                                    ])->columns(1),
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
                                            ->formatStateUsing(fn ($state, StokKarti $r): string => $this->fiyatStr($state, $r)),
                                        TextEntry::make('son_giris_maliyeti')
                                            ->label('Son giriş maliyeti')
                                            ->formatStateUsing(fn ($state, StokKarti $r): string => $this->fiyatStr($state, $r))
                                            ->placeholder('—'),
                                        TextEntry::make('stok_degeri')
                                            ->label('Stok değeri')
                                            ->formatStateUsing(fn ($state, StokKarti $r): string => $this->fiyatStr($state, $r)),
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
            Actions\Action::make('kritikStoklar')
                ->label('Kritik stoklar')
                ->icon('heroicon-o-bell-alert')
                ->url(KritikStoklarSayfasi::getUrl())
                ->visible(fn (): bool => $this->kritikDurumdaMi($r) && KritikStoklarSayfasi::canAccess())
                ->color('warning'),
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

    private function formatMiktar(string $v): string
    {
        return str_replace('.', ',', rtrim(rtrim(bcadd($v, '0', 4), '0'), '.'));
    }

    private function fiyatStr(mixed $state, StokKarti $r): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        $paraBirimi = strtoupper((string) ($r->para_birimi ?: 'TRY'));
        $sembol = match ($paraBirimi) {
            'TRY' => '₺',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $paraBirimi,
        };

        return $sembol.number_format((float) $state, 2, ',', '.');
    }

    private function fiyatBilgisiHtml(StokKarti $stok): HtmlString
    {
        $paraBirimi = strtoupper((string) ($stok->para_birimi ?: 'TRY'));
        $satir = static fn (string $etiket, string $deger): string => '<div class="flex items-center justify-between gap-4 border-b border-gray-100 py-2 last:border-b-0 dark:border-white/10"><span class="text-sm text-gray-500">'.e($etiket).'</span><span class="text-sm font-medium">'.e($deger).'</span></div>';

        return new HtmlString('<div class="grid gap-1 sm:grid-cols-2">'
            .$satir('Alış fiyatı', $this->fiyatStr($stok->alis_fiyati, $stok))
            .$satir('Satış fiyatı', $this->fiyatStr($stok->satis_fiyati, $stok))
            .$satir('Para birimi', $paraBirimi)
            .$satir('KDV oranı (%)', $stok->kdv_orani !== null ? (string) $stok->kdv_orani : '—')
            .'</div>');
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
            ->with(['olcu:id,kod,ad,aktif_mi', 'depo:id,ad'])
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
                '<tr class="border-b border-gray-100 dark:border-white/10"><td class="px-3 py-2 text-sm">%s</td><td class="px-3 py-2 text-sm">%s</td><td class="px-3 py-2 text-sm text-end">%s</td><td class="px-3 py-2 text-sm text-end">%s</td><td class="px-3 py-2 text-sm text-end">%s</td><td class="px-3 py-2 text-sm text-end">%s</td><td class="px-3 py-2 text-sm">%s</td></tr>',
                e($b->olcu?->ad ?: $b->olcu?->kod ?: '—'), e($b->depo?->ad ?: 'Genel stok'),
                e($this->formatMiktar((string) $b->ana_miktar)), e($this->formatMiktar((string) $b->adet_esdegeri)),
                e($this->formatMiktar((string) $b->rezerve_ana_miktar)), e($this->formatMiktar(bcsub((string) $b->ana_miktar, (string) $b->rezerve_ana_miktar, 8))), e((string) $b->durum)
            );
        })->implode('');
        $ozet = '<tr class="font-semibold bg-gray-50 dark:bg-white/5"><td class="px-3 py-2" colspan="2">Toplam</td><td class="px-3 py-2 text-end">'.$this->formatMiktar($toplamAna).'</td><td class="px-3 py-2 text-end">'.$this->formatMiktar($toplamAdet).'</td><td class="px-3 py-2" colspan="3">—</td></tr>';

        return $this->stokOlcuBakiyeleriHtmlCache = new HtmlString('<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm"><thead><tr class="bg-gray-50 dark:bg-white/5 text-start"><th class="px-3 py-2 font-medium">Ölçü</th><th class="px-3 py-2 font-medium">Depo</th><th class="px-3 py-2 font-medium text-end">Ana miktar</th><th class="px-3 py-2 font-medium text-end">Adet eşdeğeri</th><th class="px-3 py-2 font-medium text-end">Rezerv</th><th class="px-3 py-2 font-medium text-end">Kullanılabilir</th><th class="px-3 py-2 font-medium">Durum</th></tr></thead><tbody>'.$rows.$ozet.'</tbody></table></div>');
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
