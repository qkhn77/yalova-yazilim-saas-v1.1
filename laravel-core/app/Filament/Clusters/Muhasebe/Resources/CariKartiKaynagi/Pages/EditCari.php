<?php

namespace App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FinansHareketi;
use App\Services\TenantContextService;
use App\Support\DenetimYardimcisi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditCari extends EditRecord
{
    protected static string $resource = CariKartiKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.cari-karti-kaynagi.pages.edit-cari';

    protected static ?string $title = 'Cari düzenle';

    public function form(Form $form): Form
    {
        if (CariKartiKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (CariKartiKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'durum' => (string) (($this->record->durum?->value ?? $this->record->durum ?? 'aktif')),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (CariKartiKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $durum = (string) ($this->data['durum'] ?? '');
        if (! in_array($durum, ['aktif', 'pasif'], true)) {
            throw ValidationException::withMessages([
                'data.durum' => 'Durum gecersiz.',
            ]);
        }

        $data = $this->mutateFormDataBeforeSave([
            'durum' => $durum,
        ]);

        $this->handleRecordUpdate($this->record, $data);

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $detayModu = CariKartiKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make('goruntule')
                ->label('Cariyi görüntüle')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => CariKartiKaynagi::getUrl('view', ['record' => (int) $this->record->getKey()])),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! CariKartiKaynagi::detayModu()) {
            $alanlar = [
                'firma_id',
                'kod',
                'ad',
                'kisa_ad',
                'tur',
                'durum',
                'cari_grubu_id',
                'vergi_dairesi',
                'vergi_no',
                'tc_no',
                'telefon',
                'gsm',
                'email',
                'website',
                'adres',
                'ulke',
                'il',
                'ilce',
                'posta_kodu',
                'yetkili_kisi',
                'risk_limiti',
                'vade_gunu',
                'para_birimi',
                'aciklama',
            ];

            $mevcut = Cari::query()
                ->whereKey($this->record->getKey())
                ->first($alanlar);

            if ($mevcut) {
                $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));
                $data = array_replace($mevcutVeri, $data);
            }
        }

        $kullanici = Auth::user();
        $super = $kullanici && Cari::kullaniciSuperAdminMi($kullanici);

        if (! $super) {
            $aktif = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
            if ($aktif < 1 || (int) $this->record->firma_id !== $aktif) {
                abort(403);
            }
            $data['firma_id'] = $aktif;
        } else {
            $data['firma_id'] = (int) ($data['firma_id'] ?? $this->record->firma_id);
            if ($data['firma_id'] < 1) {
                throw ValidationException::withMessages(['firma_id' => 'Firma geçersiz.']);
            }
        }

        if (! CariKartiKaynagi::telefonBenzersizMi((int) $data['firma_id'], $data['telefon'] ?? null, (int) $this->record->getKey())) {
            throw ValidationException::withMessages(['telefon' => 'Bu telefon numarası aynı firma içinde başka bir cari kartında kayıtlı.']);
        }

        // Cari kodu dış sistem referansı olabilir; düzenleme ekranından değiştirilemez.
        $kod = trim((string) ($this->record->kod ?? ''));
        if ($kod === '') {
            throw ValidationException::withMessages(['kod' => 'Kod zorunludur.']);
        }
        $data['kod'] = $kod;
        if (! CariKartiKaynagi::kodBenzersizMi((int) $data['firma_id'], $kod, (int) $this->record->getKey())) {
            throw ValidationException::withMessages(['kod' => 'Bu kod bu firma için zaten kullanılıyor.']);
        }

        $para = strtoupper(trim((string) ($data['para_birimi'] ?? $this->record->para_birimi ?? '')));
        if ($para === '' || ! CariKartiKaynagi::paraBirimiFirmaIcinGecerliMi((int) $data['firma_id'], $para)) {
            throw ValidationException::withMessages([
                'para_birimi' => 'Para birimi bu firma için tanımlı ve aktif olmalıdır (Tanımlar → Para birimleri).',
            ]);
        }
        $mevcutPara = strtoupper((string) ($this->record->para_birimi ?? ''));
        if ($mevcutPara !== '' && $mevcutPara !== $para && $this->cariIslemGecmisiVar()) {
            DenetimYardimcisi::kaydet(
                olay: 'cari.para_birimi_degisim_engellendi',
                konuTipi: Cari::class,
                konuId: (int) $this->record->getKey(),
                firmaId: (int) $this->record->firma_id,
                eskiVeri: ['para_birimi' => $mevcutPara],
                yeniVeri: ['para_birimi' => $para]
            );
            throw ValidationException::withMessages([
                'para_birimi' => 'Cari için hareket/fatura geçmişi bulunduğundan para birimi değiştirilemez.',
            ]);
        }
        $data['para_birimi'] = $para;

        return $data;
    }

    private function cariIslemGecmisiVar(): bool
    {
        $cariId = (int) $this->record->getKey();
        $firmaId = (int) $this->record->firma_id;

        return Fatura::query()->where('firma_id', $firmaId)->where('cari_id', $cariId)->exists()
            || FinansHareketi::query()->where('firma_id', $firmaId)->where('cari_id', $cariId)->exists()
            || CariHareketi::query()->where('firma_id', $firmaId)->where('cari_id', $cariId)->exists();
    }
}
