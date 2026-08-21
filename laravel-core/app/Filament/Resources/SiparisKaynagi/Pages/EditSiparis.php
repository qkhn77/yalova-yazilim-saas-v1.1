<?php

namespace App\Filament\Resources\SiparisKaynagi\Pages;

use App\Filament\Resources\SiparisKaynagi;
use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisGecmisi;
use App\Modules\Urun\Servisler\SiparisDurumGecisServisi;
use App\Modules\Urun\Servisler\SiparisGecmisServisi;
use App\Modules\Urun\Servisler\SiparisOdemeServisi;
use App\Services\EcommerceBildirimServisi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class EditSiparis extends EditRecord
{
    protected static string $resource = SiparisKaynagi::class;

    protected static ?string $title = 'Sipariş düzenle';

    protected static string $view = 'filament.resources.siparis-kaynagi.pages.edit-siparis';

    public function form(Form $form): Form
    {
        if (SiparisKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (SiparisKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'durum' => (string) ($this->record->durum ?? Siparis::DURUM_ONAY_BEKLIYOR),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (SiparisKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $durum = (string) ($this->data['durum'] ?? '');
        if (! array_key_exists($durum, Siparis::durumEtiketleri())) {
            throw ValidationException::withMessages([
                'data.durum' => 'Durum gecersiz.',
            ]);
        }

        $this->handleRecordUpdate($this->record, $this->mutateFormDataBeforeSave([
            'durum' => $durum,
        ]));

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    /**
     * @return array<string,string>
     */
    public function durumSecenekleri(): array
    {
        if (! $this->record instanceof Siparis) {
            return Siparis::durumEtiketleri();
        }

        return app(SiparisDurumGecisServisi::class)->durumSecimOpsiyonlari((string) $this->record->durum);
    }

    protected function getHeaderActions(): array
    {
        $detayModu = SiparisKaynagi::detayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? SiparisKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
        ];
    }

    protected function getFormActions(): array
    {
        if (SiparisKaynagi::detayModu()) {
            return parent::getFormActions();
        }

        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Siparis) {
            return parent::handleRecordUpdate($record, $data);
        }

        $record = $record->fresh() ?? $record;

        $kargoAlanlari = [
            'kargo_yontemi_id',
            'kargo_firmasi',
            'kargo_ucreti',
            'takip_no',
            'kargo_tarihi',
            'teslim_tarihi',
            'teslimat_ulke',
            'teslimat_il',
            'teslimat_ilce',
            'teslimat_posta_kodu',
        ];
        $notAlanlari = ['ic_not', 'musteri_notu', 'operasyon_notu'];
        $ekAlanlar = array_merge($kargoAlanlari, $notAlanlari);

        $kargoOnceki = Arr::only($record->getAttributes(), $kargoAlanlari);

        if (Siparis::iptalEdildiDurumMu($record->durum)) {
            $record->update(Arr::only($data, array_merge($ekAlanlar, ['iptal_nedeni'])));
            $this->kargoGuncellemeGecmisi($record->fresh(), $data, $kargoOnceki, $kargoAlanlari, false);

            return $record->refresh();
        }

        $eskiDurum = $record->durum;
        $yeniDurum = (string) ($data['durum'] ?? '');
        $takipNo = trim((string) ($data['takip_no'] ?? $record->takip_no ?? ''));

        if (Siparis::kargoTakipZorunluDurumMu($yeniDurum) && $takipNo === '') {
            throw ValidationException::withMessages([
                'takip_no' => 'Kargolandı durumunda kargo takip numarası zorunludur.',
            ]);
        }

        if (in_array($yeniDurum, [Siparis::DURUM_IPTAL_EDILDI, Siparis::DURUM_IPTAL], true) && ! Siparis::iptalEdildiDurumMu($eskiDurum)) {
            app(SiparisOdemeServisi::class)->siparisIptalEt(
                $record,
                isset($data['iptal_nedeni']) ? (string) $data['iptal_nedeni'] : null,
            );
            $record = $record->fresh();
            $record->update(Arr::only($data, $ekAlanlar));
            $this->kargoGuncellemeGecmisi($record->fresh(), $data, $kargoOnceki, $kargoAlanlari, false);

            return $record->refresh();
        }

        $eftOnayHedefleri = [
            Siparis::DURUM_ONAYLANDI_YENI,
            Siparis::DURUM_ONAYLANDI,
            Siparis::DURUM_ODENDI,
        ];

        if ($eskiDurum === Siparis::DURUM_EFT_ONAYI_BEKLIYOR && in_array($yeniDurum, $eftOnayHedefleri, true)) {
            app(SiparisOdemeServisi::class)->adminManuelOdemeOnayla($record);
            $record = $record->fresh();
            $record->update(Arr::only($data, $ekAlanlar));
            $this->kargoGuncellemeGecmisi($record->fresh(), $data, $kargoOnceki, $kargoAlanlari, false);

            return $record->refresh();
        }

        if ($yeniDurum !== $eskiDurum) {
            try {
                app(SiparisDurumGecisServisi::class)->durumuGuncelle($record, $yeniDurum);
            } catch (ValidationException $e) {
                throw $e;
            }
            $record = $record->fresh();
        }

        $record->update(Arr::only($data, $ekAlanlar));
        $buIstekteDurumBildiriminiBastir = $yeniDurum !== $eskiDurum
            && in_array($yeniDurum, [Siparis::DURUM_GONDERILDI, Siparis::DURUM_KARGOLANDI, Siparis::DURUM_TESLIM_EDILDI, Siparis::DURUM_TAMAMLANDI], true);

        $this->kargoGuncellemeGecmisi($record->fresh(), $data, $kargoOnceki, $kargoAlanlari, ! $buIstekteDurumBildiriminiBastir);

        return $record->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $kargoOnceki
     * @param  list<string>  $kargoAlanlari
     */
    private function kargoGuncellemeGecmisi(Siparis $siparis, array $data, array $kargoOnceki, array $kargoAlanlari, bool $bildirimGonder = true): void
    {
        $yeni = Arr::only($data, $kargoAlanlari);
        $degisti = false;
        foreach ($kargoAlanlari as $alan) {
            if (! array_key_exists($alan, $data)) {
                continue;
            }

            if ((string) ($kargoOnceki[$alan] ?? '') !== (string) ($yeni[$alan] ?? '')) {
                $degisti = true;

                break;
            }
        }

        if (! $degisti) {
            return;
        }

        app(SiparisGecmisServisi::class)->kaydet(
            $siparis,
            SiparisGecmisi::OLAY_KARGO_GUNCELLENDI,
            'Kargo / teslimat bilgisi güncellendi',
            $yeni,
        );

        $takipBilgisiDegisti = (string) ($kargoOnceki['takip_no'] ?? '') !== (string) ($yeni['takip_no'] ?? '')
            || (string) ($kargoOnceki['kargo_firmasi'] ?? '') !== (string) ($yeni['kargo_firmasi'] ?? '');

        if ($bildirimGonder && $takipBilgisiDegisti) {
            app(EcommerceBildirimServisi::class)->kargoBilgisiGuncellendi($siparis->fresh());
        }
    }
}
