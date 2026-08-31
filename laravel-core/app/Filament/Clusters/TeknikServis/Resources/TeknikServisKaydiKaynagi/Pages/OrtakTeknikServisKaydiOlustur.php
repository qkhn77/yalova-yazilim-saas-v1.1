<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Concerns\TeknikServisKayitFormSchema;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Models\TeknikServis\TeknikServisHatirlatma;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisKayitliCihazi;
use App\Services\TeknikServisGenelAyarServisi;
use App\Services\TeknikServisTelegramBildirimServisi;
use App\Services\TenantContextService;
use App\TeknikServis\Enumlar\MusteriOnayDurumu;
use App\TeknikServis\Enumlar\OdemeDurumu;
use App\TeknikServis\Enumlar\ServisTipi;
use App\TeknikServis\Servisler\TeknikServisFisNumarasiServisi;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Uc olusturma rotasi icin ortak davranis: servis tipi route ile sabitlenir.
 */
abstract class OrtakTeknikServisKaydiOlustur extends CreateRecord
{
    protected static string $resource = TeknikServisKaydiKaynagi::class;

    protected array $extraBodyAttributes = [
        'class' => 'page-teknik-servis-create-compact',
    ];

    protected static bool $shouldRegisterNavigation = false;

    abstract protected static function sabitServisTipi(): ServisTipi;

    public function form(Form $form): Form
    {
        return TeknikServisKayitFormSchema::formuOlustur($form, true, static::sabitServisTipi());
    }

