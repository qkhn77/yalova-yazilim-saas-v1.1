<?php

namespace App\Filament\Resources\SiparisKaynagi\Pages;

use App\Filament\Resources\SiparisKaynagi;
use App\Models\Ecommerce\Odeme;
use App\Models\Ecommerce\Siparis;
use App\Modules\Urun\Servisler\SiparisOdemeServisi;
use App\Services\EcommerceKargoTakipServisi;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class ViewSiparis extends ViewRecord
{
    protected static string $resource = SiparisKaynagi::class;

    protected static string $view = 'filament.resources.siparis-kaynagi.pages.view-siparis';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        /** @var Siparis $s */
        $s = $this->record;
        if (! $this->detayModu()) {
            return;
        }

        $s->loadMissing([
            'kalemler.stokKarti.gorseller',
            'odemeler',
            'gecmisleri.kullanici',
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        /** @var Siparis $r */
        $r = $this->record;

        return 'Sipariş '.$r->siparis_no;
    }

    protected function fillForm(): void
    {
        if ($this->detayModu()) {
            parent::fillForm();
        }
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Siparis $r */
        $r = $this->record;

        if (! $this->detayModu()) {
            return $r->musteri_ad_soyad;
        }

        $odemeParcasi = '';

        if ($this->detayModu()) {
            $o = $r->relationLoaded('sonOdeme')
                ? $r->sonOdeme
                : ($r->relationLoaded('odemeler') ? $r->odemeler->sortByDesc('id')->first() : null);
            $odemeEtiket = $o ? (Odeme::durumEtiketleri()[$o->durum] ?? $o->durum) : '—';
            $odemeParcasi = ' · Ödeme: '.$odemeEtiket;
        }

        return sprintf(
            '%s · %s%s · %s · %s %s',
            $r->musteri_ad_soyad,
            Siparis::durumEtiketleri()[$r->durum] ?? $r->durum,
            $odemeParcasi,
            $r->created_at?->format('d.m.Y H:i') ?? '—',
            number_format((float) $r->genel_toplam, 2, ',', '.'),
            $r->para_birimi ?: 'TRY'
        );
    }

    protected function getHeaderActions(): array
    {
        $detayModu = $this->detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizliGorunum' : 'detaylar')
                ->label($detayModu ? 'Hızlı Görünüm' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-list-bullet')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? SiparisKaynagi::getUrl('view', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
            Actions\Action::make('manuel_odeme_onayi')
                ->label('Manuel ödeme onayı')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (Siparis $r): bool => Siparis::odemeAkisindaDurumMu($r->durum) && ! $r->stok_dusuldu_mi)
                ->requiresConfirmation()
                ->action(function (Siparis $r): void {
                    try {
                        app(SiparisOdemeServisi::class)->adminManuelOdemeOnayla($r);
                        Notification::make()->title('Ödeme onaylandı')->success()->send();
                        $this->redirect(SiparisKaynagi::getUrl('view', ['record' => $r]));
                    } catch (ValidationException $e) {
                        $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                        Notification::make()->title((string) $msg)->danger()->send();
                    }
                }),
            Actions\Action::make('siparis_iptal')
                ->label('Siparişi iptal et')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Siparis $r): bool => ! Siparis::iptalEdildiDurumMu($r->durum)
                    && ! Siparis::teslimEdildiDurumMu($r->durum))
                ->form([
                    Textarea::make('iptal_nedeni')
                        ->label('İptal nedeni')
                        ->rows(3),
                ])
                ->action(function (Siparis $r, array $data): void {
                    try {
                        app(SiparisOdemeServisi::class)->siparisIptalEt($r, $data['iptal_nedeni'] ?? null);
                        Notification::make()->title('Sipariş iptal edildi')->success()->send();
                        $this->redirect(SiparisKaynagi::getUrl('view', ['record' => $r]));
                    } catch (ValidationException $e) {
                        $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                        Notification::make()->title((string) $msg)->danger()->send();
                    }
                }),
            Actions\Action::make('yazdir')
                ->label('Yazdır / fiş')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(fn (): mixed => $this->dispatch('siparis-yazdir')),
            Actions\EditAction::make()
                ->label('Düzenle'),
            ] : []),
        ];
    }

    private function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        if (! $this->detayModu()) {
            return $infolist->schema([]);
        }

        return $infolist
            ->schema([
                Tabs::make('siparis_sekmeler')
                    ->tabs([
                        Tab::make('ozet')
                            ->label('Özet')
                            ->schema([
                                Section::make('Müşteri ve adres')
                                    ->schema([
                                        TextEntry::make('musteri_ad_soyad')->label('Ad soyad'),
                                        TextEntry::make('musteri_telefon')->label('Telefon'),
                                        TextEntry::make('musteri_email')->label('E-posta')->placeholder('—'),
                                        TextEntry::make('teslimat_adresi')->label('Adres')->columnSpanFull(),
                                        TextEntry::make('notlar')->label('Sipariş notu')->placeholder('—')->columnSpanFull(),
                                        TextEntry::make('durum')
                                            ->label('Durum')
                                            ->badge()
                                            ->color(fn (?string $state): string => Siparis::durumRengi($state))
                                            ->formatStateUsing(fn (?string $state): string => Siparis::durumEtiketleri()[$state ?? ''] ?? ($state ?? '—')),
                                    ])
                                    ->columns(2),
                                Section::make('Toplamlar')
                                    ->schema([
                                        TextEntry::make('ara_toplam')->label('Ara toplam')->money(fn (Siparis $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('indirim_toplami')->label('İndirim')->money(fn (Siparis $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('kdv_toplam')->label('KDV')->money(fn (Siparis $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('kargo_ucreti')->label('Kargo')->money(fn (Siparis $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('genel_toplam')->label('Genel toplam')->money(fn (Siparis $r) => $r->para_birimi ?: 'TRY'),
                                    ])
                                    ->columns(5),
                                Section::make('Stok')
                                    ->schema([
                                        TextEntry::make('stok_dusuldu_mi')->label('Stok düşüldü mü')
                                            ->formatStateUsing(fn (?bool $state): string => $state ? 'Evet' : 'Hayır'),
                                        TextEntry::make('rezerv_ozet')
                                            ->label('Rezerv / kalem özeti')
                                            ->getStateUsing(function (Siparis $r): string {
                                                $satirlar = $r->kalemler->map(function ($k): string {
                                                    $rez = (float) ($k->stok_rezerv_miktari ?? 0);

                                                    return $k->urun_adi_snapshot.' · miktar: '.$k->miktar
                                                        .($rez > 0 ? ' · rezerv: '.$rez : '');
                                                });

                                                return $satirlar->isEmpty() ? '—' : $satirlar->implode("\n");
                                            })
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Finans / ödeme iadesi (bilgilendirme)')
                                    ->schema([
                                        TextEntry::make('finans_not')
                                            ->label('')
                                            ->getStateUsing(function (Siparis $r): string {
                                                $basarili = $r->odemeler->where('durum', Odeme::DURUM_BASARILI)->isNotEmpty();

                                                return $basarili
                                                    ? 'Başarılı ödeme kaydı var. Sipariş iptalinde stok iadesi çekirdekte yapılır; tahsilat/ödeme iadesi muhasebe panelinden ayrı yönetilmelidir.'
                                                    : 'Başarılı ödeme kaydı yoksa finans iadesi gerekmez. Ödeme bekleyen satırlar iptalde ödeme kaydı iptal edilir.';
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('kalemler')
                            ->label('Kalemler')
                            ->schema([
                                RepeatableEntry::make('kalemler')
                                    ->label('')
                                    ->schema([
                                        ImageEntry::make('urun_gorseli')
                                            ->label('Görsel')
                                            ->getStateUsing(function ($record): ?string {
                                                $stok = $record->stokKarti;
                                                $gorselYolu = $stok?->kapak_gorsel_yolu ?: $stok?->og_gorsel;

                                                return $gorselYolu ? asset('uploads/'.ltrim(str_replace('\\', '/', $gorselYolu), '/')) : asset('theme/yalovakamera/images/yalova_kamera.png');
                                            })
                                            ->height(64)
                                            ->width(64)
                                            ->square(),
                                        TextEntry::make('urun_adi_snapshot')->label('Ürün'),
                                        TextEntry::make('urun_kodu_snapshot')->label('Kod')->placeholder('—'),
                                        TextEntry::make('stok_takip_bilgisi')
                                            ->label('Parti / Seri No Barkodu')
                                            ->getStateUsing(fn ($record): string => $this->stokTakipBilgisi($record))
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                        TextEntry::make('miktar')->label('Miktar'),
                                        TextEntry::make('birim_fiyat')
                                            ->label('Birim fiyat')
                                            ->money(fn (): string => ($this->record instanceof Siparis && $this->record->para_birimi)
                                                ? (string) $this->record->para_birimi
                                                : 'TRY'),
                                        TextEntry::make('indirim_tutari')
                                            ->label('İndirim')
                                            ->getStateUsing(function ($record): float {
                                                $listeBirimFiyat = round((float) ($record->stokKarti?->satis_fiyati ?: $record->birim_fiyat), 2);

                                                return round(max(0, ($listeBirimFiyat - (float) $record->birim_fiyat) * (float) $record->miktar), 2);
                                            })
                                            ->money(fn (): string => ($this->record instanceof Siparis && $this->record->para_birimi)
                                                ? (string) $this->record->para_birimi
                                                : 'TRY'),
                                        TextEntry::make('kdv_tutari')
                                            ->label('KDV tutarı')
                                            ->getStateUsing(fn ($record): float => round((float) $record->satir_toplami * ((float) $record->kdv_orani / 100), 2))
                                            ->money(fn (): string => ($this->record instanceof Siparis && $this->record->para_birimi)
                                                ? (string) $this->record->para_birimi
                                                : 'TRY'),
                                        TextEntry::make('kdv_orani')->label('KDV %'),
                                        TextEntry::make('satir_toplami')
                                            ->label('Net satır')
                                            ->money(fn (): string => ($this->record instanceof Siparis && $this->record->para_birimi)
                                                ? (string) $this->record->para_birimi
                                                : 'TRY'),
                                    ])
                                    ->columns(4),
                            ]),
                        Tab::make('odemeler')
                            ->label('Ödemeler')
                            ->schema([
                                RepeatableEntry::make('odemeler')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('odeme_no')->label('Ödeme no'),
                                        TextEntry::make('tutar')->label('Tutar')->money(fn (Odeme $o) => $o->para_birimi ?: 'TRY'),
                                        TextEntry::make('durum')->label('Durum')
                                            ->formatStateUsing(fn (?string $state): string => Odeme::durumEtiketleri()[$state ?? ''] ?? ($state ?? '—')),
                                        TextEntry::make('provider')->label('Sağlayıcı'),
                                        TextEntry::make('provider_ref')->label('Referans')->copyable()->placeholder('—'),
                                        TextEntry::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i'),
                                    ])
                                    ->columns(3),
                            ]),
                        Tab::make('operasyon')
                            ->label('Operasyon')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextEntry::make('durum')->label('Sipariş durumu')
                                            ->badge()
                                            ->color(fn (?string $state): string => Siparis::durumRengi($state))
                                            ->formatStateUsing(fn (?string $state): string => Siparis::durumEtiketleri()[$state ?? ''] ?? ($state ?? '—')),
                                        TextEntry::make('odeme_suresi_bitis_at')->label('Ödeme süresi bitişi')
                                            ->dateTime('d.m.Y H:i')
                                            ->placeholder('—'),
                                        TextEntry::make('odeme_deneme_sayisi')->label('Ödeme deneme sayısı'),
                                        TextEntry::make('kargo_firmasi')->label('Kargo firması')->placeholder('—'),
                                        TextEntry::make('kargo_ucreti')->label('Kargo ücreti')
                                            ->formatStateUsing(fn ($state, Siparis $record): string => number_format((float) ($state ?? 0), 2, ',', '.').' '.($record->kargo_para_birimi ?: 'TRY')),
                                        TextEntry::make('takip_no')->label('Takip no')->copyable()->placeholder('—'),
                                        TextEntry::make('takip_linki')
                                            ->label('Takip linki')
                                            ->state(function (Siparis $record): string {
                                                return app(EcommerceKargoTakipServisi::class)->takipUrl($record->kargo_firmasi, $record->takip_no) ?? '—';
                                            })
                                            ->copyable()
                                            ->url(fn (Siparis $record): ?string => app(EcommerceKargoTakipServisi::class)->takipUrl($record->kargo_firmasi, $record->takip_no), true)
                                            ->openUrlInNewTab(),
                                        TextEntry::make('kargo_tarihi')->label('Kargo tarihi')->date('d.m.Y')->placeholder('—'),
                                        TextEntry::make('teslim_tarihi')->label('Teslim tarihi')->date('d.m.Y')->placeholder('—'),
                                        TextEntry::make('teslimat_ulke')->label('Ülke')->placeholder('—'),
                                        TextEntry::make('teslimat_il')->label('İl / eyalet')->placeholder('—'),
                                        TextEntry::make('teslimat_ilce')->label('İlçe / bölge')->placeholder('—'),
                                        TextEntry::make('teslimat_posta_kodu')->label('Posta kodu')->placeholder('—'),
                                        TextEntry::make('iptal_nedeni')->label('İptal nedeni')->placeholder('—')->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('notlar')
                            ->label('Notlar')
                            ->schema([
                                TextEntry::make('musteri_notu')->label('Müşteri notu')->placeholder('—')->columnSpanFull(),
                                TextEntry::make('operasyon_notu')->label('Operasyon notu')->placeholder('—')->columnSpanFull(),
                                TextEntry::make('ic_not')->label('İç not')->placeholder('—')->columnSpanFull(),
                            ]),
                        Tab::make('gecmis')
                            ->label('Geçmiş / log')
                            ->schema([
                                RepeatableEntry::make('gecmisleri')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('created_at')->label('Zaman')->dateTime('d.m.Y H:i'),
                                        TextEntry::make('olay')->label('Olay')->badge(),
                                        TextEntry::make('kullanici.name')
                                            ->label('Kullanıcı')
                                            ->placeholder('Sistem / misafir'),
                                        TextEntry::make('aciklama')->label('Açıklama')->placeholder('—')->columnSpanFull(),
                                    ])
                                    ->columns(3),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private function stokTakipBilgisi(mixed $kalem): string
    {
        $partiler = [];
        if (filled($kalem->parca_kodu ?? null)) {
            $partiler[] = (string) $kalem->parca_kodu;
        }

        foreach ((array) ($kalem->parca_dagilimi ?? []) as $parti) {
            if (! is_array($parti) || blank($parti['parca_kodu'] ?? null)) {
                continue;
            }

            $etiket = (string) $parti['parca_kodu'];
            if (filled($parti['miktar'] ?? null)) {
                $etiket .= ' ('.(string) $parti['miktar'].')';
            }
            $partiler[] = $etiket;
        }

        $partiler = array_values(array_unique($partiler));
        $seriler = array_values(array_filter(array_map(
            static fn ($seri): string => trim((string) $seri),
            (array) ($kalem->seri_nolari ?? [])
        ), static fn (string $seri): bool => $seri !== ''));

        $satirlar = [];
        if ($partiler !== []) {
            $satirlar[] = 'Parti / Lot: '.implode(', ', $partiler);
        }
        if ($seriler !== []) {
            $satirlar[] = 'Seri No Barkodu: '.implode(', ', $seriler);
        }

        return $satirlar === [] ? '—' : implode(' · ', $satirlar);
    }
}
