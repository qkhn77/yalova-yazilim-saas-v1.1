<?php

namespace App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Pages\BekleyenFatura;
use App\Filament\Clusters\Muhasebe\Pages\FinansDashboardSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\GelenFatura;
use App\Filament\Clusters\Muhasebe\Pages\GelenIadeFaturasiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\GidenFatura;
use App\Filament\Clusters\Muhasebe\Pages\GidenIadeFaturasiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\GiderFaturasiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\IptalFatura;
use App\Filament\Clusters\Muhasebe\Pages\ProformaFaturaSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\TumFaturalarSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Livewire\Muhasebe\FaturaDetaySekmesi;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaFinansKapama;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\StokHareketi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Muhasebe\Servisler\FaturaFinansKapamaServisi;
use App\Muhasebe\Servisler\FaturaKapamaDogrulamaServisi;
use App\Services\NetteFaturaIstemcisi;
use App\Services\NetteFaturaUblOlusturucu;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\Livewire as InfolistLivewire;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ViewFatura extends ViewRecord
{
    protected static string $resource = FaturaKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.fatura-kaynagi.pages.view-fatura';

    /** @var array{fatura_id:int,hata:?string}|null */
    protected ?array $kapamaRaporuOzet = null;

    /** @var array<int,HtmlString> */
    private array $kalemlerTablosuHtmlCache = [];

    /** @var array<int,HtmlString> */
    private array $cariHareketleriTablosuHtmlCache = [];

    /** @var array<int,HtmlString> */
    private array $stokHareketleriTablosuHtmlCache = [];

    /** @var array<int,HtmlString> */
    private array $altBagliFaturalarHtmlCache = [];

    /** @var array<int,string> */
    private array $faturaOtomasyonOzetiMetniCache = [];

    public function mount(int|string $record): void
    {
        // Görüntüleme ekranı, düzenleme ekranının salt-okunur görünümünü
        // kullanır; böylece iki sayfada aynı form ve tasarım korunur.
        $this->redirect(
            FaturaKaynagi::getUrl('edit', ['record' => (int) $record]).'?goruntule=1',
            navigate: true,
        );

        return;

    }

    protected function resolveRecord(int|string $key): Model
    {
        $detayModu = $this->detayModu();
        $kolonlar = $detayModu
            ? [
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
                'e_belge_uuid',
                'e_belge_durumu',
                'e_belge_saglayici',
                'e_belge_saglayici_belge_id',
                'e_belge_hash',
                'e_belge_gonderildi_at',
                'e_belge_yanit_kodu',
                'e_belge_yanit_mesaji',
                'e_belge_son_hata',
                'created_at',
            ]
            : [
                'id',
                'firma_id',
                'fatura_no',
            ];

        $sorgu = FaturaKaynagi::getEloquentQuery()
            ->whereKey($key)
            ->select($kolonlar);

        if ($detayModu) {
            $sorgu->with([
                'cari:id,kod,ad',
                'bagliFatura:id,fatura_no,tur',
            ]);
        }

        $record = $sorgu->first();

        if ($record === null) {
            throw (new ModelNotFoundException)->setModel($this->getModel(), [$key]);
        }

        return $record;
    }

    public function getTitle(): string|Htmlable
    {
        /** @var Fatura $r */
        $r = $this->record;

        return (string) ($r->fatura_no ?: 'Fatura #'.$r->getKey());
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (! $this->detayModu()) {
            return null;
        }

        /** @var Fatura $r */
        $r = $this->record;
        $tur = $r->tur instanceof FaturaTuru ? $r->tur->etiket() : (string) $r->tur;
        $durum = $r->durum instanceof FaturaDurumu ? $r->durum->value : (string) $r->durum;
        $odeme = (string) ($r->odeme_durumu ?? '—');

        return sprintf(
            'Tür: %s · Durum: %s · Ödeme: %s',
            $tur,
            Str::headline(str_replace('_', ' ', $durum)),
            Str::headline(str_replace('_', ' ', $odeme))
        );
    }

    protected function getHeaderActions(): array
    {
        /** @var Fatura $r */
        $r = $this->record;

        return [
            Actions\Action::make($this->detayModu() ? 'hizliGorunum' : 'detayliGorunum')
                ->label($this->detayModu() ? 'Hızlı Görünüm' : 'Detayları Göster')
                ->icon($this->detayModu() ? 'heroicon-o-bolt' : 'heroicon-o-list-bullet')
                ->color('gray')
                ->url(fn (): string => $this->detayModu()
                    ? FaturaKaynagi::getUrl('view', ['record' => (int) $this->record->getKey()]).'?hizli=1'
                    : FaturaKaynagi::getUrl('view', ['record' => (int) $this->record->getKey()])),
            Actions\EditAction::make()->label('Düzenle'),
            ...($this->detayModu() ? [
            Actions\Action::make('listeyeDon')
                ->label('Listeye dön')
                ->icon('heroicon-o-arrow-left')
                ->url(static::listeUrlForFatura($r))
                ->color('gray'),
            Actions\Action::make('cariyeGit')
                ->label('Cariye git')
                ->icon('heroicon-o-user')
                ->visible(fn (): bool => (bool) $r->cari_id)
                ->url(fn (): string => CariKartiKaynagi::getUrl('view', ['record' => $r->cari_id]))
                ->openUrlInNewTab(false),
            Actions\Action::make('ublXmlIndir')
                ->label('UBL XML indir')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => $this->eBelgeAkisiAcikMi($r))
                ->action(function () {
                    /** @var Fatura $fatura */
                    $fatura = $this->record;
                    $sonuc = app(NetteFaturaUblOlusturucu::class)->olustur($fatura);

                    return response()->streamDownload(
                        static function () use ($sonuc): void {
                            echo $sonuc['xml'];
                        },
                        $sonuc['dosya_adi'],
                        ['Content-Type' => 'application/xml; charset=UTF-8']
                    );
                }),
            Actions\Action::make('netteFaturaGonder')
                ->label('NetteFatura gönder')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn (): bool => $this->eBelgeAkisiAcikMi($r) && MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FATURA_GUNCELLE))
                ->requiresConfirmation()
                ->modalHeading('Faturayı NetteFatura’ya gönder')
                ->modalDescription('UBL XML oluşturulacak ve NetteFatura sendDocument servisine gönderilecek.')
                ->action(function (): void {
                    /** @var Fatura $fatura */
                    $fatura = $this->record;

                    try {
                        $sonuc = app(NetteFaturaIstemcisi::class)->belgeGonder($fatura);
                        $this->record = $fatura->refresh();

                        Notification::make()
                            ->title($sonuc['basarili'] ? 'NetteFatura gönderimi tamamlandı' : 'NetteFatura gönderimi hata döndü')
                            ->body($sonuc['mesaj'])
                            ->color($sonuc['basarili'] ? 'success' : 'danger')
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('NetteFatura gönderimi yapılamadı')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('netteFaturaYanitSorgula')
                ->label('Yanıt sorgula')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->eBelgeAkisiAcikMi($r) && filled($r->e_belge_hash ?? null))
                ->action(function (): void {
                    /** @var Fatura $fatura */
                    $fatura = $this->record;

                    try {
                        $sonuc = app(NetteFaturaIstemcisi::class)->uygulamaYanitiSorgula($fatura);
                        $this->record = $fatura->refresh();

                        Notification::make()
                            ->title($sonuc['basarili'] ? 'NetteFatura yanıtı sorgulandı' : 'NetteFatura yanıt sorgusu hata döndü')
                            ->body($sonuc['mesaj'])
                            ->color($sonuc['basarili'] ? 'success' : 'danger')
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Yanıt sorgusu yapılamadı')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('tahsilatYap')
                ->label('Tahsilat yap')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->url(fn (): string => FinansDashboardSayfasi::tahsilatUrlQuery(['fatura_id' => $r->id]))
                ->visible(fn (): bool => MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR) && $this->faturaTahsilatAkisiAcikMi($r)),
            Actions\Action::make('odemeYap')
                ->label('Ödeme yap')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->url(fn (): string => FinansDashboardSayfasi::faturaOdemeUrl($r->id))
                ->visible(fn (): bool => MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR) && $this->faturaOdemeAkisiAcikMi($r)),
            Actions\Action::make('finansKapamaDuzelt')
                ->label('Finans kapamasını düzelt')
                ->icon('heroicon-o-link')
                ->color('warning')
                ->visible(fn (): bool => $this->faturaKapamaDuzeltilebilirMi($r)
                    && MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_GUNCELLE))
                ->form([
                    \Filament\Forms\Components\Select::make('eski_finans_id')
                        ->label('İptal edilmiş eski finans hareketi')
                        ->options(fn (): array => $this->faturaKapamaEskiFinansSecenekleri($r))
                        ->required()->searchable(),
                    \Filament\Forms\Components\Select::make('yeni_finans_id')
                        ->label('Bağlanacak aktif finans hareketi')
                        ->options(fn (): array => $this->faturaKapamaYeniFinansSecenekleri($r))
                        ->required()->searchable(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(FaturaFinansKapamaServisi::class)->faturaKapamalariniYeniFinansaTasi(
                            (int) $data['eski_finans_id'],
                            (int) $data['yeni_finans_id'],
                        );
                        $this->record = $this->resolveRecord((int) $this->record->getKey());
                        Notification::make()->title('Fatura finans kapaması düzeltildi')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Finans kapaması düzeltilemedi')->body($e->getMessage())->danger()->send();
                    }
                }),
            ] : []),
        ];
    }

    private function faturaKapamaDuzeltilebilirMi(Fatura $fatura): bool
    {
        if (! $this->detayModu()) {
            return false;
        }

        return FaturaFinansKapama::query()->where('fatura_id', $fatura->getKey())
            ->whereHas('finansHareketi', fn ($q) => $q->where('durum', FinansHareketDurumu::Iptal))
            ->exists()
            && FinansHareketi::query()->where('firma_id', $fatura->firma_id)
                ->where('durum', FinansHareketDurumu::Aktif)->exists();
    }

    /** @return array<int,string> */
    private function faturaKapamaEskiFinansSecenekleri(Fatura $fatura): array
    {
        return FinansHareketi::query()->where('firma_id', $fatura->firma_id)
            ->where('durum', FinansHareketDurumu::Iptal)
            ->whereHas('faturaKapatmalari', fn ($q) => $q->where('fatura_id', $fatura->getKey()))
            ->orderByDesc('id')->get(['id', 'tarih', 'tutar', 'para_birimi'])
            ->mapWithKeys(fn (FinansHareketi $h): array => [$h->id => '#'.$h->id.' · '.number_format((float) $h->tutar, 2, ',', '.').' '.($h->para_birimi ?: 'TRY').' · iptal'])->all();
    }

    /** @return array<int,string> */
    private function faturaKapamaYeniFinansSecenekleri(Fatura $fatura): array
    {
        return FinansHareketi::query()->where('firma_id', $fatura->firma_id)
            ->where('durum', FinansHareketDurumu::Aktif)
            ->when($fatura->cari_id, fn ($q) => $q->where('cari_id', $fatura->cari_id))
            ->whereIn('tur', [FinansHareketTuru::Tahsilat->value, FinansHareketTuru::Odeme->value])
            ->orderByDesc('id')->limit(100)->get(['id', 'tarih', 'tutar', 'para_birimi', 'tur'])
            ->mapWithKeys(fn (FinansHareketi $h): array => [$h->id => '#'.$h->id.' · '.number_format((float) $h->tutar, 2, ',', '.').' '.($h->para_birimi ?: 'TRY').' · '.($h->tur instanceof FinansHareketTuru ? $h->tur->value : $h->tur)])->all();
    }

    private function eBelgeAkisiAcikMi(Fatura $f): bool
    {
        if (! $this->detayModu()) {
            return false;
        }

        if (! $f->cari_id) {
            return false;
        }

        $eBelgeTipi = (string) ($f->e_belge_tipi ?? '');
        if ($eBelgeTipi === 'kagit') {
            return false;
        }

        $tur = $f->tur instanceof FaturaTuru ? $f->tur : FaturaTuru::tryFrom((string) $f->tur);

        return $tur?->kanonik()->kayitUretirMi() === true;
    }

    private function faturaTahsilatAkisiAcikMi(Fatura $f): bool
    {
        if (! $f->cari_id) {
            return false;
        }
        if ($f->durum !== FaturaDurumu::Onayli) {
            return false;
        }
        $tur = $f->tur instanceof FaturaTuru ? $f->tur : FaturaTuru::tryFrom((string) $f->tur);
        if (! $tur || ! $tur->kayitUretirMi() || $tur->cariYonu() !== 'alacak') {
            return false;
        }

        return bccomp((string) ($f->acik_tutar ?? '0'), '0', 2) > 0;
    }

    private function faturaOdemeAkisiAcikMi(Fatura $f): bool
    {
        if (! $f->cari_id) {
            return false;
        }
        if ($f->durum !== FaturaDurumu::Onayli) {
            return false;
        }
        $tur = $f->tur instanceof FaturaTuru ? $f->tur : FaturaTuru::tryFrom((string) $f->tur);
        if (! $tur || ! $tur->kayitUretirMi() || $tur->cariYonu() !== 'borc') {
            return false;
        }

        return bccomp((string) ($f->acik_tutar ?? '0'), '0', 2) > 0;
    }

    private function detayModu(): bool
    {
        return ! request()->boolean('hizli');
    }

    public static function listeUrlForFatura(Fatura $fatura): string
    {
        $turDegeri = $fatura->tur instanceof FaturaTuru ? $fatura->tur->value : (string) $fatura->tur;
        $slug = FaturaKaynagi::slugPathFromTuru($turDegeri);

        return match ($slug) {
            'gelen-faturalar' => GelenFatura::getUrl(),
            'giden-faturalar' => GidenFatura::getUrl(),
            'bekleyen-faturalar' => BekleyenFatura::getUrl(),
            'iptal-faturalar' => IptalFatura::getUrl(),
            'giden-iade-faturalari' => GidenIadeFaturasiSayfasi::getUrl(),
            'gelen-iade-faturalari' => GelenIadeFaturasiSayfasi::getUrl(),
            'proforma-faturalar' => ProformaFaturaSayfasi::getUrl(),
            'gider-faturalari' => GiderFaturasiSayfasi::getUrl(),
            default => TumFaturalarSayfasi::getUrl(),
        };
    }

    protected function kapamaRaporu(): array
    {
        if ($this->kapamaRaporuOzet !== null) {
            return $this->kapamaRaporuOzet;
        }

        /** @var Fatura $r */
        $r = $this->record;
        if (! $r->relationLoaded('finansKapatmalari')) {
            $this->kapamaRaporuOzet = app(FaturaKapamaDogrulamaServisi::class)
                ->faturaKapamaDurumuRaporla((int) $r->getKey());

            return $this->kapamaRaporuOzet;
        }

        $odenen = '0.00';
        foreach ($r->finansKapatmalari as $kapama) {
            /** @var FaturaFinansKapama $kapama */
            $hareketDurumu = $kapama->finansHareketi?->durum;
            $aktif = $hareketDurumu instanceof FinansHareketDurumu
                ? $hareketDurumu === FinansHareketDurumu::Aktif
                : (string) $hareketDurumu === FinansHareketDurumu::Aktif->value;

            if ($aktif) {
                $odenen = bcadd($odenen, (string) ($kapama->uygulanan_tutar ?? '0'), 2);
            }
        }

        $odenecek = (string) ($r->odenecek_tutar ?? $r->genel_toplam ?? 0);
        $acikBeklenen = bcsub($odenecek, $odenen, 2);
        $acikBeklenenClamp = bccomp($acikBeklenen, '0', 2) < 0 ? '0.00' : $acikBeklenen;

        $hata = null;
        if (bccomp((string) $r->odendi_tutari, $odenen, 2) !== 0) {
            $hata = 'odendi_tutari uyuşmuyor';
        } elseif (bccomp((string) $r->acik_tutar, $acikBeklenenClamp, 2) !== 0) {
            $hata = 'acik_tutar uyuşmuyor';
        } elseif (bccomp((string) $r->acik_tutar, '0', 2) < 0) {
            $hata = 'acik_tutar negatif';
        }

        $toplam = $r->finansKapatmalari->count();
        $distinct = $r->finansKapatmalari->pluck('finans_hareket_id')->unique()->count();
        if ($hata === null && $toplam !== $distinct) {
            $hata = 'duplicate kapama tespit edildi';
        }

        $this->kapamaRaporuOzet = [
            'fatura_id' => (int) $r->getKey(),
            'hata' => $hata,
            'odenecek_tutar' => $odenecek,
            'odendi_tutari' => (string) $r->odendi_tutari,
            'beklenen_odendi_tutari' => $odenen,
            'acik_tutar' => (string) $r->acik_tutar,
            'beklenen_acik_tutar' => $acikBeklenenClamp,
        ];

        return $this->kapamaRaporuOzet;
    }

    protected function vadesiGecmisVeAcikMi(Fatura $f): bool
    {
        if ($f->vade_tarihi === null) {
            return false;
        }
        if (bccomp((string) ($f->acik_tutar ?? '0'), '0', 2) <= 0) {
            return false;
        }

        $vade = Carbon::parse($f->vade_tarihi)->startOfDay();

        return $vade->lt(Carbon::today());
    }

    public function infolist(Infolist $infolist): Infolist
    {
        if (! $this->detayModu()) {
            return $infolist
                ->schema([
                    TextEntry::make('fatura_no')->label('Fatura no'),
                ])
                ->columns(1);
        }

        return $infolist
            ->schema([
                Section::make('Uyarılar')
                    ->schema([
                        TextEntry::make('_u_iptal')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Bu fatura iptal edilmiştir.')
                            ->color('danger')
                            ->icon('heroicon-o-x-circle')
                            ->visible(fn (Fatura $record): bool => $record->durum === FaturaDurumu::Iptal),
                        TextEntry::make('_u_vade')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Vadesi geçmiş açık tutar bulunmaktadır; tahsilat/ödeme takibi önerilir.')
                            ->color('warning')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->visible(fn (Fatura $record): bool => $this->vadesiGecmisVeAcikMi($record)),
                        TextEntry::make('_u_kapama')
                            ->label('')
                            ->getStateUsing(function (): string {
                                $h = $this->kapamaRaporu()['hata'] ?? null;

                                return 'Kapama tutarsızlığı: '.($h ?? '');
                            })
                            ->color('warning')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->visible(fn (): bool => $this->detayModu() && filled($this->kapamaRaporu()['hata'] ?? null)),
                    ])
                    ->visible(function (Fatura $record): bool {
                        if ($record->durum === FaturaDurumu::Iptal) {
                            return true;
                        }
                        if ($this->vadesiGecmisVeAcikMi($record)) {
                            return true;
                        }

                        return $this->detayModu() && filled($this->kapamaRaporu()['hata'] ?? null);
                    })
                    ->columns(1),

                Section::make('Özet rakamlar')
                    ->schema([
                        TextEntry::make('genel_toplam')
                            ->label('Genel toplam')
                            ->money(fn (Fatura $r) => $r->para_birimi ?: 'TRY'),
                        TextEntry::make('odendi_tutari')
                            ->label('Ödenen')
                            ->money(fn (Fatura $r) => $r->para_birimi ?: 'TRY'),
                        TextEntry::make('acik_tutar')
                            ->label('Açık tutar')
                            ->money(fn (Fatura $r) => $r->para_birimi ?: 'TRY')
                            ->color(fn (Fatura $r): ?string => bccomp((string) ($r->acik_tutar ?? '0'), '0', 2) > 0 ? 'warning' : null),
                        TextEntry::make('odeme_durumu')
                            ->label('Ödeme durumu')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state ? Str::headline(str_replace('_', ' ', $state)) : '—'),
                    ])
                    ->columns(4),

                Tabs::make('FaturaDetay')
                    ->tabs([
                        Tab::make('Özet')
                            ->schema([
                                Section::make('Kimlik')
                                    ->schema([
                                        TextEntry::make('fatura_no')->label('Fatura no'),
                                        TextEntry::make('belge_no')->label('Belge no')->placeholder('—'),
                                        TextEntry::make('tur')
                                            ->label('Tür')
                                            ->badge()
                                            ->formatStateUsing(fn ($state): string => $state instanceof FaturaTuru ? $state->value : (string) $state),
                                        TextEntry::make('durum')
                                            ->label('Durum')
                                            ->badge()
                                            ->formatStateUsing(fn ($state): string => $state instanceof FaturaDurumu ? $state->value : (string) $state),
                                        TextEntry::make('odeme_durumu')
                                            ->label('Ödeme durumu')
                                            ->badge()
                                            ->formatStateUsing(fn (?string $state): string => $state ? Str::headline(str_replace('_', ' ', $state)) : '—'),
                                        TextEntry::make('para_birimi')->label('Para birimi'),
                                    ])
                                    ->columns(2),
                                Section::make('Tarihler')
                                    ->schema([
                                        TextEntry::make('tarih')->label('Fatura tarihi')->dateTime('d.m.Y H:i'),
                                        TextEntry::make('vade_tarihi')->label('Vade')->date('d.m.Y')->placeholder('—'),
                                        TextEntry::make('created_at')->label('Oluşturulma')->dateTime('d.m.Y H:i'),
                                    ])
                                    ->columns(2),
                                Section::make('Cari')
                                    ->schema([
                                        TextEntry::make('cari.ad')
                                            ->label('Cari adı')
                                            ->placeholder('—')
                                            ->url(fn (Fatura $r): ?string => $r->cari_id
                                                ? CariKartiKaynagi::getUrl('view', ['record' => $r->cari_id])
                                                : null),
                                        TextEntry::make('cari.kod')->label('Cari kod')->placeholder('—'),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Fatura $r): bool => (bool) $r->cari_id),
                                Section::make('Tutarlar')
                                    ->schema([
                                        TextEntry::make('ara_toplam')->label('Ara toplam')->money(fn (Fatura $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('kdv_toplam')->label('KDV')->money(fn (Fatura $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('genel_toplam')->label('Genel toplam')->money(fn (Fatura $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('odenecek_tutar')->label('Ödenecek tutar')->money(fn (Fatura $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('odendi_tutari')->label('Ödenen tutar')->money(fn (Fatura $r) => $r->para_birimi ?: 'TRY'),
                                        TextEntry::make('acik_tutar')->label('Açık tutar')->money(fn (Fatura $r) => $r->para_birimi ?: 'TRY'),
                                    ])
                                    ->columns(2),
                                Section::make('Ek')
                                    ->schema([
                                        TextEntry::make('doviz_kuru')->label('Döviz kuru')->numeric(8),
                                        TextEntry::make('kdv_dahil_fiyatlandirma_mi')
                                            ->label('KDV dahil fiyatlandırma')
                                            ->formatStateUsing(fn (?bool $state): string => $state ? 'Evet' : 'Hayır'),
                                    ])
                                    ->columns(2),
                                Section::make('İptal')
                                    ->schema([
                                        TextEntry::make('iptal_nedeni')->label('İptal nedeni')->columnSpanFull(),
                                        TextEntry::make('iptal_edildi_at')->label('İptal tarihi')->dateTime('d.m.Y H:i'),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Fatura $r): bool => filled($r->iptal_nedeni) || $r->iptal_edildi_at !== null || $r->durum === FaturaDurumu::Iptal),
                            ]),
                        ...($this->detayModu() ? [
                        Tab::make('Kalemler')
                            ->schema([
                                InfolistLivewire::make(FaturaDetaySekmesi::class, ['sekme' => 'kalemler'])
                                    ->lazy()
                                    ->key('fatura-detay-kalemler'),
                            ]),
                        Tab::make('Ödemeler')
                            ->schema([
                                InfolistLivewire::make(FaturaDetaySekmesi::class, ['sekme' => 'odemeler'])
                                    ->lazy()
                                    ->key('fatura-detay-odemeler'),
                            ]),
                        Tab::make('Cari')
                            ->schema([
                                InfolistLivewire::make(FaturaDetaySekmesi::class, ['sekme' => 'cari'])
                                    ->lazy()
                                    ->key('fatura-detay-cari'),
                            ])
                            ->visible(fn (Fatura $r): bool => (bool) $r->cari_id),
                        Tab::make('Stok')
                            ->schema([
                                InfolistLivewire::make(FaturaDetaySekmesi::class, ['sekme' => 'stok'])
                                    ->lazy()
                                    ->key('fatura-detay-stok'),
                            ]),
                        Tab::make('Bağlantılar')
                            ->schema([
                                InfolistLivewire::make(FaturaDetaySekmesi::class, ['sekme' => 'baglantilar'])
                                    ->lazy()
                                    ->key('fatura-detay-baglantilar'),
                            ]),
                        Tab::make('Notlar')
                            ->schema([
                                TextEntry::make('aciklama')->label('Açıklama')->columnSpanFull()->placeholder('—'),
                                TextEntry::make('notlar')->label('Notlar')->columnSpanFull()->placeholder('—'),
                            ]),
                        ] : []),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private function para(Fatura|FaturaKalemi|FaturaFinansKapama|null $kaynak, Fatura $fatura): string
    {
        if ($kaynak instanceof Fatura) {
            return $kaynak->para_birimi ?: 'TRY';
        }
        if ($kaynak instanceof FaturaKalemi || $kaynak instanceof FaturaFinansKapama) {
            $pb = $kaynak->para_birimi ?? null;

            return $pb ? (string) $pb : ($fatura->para_birimi ?: 'TRY');
        }

        return $fatura->para_birimi ?: 'TRY';
    }

    private function kalemlerTablosuHtml(Fatura $fatura): HtmlString
    {
        $cacheKey = (int) $fatura->getKey();
        if (isset($this->kalemlerTablosuHtmlCache[$cacheKey])) {
            return $this->kalemlerTablosuHtmlCache[$cacheKey];
        }

        $rows = '';
        foreach ($fatura->kalemler as $kalem) {
            /** @var FaturaKalemi $kalem */
            $ad = $kalem->stokKarti?->ad
                ?? $kalem->aciklama
                ?? '—';
            $pb = $this->para($kalem, $fatura);
            $satirToplam = (string) ($kalem->satir_genel_toplam ?? $kalem->toplam ?? '0');
            $rows .= sprintf(
                '<tr class="border-b border-gray-100 dark:border-white/10">
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                </tr>',
                e($ad),
                e((string) $kalem->miktar),
                e((string) ($kalem->birim ?? '—')),
                e(number_format((float) $kalem->birim_fiyat, 2, ',', '.').' '.$pb),
                e((string) $kalem->kdv_orani),
                e(number_format((float) $satirToplam, 2, ',', '.').' '.$pb)
            );
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="px-3 py-4 text-sm text-gray-500">Kalem yok.</td></tr>';
        }

        $toplamSatir = '0.00000000';
        foreach ($fatura->kalemler as $k) {
            $toplamSatir = bcadd($toplamSatir, (string) ($k->satir_genel_toplam ?? $k->toplam ?? '0'), 8);
        }
        $genel = (string) ($fatura->genel_toplam ?? '0');
        $uyum = bccomp($toplamSatir, $genel, 8) === 0;
        $kontrol = $uyum
            ? 'Kalem satır toplamı genel toplam ile uyumlu görünüyor.'
            : 'Kalem satır toplamı ('.$toplamSatir.') ile genel toplam ('.$genel.') farklı; başlık alanlarını kontrol edin.';

        $html = '<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm">
            <thead><tr class="bg-gray-50 dark:bg-white/5 text-start">
                <th class="px-3 py-2 font-medium">Ürün / stok / açıklama</th>
                <th class="px-3 py-2 font-medium text-end">Miktar</th>
                <th class="px-3 py-2 font-medium">Birim</th>
                <th class="px-3 py-2 font-medium text-end">Birim fiyat</th>
                <th class="px-3 py-2 font-medium text-end">KDV %</th>
                <th class="px-3 py-2 font-medium text-end">Satır toplamı</th>
            </tr></thead><tbody>'.$rows.'</tbody></table></div>';
        $html .= '<p class="mt-3 text-sm '.($uyum ? 'text-gray-600 dark:text-gray-400' : 'text-warning-600 dark:text-warning-400').'">'.e($kontrol).'</p>';

        return $this->kalemlerTablosuHtmlCache[$cacheKey] = new HtmlString($html);
    }

    private function cariHareketleriTablosuHtml(Fatura $fatura): HtmlString
    {
        $cacheKey = (int) $fatura->getKey();
        if (isset($this->cariHareketleriTablosuHtmlCache[$cacheKey])) {
            return $this->cariHareketleriTablosuHtmlCache[$cacheKey];
        }

        if (! $fatura->cari_id) {
            return $this->cariHareketleriTablosuHtmlCache[$cacheKey] = new HtmlString('<p class="text-sm text-gray-500">Bu fatura için cari bağlantısı yok.</p>');
        }

        $running = '0.00';
        $pb = $fatura->para_birimi ?: 'TRY';
        $rows = '';
        /** @var CariHareketi $h */
        foreach ($fatura->cariHareketleri as $h) {
            $borc = (string) ($h->borc ?? '0');
            $alacak = (string) ($h->alacak ?? '0');
            $running = bcadd($running, $borc, 2);
            $running = bcsub($running, $alacak, 2);
            $rows .= sprintf(
                '<tr class="border-b border-gray-100 dark:border-white/10">
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm text-end font-medium">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                </tr>',
                e(optional($h->islem_tarihi)->format('d.m.Y H:i') ?? '—'),
                e(number_format((float) $borc, 2, ',', '.').' '.$pb),
                e(number_format((float) $alacak, 2, ',', '.').' '.$pb),
                e(number_format((float) $running, 2, ',', '.').' '.$pb),
                e((string) ($h->aciklama ?? '—'))
            );
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="px-3 py-4 text-sm text-gray-500">Bu fatura için cari hareketi bulunamadı.</td></tr>';
        }

        $html = '<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm">
            <thead><tr class="bg-gray-50 dark:bg-white/5 text-start">
                <th class="px-3 py-2 font-medium">Tarih</th>
                <th class="px-3 py-2 font-medium text-end">Borç</th>
                <th class="px-3 py-2 font-medium text-end">Alacak</th>
                <th class="px-3 py-2 font-medium text-end">Bakiye (işlem sırası)</th>
                <th class="px-3 py-2 font-medium">Açıklama</th>
            </tr></thead><tbody>'.$rows.'</tbody></table></div>';
        $html .= '<p class="mt-2 text-xs text-gray-500">Bakiye, bu faturaya bağlı cari hareket satırları üzerinden kronolojik birikimli gösterimdir; resmi ekstre ile mutlaka karşılaştırın.</p>';

        return $this->cariHareketleriTablosuHtmlCache[$cacheKey] = new HtmlString($html);
    }

    private function stokHareketleriTablosuHtml(Fatura $fatura): HtmlString
    {
        $cacheKey = (int) $fatura->getKey();
        if (isset($this->stokHareketleriTablosuHtmlCache[$cacheKey])) {
            return $this->stokHareketleriTablosuHtmlCache[$cacheKey];
        }

        $rows = '';
        foreach ($fatura->stokHareketleri as $h) {
            /** @var StokHareketi $h */
            $ad = $h->stokKarti?->ad ?? '—';
            $tur = $h->islem_turu instanceof StokHareketIslemTuru ? $h->islem_turu->value : (string) $h->islem_turu;
            $rows .= sprintf(
                '<tr class="border-b border-gray-100 dark:border-white/10">
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                </tr>',
                e($ad),
                e($tur),
                e((string) $h->miktar),
                e(optional($h->tarih)->format('d.m.Y H:i') ?? '—')
            );
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="px-3 py-4 text-sm text-gray-500">Stok hareketi yok.</td></tr>';
        }

        $html = '<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm">
            <thead><tr class="bg-gray-50 dark:bg-white/5 text-start">
                <th class="px-3 py-2 font-medium">Stok</th>
                <th class="px-3 py-2 font-medium">İşlem türü</th>
                <th class="px-3 py-2 font-medium text-end">Miktar</th>
                <th class="px-3 py-2 font-medium">Tarih</th>
            </tr></thead><tbody>'.$rows.'</tbody></table></div>';

        return $this->stokHareketleriTablosuHtmlCache[$cacheKey] = new HtmlString($html);
    }

    private function altBagliFaturalarHtml(Fatura $fatura): HtmlString
    {
        $cacheKey = (int) $fatura->getKey();
        if (isset($this->altBagliFaturalarHtmlCache[$cacheKey])) {
            return $this->altBagliFaturalarHtmlCache[$cacheKey];
        }

        $digerleri = Fatura::query()
            ->where('bagli_fatura_id', $fatura->getKey())
            ->orderBy('id')
            ->get(['id', 'fatura_no', 'tur']);

        if ($digerleri->isEmpty()) {
            return $this->altBagliFaturalarHtmlCache[$cacheKey] = new HtmlString('<span class="text-sm text-gray-500">Bu faturaya bağlı alt kayıt yok.</span>');
        }

        $parts = [];
        foreach ($digerleri as $d) {
            $url = e(FaturaKaynagi::getUrl('view', ['record' => $d->id]));
            $no = e($d->fatura_no ?: '#'.$d->id);
            $tur = $d->tur instanceof FaturaTuru ? $d->tur->value : (string) $d->tur;
            $parts[] = '<a href="'.$url.'" class="text-primary-600 hover:underline text-sm font-medium">'.$no.'</a> <span class="text-gray-500 text-xs">('.e($tur).')</span>';
        }

        return $this->altBagliFaturalarHtmlCache[$cacheKey] = new HtmlString('<div class="flex flex-wrap gap-x-3 gap-y-1">'.implode('', $parts).'</div>');
    }

    private function faturaOtomasyonOzetiMetni(Fatura $fatura): string
    {
        $cacheKey = (int) $fatura->getKey();
        if (array_key_exists($cacheKey, $this->faturaOtomasyonOzetiMetniCache)) {
            return $this->faturaOtomasyonOzetiMetniCache[$cacheKey];
        }

        return $this->faturaOtomasyonOzetiMetniCache[$cacheKey] = app(FaturaFinansKapamaServisi::class)
            ->faturaOtomasyonOzetiMetni($fatura);
    }
}
