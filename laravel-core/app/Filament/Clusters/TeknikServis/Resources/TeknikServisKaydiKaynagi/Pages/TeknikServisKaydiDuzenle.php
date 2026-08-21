<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Pages\VadeTakipSayfasi;
use App\Filament\Clusters\TeknikServis\Concerns\TeknikServisKayitFormSchema;
use App\Filament\Clusters\TeknikServis\Pages\KabulFisi;
use App\Filament\Clusters\TeknikServis\Pages\ServisFormu;
use App\Filament\Clusters\TeknikServis\Pages\ServisFisi;
use App\Filament\Clusters\TeknikServis\Pages\TeslimFisi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Models\TeknikServis\TeknikServisDurumGecmisi;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Models\TeknikServis\TeknikServisMesajSablonu;
use App\Models\TeknikServis\TeknikServisHatirlatma;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisMuhasebeBaglantisi;
use App\Models\TeknikServis\TeknikServisTahsilati;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Services\TeknikServisTelegramBildirimServisi;
use App\TeknikServis\Enumlar\TeknikServisMuhasebeIslemTipi;
use App\TeknikServis\Enumlar\TeknikServisMuhasebeSenkronDurumu;
use App\TeknikServis\Filament\TeknikServisAlacakOzetAksiyonu;
use App\TeknikServis\Filament\TeknikServisAlacakPlanAksiyonu;
use App\TeknikServis\Filament\TeknikServisDurumKodlari;
use App\TeknikServis\Servisler\TeknikServisAlacakOzetServisi;
use App\TeknikServis\Servisler\TeknikServisBekleyenFaturaSenkronKontrolu;
use App\TeknikServis\Servisler\TeknikServisFormSecenekCache;
use App\TeknikServis\Servisler\TeknikServisTahsilatServisi;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TeknikServisKaydiDuzenle extends EditRecord
{
    private const PARA_BASAMAK = 8;

    protected static string $resource = TeknikServisKaydiKaynagi::class;

    protected array $extraBodyAttributes = [
        'class' => 'page-teknik-servis-create-compact',
    ];

    protected static string $view = 'filament.clusters.teknik-servis.resources.teknik-servis-kaydi-kaynagi.pages.teknik-servis-kaydi-duzenle';

    protected static ?string $title = "Servis kayd\u{0131}n\u{0131} d\u{00FC}zenle";

    private ?int $oncekiServisDurumuId = null;

    private bool $teslimBekleyenIlkGecisUyarisiGoster = false;

    private bool $teslimEdildiTelegramBildirimiGonder = false;

    private ?string $teslimAcikAlacakUyarisiMesaji = null;

    private bool $teslimEdilenKalemUyarisiGoster = false;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $kayitSonrasiMuhasebeSenkronData = null;

    /** @var array<int, string> */
    private array $muhasebeSenkronBildirimleri = [];

    /** @var array<int, array{kod:string,ad:string,is_fiyat_verildi:bool,is_teslim_edildi:bool,is_iptal:bool,is_iade:bool}|null> */
    private array $servisDurumuCache = [];

    /** @var array<int, int>|null */
    private ?array $teslimBekleyenDurumIdleriCache = null;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function areFormActionsSticky(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return TeknikServisKayitFormSchema::formuOlustur($form, false, null);
    }

    protected function resolveRecord(int | string $key): Model
    {
        $record = parent::resolveRecord($key);

        if ($record instanceof TeknikServisKaydi && $record->relationLoaded('kalemler')) {
            TeknikServisKayitFormSchema::stokKayitlariniCachele(
                $record->kalemler
                    ->pluck('stok_id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all(),
                (int) $record->firma_id
            );
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('durumBilgisi')
                ->label(fn (): string => 'Durum: '.$this->guncelDurumMetni())
                ->disabled()
                ->extraAttributes([
                    'class' => 'animate-pulse',
                    'style' => 'pointer-events:none; font-weight:700;',
                ]),
            Actions\Action::make('kabulFormuYazdir')
                ->label('Kabul Formu')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->url(fn (): string => KabulFisi::getUrl([
                    'record' => (int) $this->record->getKey(),
                    'auto_print' => 1,
                ]), shouldOpenInNewTab: true),
            Actions\Action::make('teslimFormuYazdir')
                ->label('Teslim Formu')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->url(fn (): string => TeslimFisi::getUrl([
                    'record' => (int) $this->record->getKey(),
                    'auto_print' => 1,
                ]), shouldOpenInNewTab: true),
            Actions\Action::make('servisFormuYazdir')
                ->label('Servis Formu')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('gray')
                ->url(fn (): string => ServisFormu::getUrl([
                    'record' => (int) $this->record->getKey(),
                    'auto_print' => 1,
                ]), shouldOpenInNewTab: true),
            Actions\Action::make('servisFisiYazdir')
                ->label('Servis Fişi')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn (): string => ServisFisi::getUrl([
                    'record' => (int) $this->record->getKey(),
                    'auto_print' => 1,
                ]), shouldOpenInNewTab: true),
                TeknikServisAlacakOzetAksiyonu::olustur(fn () => $this->record),
                TeknikServisAlacakPlanAksiyonu::olustur(fn () => $this->record),
            Actions\Action::make('saveChanges')
                ->label('Kaydet')
                ->color('primary')
                ->action('save'),
        ];
    }

    protected function getFormActions(): array
    {
        return parent::getFormActions();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oncekiServisDurumuId = (int) ($this->record?->servis_durumu_id ?? 0);
        $this->teslimBekleyenIlkGecisUyarisiGoster = $this->teslimBekleyenIlkGecisUyarisiGerekliMi(
            $this->oncekiServisDurumuId,
            (int) ($data['servis_durumu_id'] ?? 0)
        );
        $this->teslimEdildiTelegramBildirimiGonder = $this->teslimEdildiTelegramBildirimiGerekliMi(
            $this->oncekiServisDurumuId,
            (int) ($data['servis_durumu_id'] ?? 0)
        );
        $this->teslimAcikAlacakUyarisiMesaji = null;
        $this->teslimEdilenKalemUyarisiGoster = false;
        $this->kayitSonrasiMuhasebeSenkronData = null;
        $this->muhasebeSenkronBildirimleri = [];

        $this->fiyatVerilenGecisiniDogrula($data);
        $this->teslimDurumuGecisiniDogrula($data);

        $ham = $this->form->getRawState();
        if (array_key_exists('arizalar', $data) || (is_array($ham) && array_key_exists('arizalar', $ham))) {
            $arizaSecimleri = $data['arizalar'] ?? (is_array($ham) ? ($ham['arizalar'] ?? []) : []);
            $data['ariza_id'] = array_values(array_filter((array) $arizaSecimleri))[0] ?? null;
        }

        if (array_key_exists('kalemler', $data)) {
            $ozet = TeknikServisKayitFormSchema::stokOzetHesaplaKalemDizisi($this->formKalemleriniAl($data));
            $data['toplam_tutar'] = (float) ($ozet['odenecek_tutar'] ?? 0);
        }

        $data['guncelleyen_id'] = Auth::id();
        $this->teslimEdilenKalemUyarisiGoster = $this->teslimEdilenKalemUyarisiGerekliMi($data);
        $this->kayitSonrasiMuhasebeSenkronData = $data;

        return $data;
    }

    protected function afterSave(): void
    {
        $ham = $this->form->getRawState();
        $this->kayitSonrasiMuhasebeSenkronla();
        $this->durumGecmisiKaydet();

        if (
            is_array($ham)
            && (array_key_exists('bakim_tarihi', $ham) || array_key_exists('bakim_periyot_ay', $ham))
        ) {
            $this->bakimHatirlatmasiniSenkronla($this->record, $ham);
        }

        if ($this->teslimBekleyenIlkGecisUyarisiGoster) {
            $this->teslimBekleyenMesajOnayiGoster(is_array($ham) ? $ham : []);
        }

        if ($this->teslimEdilenKalemUyarisiGoster) {
            Notification::make()
                ->title('Teslim edilmiş servis güncellendi')
                ->body('Bu servis teslim edilmiş görünüyor. Stok kalemi değiştirdiyseniz muhasebe kontrolü ekranından fatura ve stok hareketlerini kontrol edin.')
                ->warning()
                ->send();

            $this->teslimEdilenKalemUyarisiGoster = false;
        }

        $this->muhasebeSenkronBildirimleriniGoster();

        if ($this->teslimEdildiTelegramBildirimiGonder) {
            app(TeknikServisTelegramBildirimServisi::class)->teslimEdildi($this->record);
        }

        if ($this->teslimAcikAlacakUyarisiMesaji) {
            Notification::make()
                ->title('Açık alacak ödeme planında takip edilecek')
                ->body($this->teslimAcikAlacakUyarisiMesaji)
                ->warning()
                ->actions([
                    NotificationAction::make('vade_takibi')
                        ->label('Vade Takibine Git')
                        ->url(VadeTakipSayfasi::getUrl()),
                ])
                ->send();

            $this->teslimAcikAlacakUyarisiMesaji = null;
        }
    }

    private function kayitSonrasiMuhasebeSenkronla(): void
    {
        if ($this->kayitSonrasiMuhasebeSenkronData === null) {
            return;
        }

        $this->record->unsetRelation('kalemler');
        $this->servisDurumunaGoreBekleyenFaturaSenkronla($this->kayitSonrasiMuhasebeSenkronData);
        $this->kayitSonrasiMuhasebeSenkronData = null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function teslimEdilenKalemUyarisiGerekliMi(array $data): bool
    {
        if (! $this->durumTeslimEdilenMi((int) ($this->oncekiServisDurumuId ?? 0))) {
            return false;
        }

        if (! $this->durumTeslimEdilenMi((int) ($data['servis_durumu_id'] ?? 0))) {
            return false;
        }

        $ham = $this->form->getRawState();

        return array_key_exists('kalemler', $data)
            || (is_array($ham) && array_key_exists('kalemler', $ham));
    }

    private function muhasebeSenkronBildirimiEkle(string $mesaj): void
    {
        $mesaj = trim($mesaj);
        if ($mesaj === '' || in_array($mesaj, $this->muhasebeSenkronBildirimleri, true)) {
            return;
        }

        $this->muhasebeSenkronBildirimleri[] = $mesaj;
    }

    private function muhasebeSenkronBildirimleriniGoster(): void
    {
        if ($this->muhasebeSenkronBildirimleri === []) {
            return;
        }

        Notification::make()
            ->title('Muhasebe işlemleri güncellendi')
            ->body(implode(' ', $this->muhasebeSenkronBildirimleri))
            ->success()
            ->send();

        $this->muhasebeSenkronBildirimleri = [];
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    private function teslimBekleyenIlkGecisUyarisiGerekliMi(int $oncekiDurumId, int $yeniDurumId): bool
    {
        if (! $this->durumTeslimBekleyenMi($yeniDurumId)) {
            return false;
        }

        if ($this->durumTeslimBekleyenMi($oncekiDurumId)) {
            return false;
        }

        return ! $this->kayitDahaOnceTeslimBekleyeneGectiMi();
    }

    private function teslimEdildiTelegramBildirimiGerekliMi(int $oncekiDurumId, int $yeniDurumId): bool
    {
        if (! $this->durumTeslimEdilenMi($yeniDurumId)) {
            return false;
        }

        return ! $this->durumTeslimEdilenMi($oncekiDurumId);
    }

    private function kayitDahaOnceTeslimBekleyeneGectiMi(): bool
    {
        $teslimDurumIdleri = $this->teslimBekleyenDurumIdleri();
        if ($teslimDurumIdleri === []) {
            return false;
        }

        return TeknikServisDurumGecmisi::query()
            ->where('teknik_servis_kaydi_id', (int) $this->record->getKey())
            ->whereIn('yeni_servis_durumu_id', $teslimDurumIdleri)
            ->exists();
    }

    /**
     * @return array{kod:string,ad:string,is_fiyat_verildi:bool,is_teslim_edildi:bool,is_iptal:bool,is_iade:bool}|null
     */
    private function servisDurumuTanimi(int $durumId): ?array
    {
        if ($durumId <= 0) {
            return null;
        }

        if (! array_key_exists($durumId, $this->servisDurumuCache)) {
            $this->servisDurumuCache[$durumId] = app(TeknikServisFormSecenekCache::class)->remember(
                TeknikServisFormSecenekCache::GROUP_SERVIS_DURUMU,
                'durum-tanimi:'.$durumId,
                function () use ($durumId): ?array {
                    $durum = TeknikServisDurumTanimi::query()
                    ->select(['id', 'kod', 'ad', 'is_fiyat_verildi', 'is_teslim_edildi', 'is_iptal', 'is_iade'])
                        ->toBase()
                        ->find($durumId);

                    if (! $durum) {
                        return null;
                    }

                    return [
                        'kod' => (string) ($durum->kod ?? ''),
                        'ad' => (string) ($durum->ad ?? ''),
                        'is_fiyat_verildi' => (bool) ($durum->is_fiyat_verildi ?? false),
                        'is_teslim_edildi' => (bool) ($durum->is_teslim_edildi ?? false),
                        'is_iptal' => (bool) ($durum->is_iptal ?? false),
                        'is_iade' => (bool) ($durum->is_iade ?? false),
                    ];
                }
            );
        }

        return $this->servisDurumuCache[$durumId];
    }

    private function durumTeslimBekleyenMi(int $durumId): bool
    {
        $durum = $this->servisDurumuTanimi($durumId);
        if (! $durum) {
            return false;
        }

        return in_array((string) ($durum['kod'] ?? ''), [TeknikServisDurumKodlari::TESLIM_BEKLEYEN, TeknikServisDurumKodlari::TESLIM_BEKLIYOR], true)
            || (string) ($durum['ad'] ?? '') === 'Teslim Bekleyen';
    }

    private function durumTeslimEdilenMi(int $durumId): bool
    {
        $durum = $this->servisDurumuTanimi($durumId);
        if (! $durum) {
            return false;
        }

        return (bool) ($durum['is_teslim_edildi'] ?? false)
            || (string) ($durum['kod'] ?? '') === TeknikServisDurumKodlari::TESLIM_EDILDI
            || (string) ($durum['ad'] ?? '') === 'Teslim Edilen';
    }

    /**
     * @return array<int,int>
     */
    private function teslimBekleyenDurumIdleri(): array
    {
        if ($this->teslimBekleyenDurumIdleriCache !== null) {
            return $this->teslimBekleyenDurumIdleriCache;
        }

        return $this->teslimBekleyenDurumIdleriCache = app(TeknikServisFormSecenekCache::class)->remember(
            TeknikServisFormSecenekCache::GROUP_SERVIS_DURUMU,
            'teslim-bekleyen-idleri',
            fn (): array => TeknikServisDurumTanimi::query()
                ->where(function ($query): void {
                    $query->whereIn('kod', [TeknikServisDurumKodlari::TESLIM_BEKLEYEN, TeknikServisDurumKodlari::TESLIM_BEKLIYOR])
                        ->orWhere('ad', 'Teslim Bekleyen');
                })
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all()
        );
    }

    private function durumGecmisiKaydet(): void
    {
        $onceki = (int) ($this->oncekiServisDurumuId ?? 0);
        $yeni = (int) ($this->record->servis_durumu_id ?? 0);

        if ($yeni <= 0 || $onceki === $yeni) {
            return;
        }

        TeknikServisDurumGecmisi::query()->create([
            'firma_id' => (int) $this->record->firma_id,
            'teknik_servis_kaydi_id' => (int) $this->record->getKey(),
            'onceki_servis_durumu_id' => $onceki > 0 ? $onceki : null,
            'yeni_servis_durumu_id' => $yeni,
            'degisim_notu' => 'Servis düzenleme ekranından durum güncellemesi.',
            'degistiren_id' => Auth::id(),
            'degisim_tarihi' => now(),
        ]);
    }

    /**
     * @param array<string,mixed> $ham
     */
    private function teslimBekleyenMesajOnayiGoster(array $ham): void
    {
        $url = $this->teslimBekleyenWhatsappUrl($ham);
        if (! $url) {
            return;
        }

        Notification::make()
            ->title('WhatsApp mesajı göndermek istiyor musunuz?')
            ->body('Servis durumu ilk kez Teslim Bekleyen olarak kaydedildi.')
            ->warning()
            ->seconds(15)
            ->actions([
                NotificationAction::make('evet')
                    ->label('Evet, gönderime geç')
                    ->button()
                    ->color('success')
                    ->alpineClickHandler('close(); setTimeout(() => window.location.reload(), 750)')
                    ->url($url, shouldOpenInNewTab: true),
                NotificationAction::make('hayir')
                    ->label('Hayır')
                    ->button()
                    ->color('gray')
                    ->extraAttributes(['class' => 'teknik-servis-whatsapp-bildirim-hayir'])
                    ->alpineClickHandler('window.dispatchEvent(new CustomEvent("yk-close-filament-notification", { detail: { source: $el } }))'),
            ])
            ->send();
    }

    /**
     * @param array<string,mixed> $ham
     */
    private function teslimBekleyenWhatsappUrl(array $ham): ?string
    {
        $sablonKodu = trim((string) ($ham['whatsapp_sablon_kodu'] ?? 'teslim_bekleyen_mesaji'));
        $telefon = $this->normalizeTelefon(
            (string) ($ham['musteri_tel'] ?? $this->record->musteri_tel ?? $this->record->cari?->telefon ?? $this->record->cari?->gsm ?? '')
        );

        if (! $telefon) {
            return null;
        }

        $sablon = TeknikServisMesajSablonu::query()
            ->where('kanal', 'whatsapp')
            ->where('aktif', true)
            ->where('kod', $sablonKodu)
            ->first();

        if (! $sablon) {
            $sablon = TeknikServisMesajSablonu::query()
                ->where('kanal', 'whatsapp')
                ->where('aktif', true)
                ->where('kod', 'teslim_bekleyen_mesaji')
                ->first();
        }

        $mesaj = trim((string) ($sablon?->mesaj ?? ''));
        if ($mesaj === '') {
            $mesaj = 'Merhaba Sayin Musterimiz, cihaziniza ait servis islemleri tamamlanmis olup cihaziniz teslime hazirdir.';
        }

        $stokKartlari = $this->stokKartlariMetni((array) ($ham['kalemler'] ?? []));
        $markaModel = trim((string) (($this->record->marka?->ad ?? '').' '.($this->record->model_no ?? '')));

        $mesaj = strtr($mesaj, [
            '{cari_ad}' => (string) ($this->record->musteri_ad_soyad ?: $this->record->cari?->ad ?: '-'),
            '{cari_tel}' => (string) ($this->record->musteri_tel ?: $this->record->cari?->telefon ?: $this->record->cari?->gsm ?: '-'),
            '{fis_no}' => (string) ($this->record->fis_no ?: '-'),
            '{cihaz}' => (string) ($this->record->cihaz?->ad ?: '-'),
            '{marka_model}' => $markaModel !== '' ? $markaModel : '-',
            '{ariza_bilgisi}' => (string) ($this->record->ariza?->ad ?: $this->record->musteri_sikayeti ?: '-'),
            '{musteriye_gorunen_not}' => (string) ($this->record->musteriye_gorunen_not ?: '-'),
            '{stok_kartlari}' => $stokKartlari,
            '{teslim_tarihi}' => now()->format('d.m.Y'),
        ]);

        return 'https://wa.me/'.$telefon.'?text='.urlencode($mesaj);
    }

    /**
     * @param array<int,mixed> $kalemler
     */
    private function stokKartlariMetni(array $kalemler): string
    {
        $satirlar = [];

        foreach ($kalemler as $kalem) {
            if (! is_array($kalem)) {
                continue;
            }

            $stokId = (int) ($kalem['stok_id'] ?? 0);
            $aciklama = trim((string) ($kalem['aciklama'] ?? ''));
            $miktar = (float) ($kalem['miktar'] ?? 0);

            $stokAdi = '';
            if ($stokId > 0) {
                $stok = StokKarti::query()->find($stokId);
                $stokAdi = trim((string) ($stok?->ad ?? ''));
            }

            $ad = $stokAdi !== '' ? $stokAdi : $aciklama;
            if ($ad === '') {
                continue;
            }

            $satirlar[] = $ad.($miktar > 0 ? ' x'.rtrim(rtrim(number_format($miktar, 2, '.', ''), '0'), '.') : '');
        }

        return $satirlar !== [] ? implode(', ', $satirlar) : '-';
    }

    private function normalizeTelefon(string $telefon): ?string
    {
        $telefon = preg_replace('/\D+/', '', $telefon) ?? '';
        if ($telefon === '') {
            return null;
        }

        if (str_starts_with($telefon, '0')) {
            $telefon = '90'.substr($telefon, 1);
        } elseif (! str_starts_with($telefon, '90')) {
            $telefon = '90'.$telefon;
        }

        return strlen($telefon) >= 11 ? $telefon : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function bakimHatirlatmasiniSenkronla(TeknikServisKaydi $kayit, array $data): void
    {
        $bakimTarihi = $data['bakim_tarihi'] ?? null;

        if (blank($bakimTarihi)) {
            TeknikServisHatirlatma::query()
                ->where('teknik_servis_kaydi_id', (int) $kayit->getKey())
                ->where('hatirlatma_tipi', 'bakim')
                ->where('durum', '!=', 'pasif')
                ->update(['durum' => 'pasif']);

            return;
        }

        $tarih = Carbon::parse((string) $bakimTarihi)->toDateString();
        $periyotAy = (int) ($data['bakim_periyot_ay'] ?? 0);

        if ($periyotAy < 1) {
            $bugun = Carbon::today();
            $secili = Carbon::parse((string) $bakimTarihi)->startOfDay();
            $periyotAy = max(1, $bugun->diffInMonths($secili, false));
        }

        $kimlik = [
            'firma_id' => (int) $kayit->firma_id,
            'teknik_servis_kaydi_id' => (int) $kayit->getKey(),
            'hatirlatma_tipi' => 'bakim',
        ];
        $degerler = [
            'periyot_ay' => $periyotAy,
            'ilk_tarih' => $tarih,
            'sonraki_tarih' => $tarih,
            'durum' => 'aktif',
            'not' => null,
            'olusturan_id' => Auth::id(),
        ];

        $mevcut = TeknikServisHatirlatma::query()->where($kimlik)->first();
        if ($mevcut && $this->bakimHatirlatmasiAyniMi($mevcut, $degerler)) {
            return;
        }

        TeknikServisHatirlatma::query()->updateOrCreate($kimlik, $degerler);
    }

    /**
     * @param array<string, mixed> $degerler
     */
    private function bakimHatirlatmasiAyniMi(TeknikServisHatirlatma $hatirlatma, array $degerler): bool
    {
        return (int) ($hatirlatma->periyot_ay ?? 0) === (int) $degerler['periyot_ay']
            && optional($hatirlatma->ilk_tarih)->toDateString() === (string) $degerler['ilk_tarih']
            && optional($hatirlatma->sonraki_tarih)->toDateString() === (string) $degerler['sonraki_tarih']
            && (string) ($hatirlatma->durum ?? '') === (string) $degerler['durum']
            && ($hatirlatma->not ?? null) === ($degerler['not'] ?? null)
            && (int) ($hatirlatma->olusturan_id ?? 0) === (int) ($degerler['olusturan_id'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fiyatVerilenGecisiniDogrula(array $data): void
    {
        $durumId = (int) ($data['servis_durumu_id'] ?? 0);
        $durum = $this->servisDurumuTanimi($durumId);
        if (! $durum) {
            return;
        }

        $fiyatVerilenMi = (bool) ($durum['is_fiyat_verildi'] ?? false)
            || in_array((string) ($durum['kod'] ?? ''), [TeknikServisDurumKodlari::FIYAT_VERILDI, 'Fiyat Verilen'], true)
            || (string) ($durum['ad'] ?? '') === 'Fiyat Verilen';

        if (! $fiyatVerilenMi) {
            return;
        }

        $hatalar = [];
        $teklifTutari = $data['teklif_tutari'] ?? $this->record?->teklif_tutari ?? null;
        $teklifTarihi = $data['teklif_tarihi'] ?? $this->record?->teklif_tarihi ?? null;

        if (blank($teklifTutari)) {
            $hatalar['data.teklif_tutari'] = "Durum 'Fiyat Verilen' için teklif tutarı zorunludur.";
        }

        if (blank($teklifTarihi)) {
            $hatalar['data.teklif_tarihi'] = "Durum 'Fiyat Verilen' için teklif tarihi zorunludur.";
        }

        if ($hatalar !== []) {
            Notification::make()
                ->title("Eksik alanlar var")
                ->body("Fiyat Verilen durumuna geçmek için Teklif Tutarı ve Teklif Tarihi alanlarını doldurmalısınız.")
                ->danger()
                ->send();
            throw ValidationException::withMessages($hatalar);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function teslimDurumuGecisiniDogrula(array $data): void
    {
        $durumId = (int) ($data['servis_durumu_id'] ?? 0);
        $durum = $this->servisDurumuTanimi($durumId);
        if (! $durum) {
            return;
        }

        $kod = (string) ($durum['kod'] ?? '');
        $ad = (string) ($durum['ad'] ?? '');

        $teslimBekleyenMi = in_array($kod, [TeknikServisDurumKodlari::TESLIM_BEKLEYEN, TeknikServisDurumKodlari::TESLIM_BEKLIYOR], true)
            || $ad === 'Teslim Bekleyen';
        $teslimEdilenMi = (bool) ($durum['is_teslim_edildi'] ?? false)
            || $kod === TeknikServisDurumKodlari::TESLIM_EDILDI
            || $ad === 'Teslim Edilen';
        $oncekiTeslimEdilenMi = $this->durumTeslimEdilenMi((int) ($this->oncekiServisDurumuId ?? 0));

        if (! $teslimBekleyenMi && ! $teslimEdilenMi) {
            return;
        }

        $kalemler = $this->formKalemleriniAl($data);

        $gecerliKalemVar = false;
        foreach ($kalemler as $kalem) {
            if (
                ! blank($kalem['stok_id'] ?? null)
                && ! blank($kalem['aciklama'] ?? null)
                && (float) ($kalem['miktar'] ?? 0) > 0
                && ! blank($kalem['birim_fiyat'] ?? null)
                && ! blank($kalem['kdv_orani'] ?? null)
                && ! blank($kalem['para_birimi'] ?? null)
            ) {
                $gecerliKalemVar = true;
                break;
            }
        }

        $hatalar = [];
        if (! $gecerliKalemVar) {
            $hatalar['data.kalemler'] = "Bu duruma ge\u{00E7}mek i\u{00E7}in en az bir ge\u{00E7}erli stok kalemi eklemelisiniz.";
        }

        if ($teslimEdilenMi) {
            $teslimTarihi = $data['teslim_tarihi'] ?? $this->record?->teslim_tarihi ?? null;
            if (blank($teslimTarihi)) {
                $hatalar['data.teslim_tarihi'] = "Durum 'Teslim Edilen' için teslim tarihi zorunludur.";
            }

            $servisId = (int) ($this->record?->getKey() ?? 0);
            $firmaId = (int) ($this->record?->firma_id ?? 0);
            if ($servisId > 0 && $firmaId > 0) {
                $kalemOzeti = TeknikServisKayitFormSchema::stokOzetHesaplaKalemDizisi($kalemler);
                $kontrol = app(TeknikServisAlacakOzetServisi::class)->teslimKontrolu($this->record, [
                    'toplam_tutar' => (float) ($kalemOzeti['odenecek_tutar'] ?? $data['toplam_tutar'] ?? $this->record->toplam_tutar ?? 0),
                    'para_birimi' => (string) ($data['tahsilat_para_birimi'] ?? $kalemOzeti['para_birimi'] ?? $this->record->tahsilat_para_birimi ?? 'TRY'),
                ]);

                if ((bool) ($kontrol['engellendi'] ?? false)) {
                    $hatalar['data.tahsilat'] = (string) ($kontrol['mesaj'] ?? 'Teslim için açık tutar tahsil edilmeli veya ödeme planına bağlanmalıdır.');
                } elseif ((bool) ($kontrol['uyari'] ?? false)) {
                    $this->teslimAcikAlacakUyarisiMesaji = (string) ($kontrol['mesaj'] ?? '');
                }
            } else {
                $hatalar['data.tahsilat'] = "Durum 'Teslim Edilen' için tahsilat bilgisi zorunludur.";
            }

            if (! $oncekiTeslimEdilenMi) {
                $stokYetersizlikMesajlari = $this->teslimEdilenIcinStokKontrolleriniDogrula($kalemler);
                if ($stokYetersizlikMesajlari !== []) {
                    $hatalar['data.kalemler'] = implode(' ', $stokYetersizlikMesajlari);
                }
            }
        }

        if ($hatalar !== []) {
            $bildirimGovdesi = "Seçtiğiniz servis durumuna geçmek için gerekli bilgileri tamamlayın.";
            $ilkHata = reset($hatalar);
            if (is_string($ilkHata) && $ilkHata !== '') {
                $bildirimGovdesi = $ilkHata;
            }
            Notification::make()
                ->title("Zorunlu bilgiler eksik")
                ->body($bildirimGovdesi)
                ->danger()
                ->send();
            throw ValidationException::withMessages($hatalar);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $kalemler
     * @return array<int,string>
     */
    private function teslimEdilenIcinStokKontrolleriniDogrula(array $kalemler): array
    {
        $mesajlar = [];

        foreach ($kalemler as $kalem) {
            $stokId = (int) ($kalem['stok_id'] ?? 0);
            $miktar = (float) ($kalem['miktar'] ?? 0);

            if ($stokId < 1 || $miktar <= 0) {
                continue;
            }

            $stok = StokKarti::query()
                ->select(['id', 'kod', 'ad', 'stok_takip', 'stok_miktari', 'rezerve_miktar', 'negative_flag'])
                ->find($stokId);

            if (! $stok) {
                $mesajlar[] = 'Seçilen stok kalemlerinden biri artık bulunamadığı için teslim işlemi tamamlanamadı.';
                continue;
            }

            if (! (bool) ($stok->stok_takip ?? true)) {
                continue;
            }

            if ((bool) ($stok->negative_flag ?? false)) {
                continue;
            }

            $musaitMiktar = (float) $stok->musaitStokMiktari();
            if ($musaitMiktar + 0.0001 >= $miktar) {
                continue;
            }

            $stokEtiketi = trim(implode(' - ', array_filter([
                trim((string) ($stok->kod ?? '')),
                trim((string) ($stok->ad ?? '')),
            ])));

            if ($stokEtiketi === '') {
                $stokEtiketi = 'Seçili stok kalemi';
            }

            $mesajlar[] = sprintf(
                '%s için yeterli stok yok. Gerekli: %s, müsait: %s.',
                $stokEtiketi,
                $this->formatMiktarMesaji($miktar),
                $this->formatMiktarMesaji($musaitMiktar)
            );
        }

        return array_values(array_unique($mesajlar));
    }

    private function formatMiktarMesaji(float $miktar): string
    {
        $formatli = number_format($miktar, 4, '.', '');
        $formatli = rtrim(rtrim($formatli, '0'), '.');

        return $formatli !== '' ? $formatli : '0';
    }

    private function enumDegeri(mixed $deger): string
    {
        return $deger instanceof \BackedEnum ? (string) $deger->value : (string) $deger;
    }

    private function teknikServisIcinMuhasebeHataMesajiniHazirla(string $mesaj): string
    {
        if (str_contains($mesaj, 'Bu işlem stok miktarını negatife düşürür')) {
            return 'Teslim işlemi tamamlanamadı çünkü seçilen stok kalemlerinden biri yeterli miktarda mevcut değil. Lütfen stok miktarını kontrol edin.';
        }

        return $mesaj;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function servisDurumunaGoreBekleyenFaturaSenkronla(array $data): void
    {
        $durumId = (int) ($data['servis_durumu_id'] ?? 0);
        $durum = $this->servisDurumuTanimi($durumId);
        if (! $durum) {
            return;
        }

        $kod = (string) ($durum['kod'] ?? '');
        $ad = (string) ($durum['ad'] ?? '');
        $teslimBekleyenMi = in_array($kod, [TeknikServisDurumKodlari::TESLIM_BEKLEYEN, TeknikServisDurumKodlari::TESLIM_BEKLIYOR], true)
            || $ad === 'Teslim Bekleyen';
        $teslimEdilenMi = (bool) ($durum['is_teslim_edildi'] ?? false)
            || $kod === TeknikServisDurumKodlari::TESLIM_EDILDI
            || $ad === 'Teslim Edilen';
        $iptalMi = (bool) ($durum['is_iptal'] ?? false) || $kod === TeknikServisDurumKodlari::IPTAL || $ad === 'İptal' || $ad === 'Iptal';
        $iadeMi = (bool) ($durum['is_iade'] ?? false) || $kod === TeknikServisDurumKodlari::IADE || $ad === 'İade' || $ad === 'Iade';

        if ($teslimBekleyenMi) {
            if ($this->bekleyenFaturaSenkronuAtlanabilirMi($data, false)) {
                $this->bekleyenDurumdaTahsilatiSenkronla($data);
                return;
            }

            $this->bekleyenFaturaOlusturVeyaGuncelle($data);
            $this->bekleyenDurumdaTahsilatiSenkronla($data);
            return;
        }

        if ($teslimEdilenMi) {
            if ($this->bekleyenFaturaSenkronuAtlanabilirMi($data, true)) {
                $this->bekleyenDurumdaTahsilatiSenkronla($data);
                return;
            }

            $this->bekleyenFaturaOlusturVeyaGuncelle($data);
            $this->bekleyenDurumdaTahsilatiSenkronla($data);
            $this->teslimEdilenIcinFaturayiOnayla($data);
            return;
        }

        if (($iptalMi || $iadeMi) && (int) ($this->oncekiServisDurumuId ?? 0) === $durumId) {
            return;
        }

        if ($iptalMi) {
            $this->bagliBekleyenFaturayiIptalEt('Servis kaydi iptal edildi');
            $this->bagliTahsilatiTersle('iptal');
            return;
        }

        if ($iadeMi) {
            $this->bagliBekleyenFaturayiIptalEt('Servis kaydi iade edildi');
            $this->bagliTahsilatiTersle('iade');
            return;
        }

        if ($this->bekleyenFaturaSenkronuAtlanabilirMi($data, false)) {
            $this->bekleyenDurumdaTahsilatiSenkronla($data);
            return;
        }

        $this->bekleyenFaturaOlusturVeyaGuncelle($data);
        $this->bekleyenDurumdaTahsilatiSenkronla($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function bekleyenFaturaSenkronuAtlanabilirMi(array $data, bool $teslimEdilenMi): bool
    {
        $servis = $this->record;
        if (! $servis) {
            return true;
        }

        $firmaId = (int) $servis->firma_id;
        $servisId = (int) $servis->getKey();
        $cariId = (int) ($data['cari_id'] ?? $servis->cari_id ?? 0);
        if ($firmaId < 1 || $servisId < 1 || $cariId < 1) {
            return true;
        }

        $kalemler = $this->formKalemleriniAl($data);
        if ($kalemler === []) {
            return true;
        }

        $baglanti = TeknikServisMuhasebeBaglantisi::query()
            ->where('firma_id', $firmaId)
            ->where('teknik_servis_kaydi_id', $servisId)
            ->where('islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
            ->orderByDesc('id')
            ->first();

        if (! $baglanti || ! $baglanti->satis_faturasi_id) {
            return false;
        }

        if ($teslimEdilenMi && $this->enumDegeri($baglanti->senkron_durumu) !== TeknikServisMuhasebeSenkronDurumu::Basarili->value) {
            return false;
        }

        if (! $teslimEdilenMi && $this->enumDegeri($baglanti->senkron_durumu) !== TeknikServisMuhasebeSenkronDurumu::Beklemede->value) {
            return false;
        }

        $fatura = Fatura::query()
            ->where('firma_id', $firmaId)
            ->whereKey((int) $baglanti->satis_faturasi_id)
            ->first();

        if (! $fatura) {
            return false;
        }

        $ozet = $this->faturaIcinKalemVeToplamHazirla($kalemler);
        if ($ozet['kalemler'] === []) {
            return true;
        }

        $paraBirimi = strtoupper((string) ($data['tahsilat_para_birimi'] ?? $ozet['para_birimi'] ?? 'TRY'));
        $faturaAlanlari = $this->bekleyenFaturaAlanlari($servis, $firmaId, $cariId, $data, $ozet, $paraBirimi);

        if ($teslimEdilenMi) {
            $faturaAlanlari['tur'] = FaturaTuru::Giden->value;
            $faturaAlanlari['durum'] = FaturaDurumu::Onayli->value;
        }

        $kontrol = app(TeknikServisBekleyenFaturaSenkronKontrolu::class);

        return $kontrol->faturaVeKalemlerAyniMi($fatura, $faturaAlanlari, $ozet['kalemler'], $firmaId, $paraBirimi)
            && ! $kontrol->tahsilatFaturaBaglantisiEksikMi($firmaId, $servisId, (int) $fatura->getKey());
    }

    /**
     * @param array<string,mixed> $data
     */
    private function bekleyenFaturaOlusturVeyaGuncelle(array $data): void
    {
        $servis = $this->record;
        if (! $servis) {
            return;
        }

        $firmaId = (int) $servis->firma_id;
        $cariId = (int) ($data['cari_id'] ?? $servis->cari_id ?? 0);
        if ($firmaId < 1 || $cariId < 1) {
            return;
        }

        $kalemler = $this->formKalemleriniAl($data);
        if ($kalemler === []) {
            return;
        }

        DB::transaction(function () use ($servis, $firmaId, $cariId, $kalemler, $data): void {
            $baglanti = TeknikServisMuhasebeBaglantisi::query()
                ->where('firma_id', $firmaId)
                ->where('teknik_servis_kaydi_id', (int) $servis->getKey())
                ->where('islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $fatura = null;
            if ($baglanti && $baglanti->satis_faturasi_id) {
                $fatura = Fatura::query()
                    ->where('firma_id', $firmaId)
                    ->whereKey((int) $baglanti->satis_faturasi_id)
                    ->lockForUpdate()
                    ->first();
            }

            $ozet = $this->faturaIcinKalemVeToplamHazirla($kalemler);
            if ($ozet['kalemler'] === []) {
                return;
            }

            $paraBirimi = strtoupper((string) ($data['tahsilat_para_birimi'] ?? $ozet['para_birimi'] ?? 'TRY'));
            $tarih = $data['kabul_tarihi'] ?? $data['tahsilat_tarihi'] ?? now();
            $faturaAlanlari = [
                'firma_id' => $firmaId,
                'cari_id' => $cariId,
                'tur' => FaturaTuru::BekleyenFatura->value,
                'durum' => FaturaDurumu::Beklemede->value,
                'tarih' => Carbon::parse((string) $tarih),
                'para_birimi' => $paraBirimi,
                'ara_toplam' => $ozet['ara_toplam'],
                'toplam_indirim' => $ozet['toplam_indirim'],
                'kdv_toplam' => $ozet['kdv_toplam'],
                'genel_toplam' => $ozet['genel_toplam'],
                'odenecek_tutar' => $ozet['genel_toplam'],
                'odendi_tutari' => '0.00',
                'acik_tutar' => $ozet['genel_toplam'],
                'odeme_durumu' => 'beklemede',
                'aciklama' => 'Teknik servis kaydı #'.(int) $servis->getKey().' için otomatik bekleyen fatura.',
                'kaynak_tipi' => 'teknik_servis',
            ];

            $faturaAlanlari = $this->bekleyenFaturaAlanlari($servis, $firmaId, $cariId, $data, $ozet, $paraBirimi);

            $faturaYeniOlustu = ! $fatura;

            if ($faturaYeniOlustu) {
                $fatura = Fatura::query()->create($faturaAlanlari);
            } else {
                $fatura->update($faturaAlanlari);
            }

            $this->muhasebeSenkronBildirimiEkle($faturaYeniOlustu
                ? 'Bekleyen fatura oluşturuldu.'
                : 'Bekleyen fatura güncellendi.');

            TeknikServisTahsilati::query()
                ->where('firma_id', $firmaId)
                ->where('teknik_servis_kaydi_id', (int) $servis->getKey())
                ->update(['satis_faturasi_id' => (int) $fatura->getKey()]);

            FaturaKalemi::query()->where('fatura_id', (int) $fatura->getKey())->delete();
            foreach ($ozet['kalemler'] as $satir) {
                FaturaKalemi::query()->create(array_merge($satir, [
                    'fatura_id' => (int) $fatura->getKey(),
                    'firma_id' => $firmaId,
                    'para_birimi' => $paraBirimi,
                    'baz_para_birimi' => 'TRY',
                ]));
            }

            $idem = 'teknik_servis:'.(int) $servis->getKey().':bekleyen_fatura';
            TeknikServisMuhasebeBaglantisi::query()->updateOrCreate(
                ['firma_id' => $firmaId, 'idempotency_key' => $idem],
                [
                    'teknik_servis_kaydi_id' => (int) $servis->getKey(),
                    'islem_tipi' => TeknikServisMuhasebeIslemTipi::Satis->value,
                    'satis_faturasi_id' => (int) $fatura->getKey(),
                    'senkron_durumu' => TeknikServisMuhasebeSenkronDurumu::Beklemede->value,
                    'son_senkron_tarihi' => now(),
                    'hata_mesaji' => null,
                ]
            );
        });
    }

    /**
     * @param array<string,mixed> $data
     * @return array<int,array<string,mixed>>
     */
    private function formKalemleriniAl(array $data): array
    {
        $ham = $this->form->getRawState();

        if (is_array($ham) && array_key_exists('kalemler', $ham)) {
            return array_values(array_filter((array) $ham['kalemler'], fn ($k): bool => is_array($k)));
        }

        if (array_key_exists('kalemler', $data)) {
            return array_values(array_filter((array) $data['kalemler'], fn ($k): bool => is_array($k)));
        }

        return $this->mevcutKalemleriFormDizisi();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function mevcutKalemleriFormDizisi(): array
    {
        $servis = $this->record;
        if (! $servis) {
            return [];
        }

        $kalemler = $servis->relationLoaded('kalemler')
            ? $servis->kalemler
            : $servis->kalemler()->orderBy('satir_no')->orderBy('id')->get();

        return $kalemler
            ->map(static fn ($kalem): array => [
                'stok_id' => (int) ($kalem->stok_id ?? 0),
                'aciklama' => (string) ($kalem->aciklama ?? ''),
                'miktar' => (float) ($kalem->miktar ?? 0),
                'birim' => (string) ($kalem->birim ?? 'AD'),
                'birim_fiyat' => (float) ($kalem->birim_fiyat ?? 0),
                'iskonto_orani' => (float) ($kalem->iskonto_orani ?? 0),
                'iskonto_tutari' => (float) ($kalem->iskonto_tutari ?? 0),
                'kdv_orani' => (float) ($kalem->kdv_orani ?? 0),
                'kdv_tutari' => (float) ($kalem->kdv_tutari ?? 0),
                'para_birimi' => (string) ($kalem->para_birimi ?? 'TRY'),
            ])
            ->all();
    }

    /**
     * @param array<string,mixed> $data
     * @param array{para_birimi:string,ara_toplam:string,toplam_indirim:string,kdv_toplam:string,genel_toplam:string,kalemler:array<int,array<string,mixed>>} $ozet
     * @return array<string,mixed>
     */
    private function bekleyenFaturaAlanlari(TeknikServisKaydi $servis, int $firmaId, int $cariId, array $data, array $ozet, string $paraBirimi): array
    {
        $tarih = $data['kabul_tarihi'] ?? $data['tahsilat_tarihi'] ?? now();

        return [
            'firma_id' => $firmaId,
            'cari_id' => $cariId,
            'tur' => FaturaTuru::BekleyenFatura->value,
            'durum' => FaturaDurumu::Beklemede->value,
            'tarih' => Carbon::parse((string) $tarih),
            'para_birimi' => $paraBirimi,
            'ara_toplam' => $ozet['ara_toplam'],
            'toplam_indirim' => $ozet['toplam_indirim'],
            'kdv_toplam' => $ozet['kdv_toplam'],
            'genel_toplam' => $ozet['genel_toplam'],
            'odenecek_tutar' => $ozet['genel_toplam'],
            'odendi_tutari' => '0.00',
            'acik_tutar' => $ozet['genel_toplam'],
            'odeme_durumu' => 'beklemede',
            'aciklama' => 'Teknik servis kaydı #'.(int) $servis->getKey().' için otomatik bekleyen fatura.',
            'kaynak_tipi' => 'teknik_servis',
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function teslimEdilenIcinFaturayiOnayla(array $data): void
    {
        $servis = $this->record;
        if (! $servis) {
            return;
        }

        $firmaId = (int) $servis->firma_id;
        $servisId = (int) $servis->getKey();

        DB::transaction(function () use ($servis, $data, $firmaId, $servisId): void {
            $satisBaglantisi = TeknikServisMuhasebeBaglantisi::query()
                ->where('firma_id', $firmaId)
                ->where('teknik_servis_kaydi_id', $servisId)
                ->where('islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $satisBaglantisi || ! $satisBaglantisi->satis_faturasi_id) {
                throw ValidationException::withMessages([
                    'data.servis_durumu_id' => 'Teslim Edilen için bağlı fatura bulunamadı.',
                ]);
            }

            $fatura = Fatura::query()
                ->where('firma_id', $firmaId)
                ->whereKey((int) $satisBaglantisi->satis_faturasi_id)
                ->lockForUpdate()
                ->first();
            if (! $fatura) {
                throw ValidationException::withMessages([
                    'data.servis_durumu_id' => 'Teslim Edilen için bağlı fatura bulunamadı.',
                ]);
            }

            $faturaTuru = $fatura->tur instanceof FaturaTuru ? $fatura->tur->value : (string) $fatura->tur;
            $faturaDurumu = $fatura->durum instanceof FaturaDurumu ? $fatura->durum->value : (string) $fatura->durum;

            if (
                $faturaTuru === FaturaTuru::Giden->value
                && $faturaDurumu === FaturaDurumu::Onayli->value
                && $this->enumDegeri($satisBaglantisi->senkron_durumu) === TeknikServisMuhasebeSenkronDurumu::Basarili->value
            ) {
                return;
            }

            if ($faturaTuru !== FaturaTuru::Giden->value) {
                $fatura->update([
                    'tur' => FaturaTuru::Giden->value,
                ]);
            }

            try {
                if ($fatura->durum !== FaturaDurumu::Onayli) {
                    app(FaturaIslemServisi::class)->faturayiOnayla($fatura);
                }
            } catch (IsKuraliIstisnasi $e) {
                $mesaj = $this->teknikServisIcinMuhasebeHataMesajiniHazirla($e->getMessage());

                Notification::make()
                    ->title('Teslim işlemi tamamlanamadı')
                    ->body($mesaj)
                    ->danger()
                    ->send();

                throw ValidationException::withMessages([
                    'data.servis_durumu_id' => $mesaj,
                ]);
            }

            $satisBaglantisi->update([
                'senkron_durumu' => TeknikServisMuhasebeSenkronDurumu::Basarili->value,
                'son_senkron_tarihi' => now(),
                'hata_mesaji' => null,
            ]);

            $this->muhasebeSenkronBildirimiEkle('Fatura giden fatura olarak onaylandı.');
        });
    }

    /**
     * @param array<string,mixed> $data
     */
    private function bekleyenDurumdaTahsilatiSenkronla(array $data): void
    {
        $servis = $this->record;
        if (! $servis) {
            return;
        }

        $kanal = (string) ($data['tahsilat_kanali'] ?? '');
        $tutar = (float) ($data['tahsilat_tutari'] ?? 0);
        if ($kanal === '' || $tutar <= 0) {
            return;
        }

        $firmaId = (int) $servis->firma_id;
        $servisId = (int) $servis->getKey();

        DB::transaction(function () use ($servis, $data, $firmaId, $servisId): void {
            $satisBaglantisi = TeknikServisMuhasebeBaglantisi::query()
                ->where('firma_id', $firmaId)
                ->where('teknik_servis_kaydi_id', $servisId)
                ->where('islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $satisBaglantisi || ! $satisBaglantisi->satis_faturasi_id) {
                return;
            }

            $fatura = Fatura::query()
                ->where('firma_id', $firmaId)
                ->whereKey((int) $satisBaglantisi->satis_faturasi_id)
                ->lockForUpdate()
                ->first();
            if (! $fatura) {
                return;
            }

            $tahsilatIdempotency = 'teknik_servis:'.$servisId.':tahsilat';
            $tahsilatBaglantisi = TeknikServisMuhasebeBaglantisi::query()
                ->where('firma_id', $firmaId)
                ->where('idempotency_key', $tahsilatIdempotency)
                ->lockForUpdate()
                ->first();

            if ($tahsilatBaglantisi && $tahsilatBaglantisi->finans_hareketi_id) {
                $tahsilatBaglantisi->update([
                    'satis_faturasi_id' => (int) $fatura->getKey(),
                    'senkron_durumu' => TeknikServisMuhasebeSenkronDurumu::Beklemede->value,
                    'son_senkron_tarihi' => now(),
                    'hata_mesaji' => null,
                ]);

                $this->muhasebeSenkronBildirimiEkle('Tahsilat faturaya bağlandı.');
                return;
            }

            $hesapId = match ((string) ($data['tahsilat_kanali'] ?? '')) {
                'kasa' => (int) ($data['tahsilat_kasa_hesap_id'] ?? 0),
                'banka' => (int) ($data['tahsilat_banka_hesap_id'] ?? 0),
                'pos' => (int) ($data['tahsilat_pos_hesap_id'] ?? 0),
                default => 0,
            };

            if ($hesapId < 1) {
                return;
            }

            $cariId = (int) ($data['cari_id'] ?? $servis->cari_id ?? 0);
            if ($cariId < 1) {
                return;
            }

            $kaynakPb = strtoupper((string) ($data['tahsilat_para_birimi'] ?? 'TRY'));
            $hedefPb = strtoupper((string) ($data['tahsilat_hedef_para_birimi'] ?? $kaynakPb));
            $kaynakTutar = number_format((float) ($data['tahsilat_tutari'] ?? 0), 2, '.', '');
            $kur = number_format((float) ($data['tahsilat_doviz_kuru'] ?? 0), 8, '.', '');
            $tarih = Carbon::parse((string) ($data['tahsilat_tarihi'] ?? now()->format('Y-m-d H:i:s')));
            $aciklama = trim((string) ($data['tahsilat_aciklama'] ?? ''));
            if ($aciklama === '') {
                $aciklama = 'Teknik servis #'.$servisId.' bekleyen tahsilati';
            }

            $finansServisi = app(FinansHareketServisi::class);
            try {
                if ($kaynakPb === $hedefPb) {
                    $sonuc = match ((string) ($data['tahsilat_kanali'] ?? '')) {
                        'kasa' => $finansServisi->tahsilatKasadanKaydet(
                            $firmaId,
                            $cariId,
                            $hesapId,
                            $kaynakTutar,
                            $kaynakPb,
                            $tarih,
                            $aciklama,
                            'fatura',
                            (int) $fatura->getKey()
                        ),
                        'banka' => $finansServisi->tahsilatBankadanKaydet(
                            $firmaId,
                            $cariId,
                            $hesapId,
                            $kaynakTutar,
                            $kaynakPb,
                            $tarih,
                            $aciklama,
                            'fatura',
                            (int) $fatura->getKey()
                        ),
                        'pos' => $finansServisi->tahsilatPosKaydet(
                            $firmaId,
                            $cariId,
                            $hesapId,
                            $kaynakTutar,
                            $kaynakPb,
                            $tarih,
                            $aciklama,
                            'fatura',
                            (int) $fatura->getKey()
                        ),
                        default => null,
                    };
                } else {
                    if ((float) $kur <= 0) {
                        return;
                    }

                    $hedefTutar = number_format((float) ($data['tahsilat_hedef_tutar'] ?? 0), 2, '.', '');
                    if ((float) $hedefTutar <= 0) {
                        $hedefTutar = ($kaynakPb === 'TRY' && $hedefPb !== 'TRY')
                            ? number_format((float) bcdiv($kaynakTutar, $kur, 2), 2, '.', '')
                            : number_format((float) bcmul($kaynakTutar, $kur, 2), 2, '.', '');
                    }

                    $sonuc = $finansServisi->tahsilatKurIleKaydet(
                        $firmaId,
                        $cariId,
                        (string) ($data['tahsilat_kanali'] ?? ''),
                        $hesapId,
                        $kaynakTutar,
                        $kaynakPb,
                        $hedefTutar,
                        $hedefPb,
                        $kur,
                        $tarih,
                        $aciklama,
                        'fatura',
                        (int) $fatura->getKey()
                    );
                }
            } catch (IsKuraliIstisnasi) {
                return;
            }

            if (! is_array($sonuc) || ! isset($sonuc['finans'])) {
                return;
            }

            TeknikServisMuhasebeBaglantisi::query()->updateOrCreate(
                ['firma_id' => $firmaId, 'idempotency_key' => $tahsilatIdempotency],
                [
                    'teknik_servis_kaydi_id' => $servisId,
                    'islem_tipi' => TeknikServisMuhasebeIslemTipi::Tahsilat->value,
                    'satis_faturasi_id' => (int) $fatura->getKey(),
                    'finans_hareketi_id' => (int) $sonuc['finans']->getKey(),
                    'senkron_durumu' => TeknikServisMuhasebeSenkronDurumu::Beklemede->value,
                    'son_senkron_tarihi' => now(),
                    'hata_mesaji' => null,
                ]
            );

            $this->muhasebeSenkronBildirimiEkle('Tahsilat faturaya bağlandı.');
        });
    }

    private function bagliBekleyenFaturayiIptalEt(string $mesaj): void
    {
        $servis = $this->record;
        if (! $servis) {
            return;
        }

        $baglanti = TeknikServisMuhasebeBaglantisi::query()
            ->where('firma_id', (int) $servis->firma_id)
            ->where('teknik_servis_kaydi_id', (int) $servis->getKey())
            ->where('islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
            ->orderByDesc('id')
            ->first();
        if (! $baglanti || ! $baglanti->satis_faturasi_id) {
            return;
        }

        $fatura = Fatura::query()
            ->where('firma_id', (int) $servis->firma_id)
            ->whereKey((int) $baglanti->satis_faturasi_id)
            ->first();
        if (! $fatura) {
            return;
        }

        $stamp = now()->format('Y-m-d H:i:s');
        $eskiAciklama = trim((string) ($fatura->aciklama ?? ''));
        $ek = '['.$stamp.'] '.$mesaj;
        $aciklama = trim($eskiAciklama.($eskiAciklama !== '' ? PHP_EOL : '').$ek);

        $fatura->update([
            'durum' => FaturaDurumu::Iptal->value,
            'aciklama' => $aciklama,
        ]);

        $baglanti->update([
            'senkron_durumu' => TeknikServisMuhasebeSenkronDurumu::Iptal->value,
            'son_senkron_tarihi' => now(),
        ]);
    }

    private function bagliTahsilatiTersle(string $durumNedeni): void
    {
        $servis = $this->record;
        if (! $servis) {
            return;
        }

        $stamp = now()->format('Y-m-d H:i:s');
        $nedenMetni = $durumNedeni === 'iade' ? 'iade' : 'iptal';
        $tersAciklama = '['.$stamp.'] Servis durumu '.$nedenMetni.' nedeni ile otomatik ters islem yapilmistir.';

        DB::transaction(function () use ($servis, $tersAciklama): void {
            $aktifTahsilatlar = TeknikServisTahsilati::query()
                ->where('firma_id', (int) $servis->firma_id)
                ->where('teknik_servis_kaydi_id', (int) $servis->getKey())
                ->where('durum', 'aktif')
                ->lockForUpdate()
                ->get();

            if ($aktifTahsilatlar->isNotEmpty()) {
                foreach ($aktifTahsilatlar as $tahsilat) {
                    app(TeknikServisTahsilatServisi::class)->iptalEt($tahsilat, $tersAciklama);
                }

                return;
            }

            $baglanti = TeknikServisMuhasebeBaglantisi::query()
                ->where('firma_id', (int) $servis->firma_id)
                ->where('teknik_servis_kaydi_id', (int) $servis->getKey())
                ->where('islem_tipi', TeknikServisMuhasebeIslemTipi::Tahsilat->value)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            if (! $baglanti || ! $baglanti->finans_hareketi_id) {
                return;
            }

            $finans = FinansHareketi::query()
                ->where('firma_id', (int) $servis->firma_id)
                ->whereKey((int) $baglanti->finans_hareketi_id)
                ->lockForUpdate()
                ->first();
            if (! $finans) {
                $baglanti->update([
                    'senkron_durumu' => TeknikServisMuhasebeSenkronDurumu::Iptal->value,
                    'son_senkron_tarihi' => now(),
                ]);
                return;
            }

            $tersKayitVar = FinansHareketi::query()
                ->where('firma_id', (int) $servis->firma_id)
                ->where('iptal_edilen_hareket_id', (int) $finans->getKey())
                ->where('durum', 'aktif')
                ->exists();

            if (! $tersKayitVar && ((string) ($finans->durum->value ?? $finans->durum) === 'aktif')) {
                try {
                    app(FinansHareketServisi::class)->tersKayitOlustur($finans, $tersAciklama);
                } catch (IsKuraliIstisnasi $e) {
                    throw ValidationException::withMessages([
                        'data.servis_durumu_id' => $e->getMessage(),
                    ]);
                }
            }

            $baglanti->update([
                'senkron_durumu' => TeknikServisMuhasebeSenkronDurumu::Iptal->value,
                'son_senkron_tarihi' => now(),
                'hata_mesaji' => null,
            ]);
        });
    }

    /**
     * @param array<int,array<string,mixed>> $kalemler
     * @return array{para_birimi:string,ara_toplam:string,toplam_indirim:string,kdv_toplam:string,genel_toplam:string,kalemler:array<int,array<string,mixed>>}
     */
    private function faturaIcinKalemVeToplamHazirla(array $kalemler): array
    {
        // Fatura baslik ara_toplami, satir net tutarlarinin (KDV matrahi) toplami olmalidir.
        $araToplam = 0.0;
        $toplamIndirim = 0.0;
        $kdvToplam = 0.0;
        $genelToplam = 0.0;
        $paraBirimi = 'TRY';
        $satirlar = [];
        $satirNo = 0;

        foreach ($kalemler as $kalem) {
            $stokId = (int) ($kalem['stok_id'] ?? 0);
            $aciklama = trim((string) ($kalem['aciklama'] ?? ''));
            $miktar = (float) ($kalem['miktar'] ?? 0);
            $birimFiyat = (float) ($kalem['birim_fiyat'] ?? 0);
            if ($miktar <= 0 || $birimFiyat < 0 || ($stokId < 1 && $aciklama === '')) {
                continue;
            }

            $satirNo++;
            $kdvOrani = (float) ($kalem['kdv_orani'] ?? 0);
            $iskontoTutari = (float) ($kalem['iskonto_tutari'] ?? 0);
            $brut = $this->paraYuvarla($miktar * $birimFiyat);
            $iskontoTutari = max(0.0, min($iskontoTutari, $brut));
            $iskontoTutari = $this->paraYuvarla($iskontoTutari);
            $net = $this->paraYuvarla($brut - $iskontoTutari);
            $kdvTutari = $this->paraYuvarla($net * ($kdvOrani / 100));
            $satirToplami = $this->paraYuvarla($net + $kdvTutari);
            $indirimOrani = $brut > 0 ? round(($iskontoTutari / $brut) * 100, 4) : 0.0;

            $pb = strtoupper((string) ($kalem['para_birimi'] ?? 'TRY'));
            if ($satirNo === 1 && $pb !== '') {
                $paraBirimi = $pb;
            }

            $araToplam = $this->paraYuvarla($araToplam + $net);
            $toplamIndirim = $this->paraYuvarla($toplamIndirim + $iskontoTutari);
            $kdvToplam = $this->paraYuvarla($kdvToplam + $kdvTutari);
            $genelToplam = $this->paraYuvarla($genelToplam + $satirToplami);

            $satirlar[] = [
                'satir_no' => $satirNo,
                'kalem_tipi' => 'stok',
                'stok_id' => $stokId > 0 ? $stokId : null,
                'birim' => (string) ($kalem['birim'] ?? 'AD'),
                'hizmet_mi' => false,
                'aciklama' => $aciklama,
                'miktar' => number_format($miktar, 4, '.', ''),
                'birim_fiyat' => $this->para($birimFiyat),
                'baz_birim_fiyat' => $this->para($birimFiyat),
                'indirim_orani' => number_format($indirimOrani, 4, '.', ''),
                'kdv_orani' => number_format($kdvOrani, 2, '.', ''),
                'satir_indirim_tutari' => $this->para($iskontoTutari),
                'indirim_tutari' => $this->para($iskontoTutari),
                'baz_indirim_tutari' => $this->para($iskontoTutari),
                'net_tutar' => $this->para($net),
                'baz_net_tutar' => $this->para($net),
                'kdv_tutari' => $this->para($kdvTutari),
                'baz_kdv_tutari' => $this->para($kdvTutari),
                'satir_toplami' => $this->para($brut),
                'baz_satir_toplami' => $this->para($brut),
                'satir_genel_toplam' => $this->para($satirToplami),
                'baz_satir_genel_toplam' => $this->para($satirToplami),
                'toplam' => $this->para($satirToplami),
            ];
        }

        return [
            'para_birimi' => $paraBirimi,
            'ara_toplam' => $this->para($araToplam),
            'toplam_indirim' => $this->para($toplamIndirim),
            'kdv_toplam' => $this->para($kdvToplam),
            'genel_toplam' => $this->para($genelToplam),
            'kalemler' => $satirlar,
        ];
    }

    private function para(float $tutar): string
    {
        return number_format($this->paraYuvarla($tutar), self::PARA_BASAMAK, '.', '');
    }

    private function paraYuvarla(float $tutar): float
    {
        return round($tutar, self::PARA_BASAMAK);
    }

    private function guncelDurumMetni(): string
    {
        $durum = $this->servisDurumuTanimi((int) ($this->record?->servis_durumu_id ?? 0));
        $ad = trim((string) ($durum['ad'] ?? ''));

        return $ad ?: 'Bilinmiyor';
    }
}
