<?php

namespace App\Filament\Resources\FirmaIciKullaniciKaynagi\Pages;

use App\Filament\Resources\FirmaIciKullaniciKaynagi;
use App\Models\FirmaKullanici;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class FirmaIciKullaniciDuzenle extends EditRecord
{
    protected static string $resource = FirmaIciKullaniciKaynagi::class;

    protected static string $view = 'filament.resources.firma-ici-kullanici-kaynagi.pages.firma-ici-kullanici-duzenle';

    public function form(Form $form): Form
    {
        if (request()->boolean('detay')) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (request()->boolean('detay')) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'durum_aktif' => ((string) ($this->record->durum ?? 'aktif')) === 'aktif',
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (request()->boolean('detay')) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $this->record->forceFill([
            'durum' => (bool) ($this->data['durum_aktif'] ?? false) ? 'aktif' : 'pasif',
        ])->save();

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $detayModu = request()->boolean('detay');

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizliForm' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? FirmaIciKullaniciKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : FirmaIciKullaniciKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()]).'?detay=1'),
        ];
    }

    protected function getFormActions(): array
    {
        if (request()->boolean('detay')) {
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
     * @param  array<string, mixed>  $veri
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $veri): array
    {
        if (! request()->boolean('detay') && array_key_exists('durum_aktif', $veri)) {
            $veri['durum'] = $veri['durum_aktif'] ? 'aktif' : 'pasif';
            unset($veri['durum_aktif']);
        }

        unset($veri['email'], $veri['hedef_firma_id']);

        return $veri;
    }

    /**
     * @param  array<string, mixed>  $veri
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $veri): array
    {
        if (! request()->boolean('detay')) {
            $veri['durum_aktif'] = ($veri['durum'] ?? 'aktif') === 'aktif';

            return $veri;
        }

        /** @var FirmaKullanici $kayit */
        $kayit = $this->record;
        $kullanici = $kayit->kullanici;
        if ($kullanici) {
            $veri['email'] = $kullanici->email;
            $veri['kullanici_adi'] = $kullanici->kullanici_adi;
            $veri['ad_soyad'] = $kullanici->ad_soyad ?? $kullanici->name;
        }

        return $veri;
    }

    protected function afterSave(): void
    {
        if (! request()->boolean('detay')) {
            return;
        }

        /** @var FirmaKullanici $kayit */
        $kayit = $this->record;
        $kullanici = $kayit->kullanici;
        if (! $kullanici) {
            return;
        }

        $veri = $this->form->getState();
        if (
            ! array_key_exists('kullanici_adi', $veri)
            && ! array_key_exists('ad_soyad', $veri)
            && empty($veri['password'])
        ) {
            return;
        }

        $kullaniciAdi = trim((string) ($veri['kullanici_adi'] ?? $kullanici->kullanici_adi));

        $cakisiyor = FirmaKullanici::query()
            ->withoutGlobalScopes()
            ->where('firma_id', (int) $kayit->firma_id)
            ->whereKeyNot((int) $kayit->getKey())
            ->whereNull('deleted_at')
            ->whereHas('kullanici', fn (Builder $query) => $query->where('kullanici_adi', $kullaniciAdi))
            ->exists();

        if ($cakisiyor) {
            throw ValidationException::withMessages(['kullanici_adi' => 'Bu firma için bu kullanıcı adı zaten kullanılıyor.']);
        }

        $guncelle = [
            'kullanici_adi' => $kullaniciAdi,
            'ad_soyad' => $veri['ad_soyad'] ?? $kullanici->ad_soyad,
            'name' => $veri['ad_soyad'] ?? $kullaniciAdi ?? $kullanici->name,
        ];
        if (! empty($veri['password'])) {
            if (mb_strlen((string) $veri['password']) < 6) {
                throw ValidationException::withMessages(['password' => 'Şifre en az 6 karakter olmalıdır.']);
            }
            $guncelle['password'] = Hash::make((string) $veri['password']);
        }
        $kullanici->update($guncelle);
    }
}