    protected function fillForm(): void
    {
        parent::fillForm();

        $cihazId = (int) request()->query('kayitli_cihaz_id', 0);
        if ($cihazId < 1) {
            return;
        }

        $cihaz = TeknikServisKayitliCihazi::query()->with('cari')->find($cihazId);
        if (! $cihaz) {
            return;
        }

        $this->form->fill(array_merge($this->form->getState(), [
            'cari_id' => (int) $cihaz->cari_id,
            'gecmis_cihaz_id' => (int) $cihaz->getKey(),
            'cihaz_id' => $cihaz->cihaz_id,
            'marka_id' => $cihaz->marka_id,
            'model_no' => $cihaz->model_no,
            'seri_no' => $cihaz->seri_no,
            'ayirt_edici_bilgi' => $cihaz->ayirt_edici_bilgi,
        ]));
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function areFormActionsSticky(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('durumBilgisi')
                ->label('Durum: Yeni Kayıt')
                ->disabled()
                ->extraAttributes([
                    'class' => 'animate-pulse',
                    'style' => 'pointer-events:none; font-weight:700;',
                ]),
            Actions\Action::make('saveChanges')
                ->label('Kaydet')
                ->color('primary')
                ->extraAttributes([
                    'wire:loading.attr' => 'disabled',
                    'wire:target' => 'create',
                ])
                ->action('create'),
        ];
    }
protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->extraAttributes([
                'wire:loading.attr' => 'disabled',
                'wire:target' => 'create',
            ]);
    }

    /**
     * Uc olusturma rotasi icin ortak davranis: servis tipi route ile sabitlenir.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();
        if ($firmaId < 1) {
            $firmaId = (int) \App\Models\Firma::query()->orderBy('id')->value('id');
        }

        $data['firma_id'] = $firmaId;
        $data['olusturan_id'] = Auth::id();
        $data['servis_tipi'] = static::sabitServisTipi()->value;

        $cariId = (int) ($data['cari_id'] ?? 0);
        if ($acikServis = TeknikServisKayitFormSchema::acikServisKaydiCariIcin($cariId)) {
            throw ValidationException::withMessages([
                'cari_id' => 'Bu carinin açık servis kaydı bulunmaktadır (fiş no: '.($acikServis->fis_no ?: '#'.$acikServis->getKey()).'). Mevcut kaydı açmadan yeni kayıt oluşturamazsınız.',
            ]);
        }

        $seriNo = trim((string) ($data['seri_no'] ?? ''));
        if ($seriNo !== '' && TeknikServisKaydi::query()
            ->where('firma_id', $firmaId)
            ->where('cari_id', $cariId)
            ->whereRaw('LOWER(TRIM(seri_no)) = ?', [mb_strtolower($seriNo, 'UTF-8')])
            ->exists()) {
            throw ValidationException::withMessages([
                'seri_no' => 'Bu müşterinin aynı seri numarasına sahip cihazı zaten kayıtlı. Mevcut cihazı seçerek devam edin.',
            ]);
        }

        $ham = $this->form->getRawState();
        $arizaSecimleri = $data['arizalar'] ?? (is_array($ham) ? ($ham['arizalar'] ?? []) : []);
        $data['ariza_id'] = array_values(array_filter((array) $arizaSecimleri))[0] ?? null;

        $ozet = TeknikServisKayitFormSchema::stokOzetHesaplaKalemDizisi((array) ($data['kalemler'] ?? []));
        $data['toplam_tutar'] = (float) ($ozet['odenecek_tutar'] ?? 0);
        $data['odenen_tutar'] = (float) ($data['odenen_tutar'] ?? 0);
        $data['odeme_durumu'] = (string) ($data['odeme_durumu'] ?? OdemeDurumu::Odenmedi->value);
        $data['musteri_onay_durumu'] = (string) ($data['musteri_onay_durumu'] ?? app(TeknikServisGenelAyarServisi::class)->varsayilanMusteriOnayDurumu($firmaId));
        $data['musteri_sikayeti'] = $this->musteriSikayetiDegeri($data, is_array($ham) ? $ham : []);
        $data['create_idempotency_key'] = $this->olusturmaIstekAnahtariAl($data);

        $fisNoServisi = app(TeknikServisFisNumarasiServisi::class);
        $fisNo = trim((string) ($data['fis_no'] ?? ''));
        if ($fisNo === '' || $fisNoServisi->fisNoKullaniliyorMu($fisNo)) {
            $data['fis_no'] = $fisNoServisi->benzersizUret($firmaId);
        }

        return $data;
    }

    /**
     * Detayli formda not alanlari bazi hizli akislarda dehydrate olmayabilir.
     * Veritabani katmaninda 500 yerine anlamli ve guvenli bir kayit degeri olustur.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $ham
     */
    private function musteriSikayetiDegeri(array $data, array $ham): string
    {
        $deger = trim((string) ($data['musteri_sikayeti'] ?? $ham['musteri_sikayeti'] ?? ''));

        return $deger !== '' ? $deger : 'Belirtilmedi';
    }

    public function create(bool $another = false): void
    {
        $istekAnahtari = $this->olusturmaIstekAnahtariAl();

        if ($mevcutKayit = $this->idempotentKaydiBul($istekAnahtari)) {
            $this->kayitZatenOlusturulduYonlendirmesi($mevcutKayit, $another);

            return;
        }

        $lock = Cache::lock($this->olusturmaKilidiAnahtari($istekAnahtari), 30);

        if (! $lock->get()) {
            Notification::make()
                ->warning()
                ->title('Kaydetme işlemi devam ediyor')
                ->body('Aynı servis kaydı isteği zaten işleniyor. Lütfen birkaç saniye bekleyin.')
                ->send();

            return;
        }

        try {
            if ($mevcutKayit = $this->idempotentKaydiBul($istekAnahtari)) {
                $this->kayitZatenOlusturulduYonlendirmesi($mevcutKayit, $another);

                return;
            }

            parent::create($another);

            if ($this->record?->exists) {
                Cache::put(
                    $this->olusturmaSonucAnahtari($istekAnahtari),
                    (int) $this->record->getKey(),
                    now()->addMinutes(15)
                );
            }
        } finally {
            optional($lock)->release();
        }
    }

    protected function getRedirectUrl(): string
    {
        return TeknikServisKaydiKaynagi::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $ham = $this->form->getRawState();
        $this->bakimHatirlatmasiniSenkronla($this->record, is_array($ham) ? $ham : []);
        app(TeknikServisTelegramBildirimServisi::class)->yeniServisKaydi($this->record);
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

        TeknikServisHatirlatma::query()->updateOrCreate(
            [
                'firma_id' => (int) $kayit->firma_id,
                'teknik_servis_kaydi_id' => (int) $kayit->getKey(),
                'hatirlatma_tipi' => 'bakim',
            ],
            [
                'periyot_ay' => $periyotAy,
                'ilk_tarih' => $tarih,
                'sonraki_tarih' => $tarih,
                'durum' => 'aktif',
                'not' => null,
                'olusturan_id' => Auth::id(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function olusturmaIstekAnahtariAl(array $data = []): string
    {
        $anahtar = trim((string) ($data['create_idempotency_key'] ?? $this->data['create_idempotency_key'] ?? ''));

        if ($anahtar !== '') {
            return $anahtar;
        }

        $anahtar = (string) Str::uuid();
        $this->data['create_idempotency_key'] = $anahtar;

        return $anahtar;
    }

    private function olusturmaKilidiAnahtari(string $istekAnahtari): string
    {
        return 'teknik_servis:create:lock:'.$istekAnahtari;
    }

    private function olusturmaSonucAnahtari(string $istekAnahtari): string
    {
        return 'teknik_servis:create:result:'.$istekAnahtari;
    }

    private function idempotentKaydiBul(string $istekAnahtari): ?TeknikServisKaydi
    {
        $kayitId = (int) Cache::get($this->olusturmaSonucAnahtari($istekAnahtari), 0);
        if ($kayitId < 1) {
            return null;
        }

        $kayit = TeknikServisKaydi::query()
            ->withoutGlobalScopes()
            ->find($kayitId);

        if (! $kayit) {
            Cache::forget($this->olusturmaSonucAnahtari($istekAnahtari));

            return null;
        }

        return $kayit;
    }

    private function kayitZatenOlusturulduYonlendirmesi(TeknikServisKaydi $kayit, bool $another = false): void
    {
        $this->record = $kayit;

        Notification::make()
            ->info()
            ->title('Kayıt zaten oluşturuldu')
            ->body('Aynı kaydetme isteği tekrar algılandı. İkinci bir servis kaydı açılmadı.')
            ->send();

        if ($another) {
            $this->form->model($this->getRecord()::class);
            $this->record = null;
            $this->fillForm();

            return;
        }

        $this->redirect($this->getRedirectUrl());
    }

}
