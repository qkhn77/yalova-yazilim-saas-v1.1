<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKaynagi;
use App\Models\Restoran\RestoranAdisyonu;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditRestoranAdisyon extends EditRecord
{
    protected static string $resource = RestoranAdisyonKaynagi::class;

    protected static string $view = 'filament.clusters.restoran.resources.restoran-adisyon-kaynagi.pages.edit-restoran-adisyon';

    public function form(Form $form): Form
    {
        if (RestoranAdisyonKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (RestoranAdisyonKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'durum' => (string) ($this->record->durum ?? RestoranAdisyonu::DURUM_ACIK),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (RestoranAdisyonKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $durum = (string) ($this->data['durum'] ?? '');
        if (! in_array($durum, [
            RestoranAdisyonu::DURUM_ACIK,
            RestoranAdisyonu::DURUM_ODEMEDE,
            RestoranAdisyonu::DURUM_KAPANDI,
            RestoranAdisyonu::DURUM_IPTAL,
        ], true)) {
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

    protected function getHeaderActions(): array
    {
        $tahsilatDetayModu = RestoranAdisyonKaynagi::tahsilatDetaylariGoster();

        if (! $tahsilatDetayModu) {
            return [];
        }

        return [
            Actions\Action::make($tahsilatDetayModu ? 'hizli_form' : 'detaylar')
                ->label($tahsilatDetayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($tahsilatDetayModu ? 'heroicon-o-bolt' : 'heroicon-o-banknotes')
                ->color('gray')
                ->url(fn (): string => $tahsilatDetayModu
                    ? RestoranAdisyonKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
        ];
    }

    protected function getFormActions(): array
    {
        if (RestoranAdisyonKaynagi::detayModu()) {
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
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (RestoranAdisyonKaynagi::detayModu()) {
            return $data;
        }

        $alanlar = [
            'firma_id',
            'sube_id',
            'masa_id',
            'cari_id',
            'garson_personel_id',
            'kasiyer_personel_id',
            'kurye_personel_id',
            'kasa_hesap_id',
            'banka_hesap_id',
            'pos_hesap_id',
            'finans_hareketi_id',
            'adisyon_no',
            'acilis_at',
            'kapanis_at',
            'durum',
            'siparis_tipi',
            'paket_durum',
            'teslimat_telefon',
            'teslimat_adresi',
            'tahmini_teslimat_at',
            'teslimat_notu',
            'odeme_kanali',
            'musteri_sayisi',
            'ara_toplam',
            'indirim_toplam',
            'ikram_toplam',
            'kdv_toplam',
            'servis_ucreti',
            'genel_toplam',
            'para_birimi',
            'tahsilat_at',
            'teslimat_at',
            'notlar',
        ];

        $mevcut = RestoranAdisyonu::query()
            ->whereKey($this->record->getKey())
            ->first($alanlar);

        if (! $mevcut) {
            return $data;
        }

        $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));

        return array_replace($mevcutVeri, $data);
    }
}
