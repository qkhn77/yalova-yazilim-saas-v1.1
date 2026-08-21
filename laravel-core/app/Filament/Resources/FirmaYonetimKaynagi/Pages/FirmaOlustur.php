<?php

namespace App\Filament\Resources\FirmaYonetimKaynagi\Pages;

use App\Filament\Resources\FirmaYonetimKaynagi;
use App\Models\Firma;
use App\Models\FirmaAboneligi;
use App\Models\FirmaKullanici;
use App\Models\FirmaModulu;
use App\Models\Rol;
use App\Models\User;
use App\Support\DenetimYardimcisi;
use App\Support\FirmaKoduUretici;
use App\Support\RolYardimcisi;
use App\Support\SaaSemaYardimcisi;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class FirmaOlustur extends CreateRecord
{
    protected static string $resource = FirmaYonetimKaynagi::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['firma_kodu'])) {
            $data['firma_kodu'] = FirmaKoduUretici::birSonraki();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $data['firma_kodu'] = (string) ($data['firma_kodu'] ?: FirmaKoduUretici::birSonraki());
            $durum = (string) ($data['durum'] ?? Firma::DURUM_BEKLEMEDE);
            if (! in_array($durum, array_keys(Firma::durumSecenekleri()), true)) {
                throw ValidationException::withMessages([
                    'durum' => 'Geçersiz firma durumu seçildi.',
                ]);
            }

            $firma = Firma::query()->create([
                'ad' => (string) $data['ad'],
                'kisa_ad' => $data['kisa_ad'] ?: null,
                'firma_kodu' => $data['firma_kodu'],
                'telefon' => $data['telefon'] ?: null,
                'eposta' => strtolower((string) $data['eposta']),
                'adres' => $data['adres'] ?: null,
                'vergi_no' => $data['vergi_no'] ?: null,
                'durum' => $durum,
            ]);

            if (! ((bool) ($data['ilk_kurulum_aktif'] ?? false))) {
                return $firma;
            }

            $eposta = strtolower(trim((string) ($data['ilk_kullanici_eposta'] ?? '')));
            $kullaniciAdi = trim((string) ($data['ilk_kullanici_adi'] ?? ''));
            $password = (string) ($data['ilk_kullanici_sifre'] ?? '');
            if ($eposta === '' || $kullaniciAdi === '' || $password === '') {
                throw ValidationException::withMessages([
                    'ilk_kullanici_eposta' => 'İlk kurulum için kullanıcı bilgileri zorunludur.',
                ]);
            }
            if (mb_strlen($password) < 6) {
                throw ValidationException::withMessages([
                    'ilk_kullanici_sifre' => 'Şifre en az 6 karakter olmalıdır.',
                ]);
            }
            if (! empty($data['ilk_rol_id']) && ! Rol::query()->whereKey((int) $data['ilk_rol_id'])->exists()) {
                throw ValidationException::withMessages([
                    'ilk_rol_id' => 'Seçilen rol geçersiz.',
                ]);
            }

            $record = User::query()->where('email', $eposta)->first();
            if ($record) {
                if ((bool) ($record->super_admin_mi ?? false)) {
                    throw ValidationException::withMessages([
                        'ilk_kullanici_eposta' => 'Sistem yöneticisi ilk firma kullanıcısı olarak bağlanamaz.',
                    ]);
                }

                $record->update([
                    'kullanici_adi' => $kullaniciAdi,
                    'name' => $kullaniciAdi,
                    'ad_soyad' => $kullaniciAdi,
                    'password' => Hash::make($password),
                ]);
            } else {
                $record = User::query()->create([
                    'email' => $eposta,
                    'kullanici_adi' => $kullaniciAdi,
                    'name' => $kullaniciAdi,
                    'ad_soyad' => $kullaniciAdi,
                    'password' => Hash::make($password),
                ]);
            }

            if (FirmaKullanici::query()->withoutGlobalScopes()->where('firma_id', (int) $firma->id)->where('kullanici_id', (int) $record->id)->exists()) {
                throw ValidationException::withMessages([
                    'ilk_kullanici_eposta' => 'Bu kullanıcı bu firmaya zaten bağlı.',
                ]);
            }

            $rolId = ! empty($data['ilk_rol_id']) ? (int) $data['ilk_rol_id'] : RolYardimcisi::varsayilanFirmaYoneticisiRolId();
            if (! $rolId) {
                throw ValidationException::withMessages([
                    'ilk_rol_id' => 'Rol atanamadı. Veritabanında `firma_yoneticisi` veya `firma_sahibi` rolü olmalı; gerekirse seed çalıştırın.',
                ]);
            }

            $payload = [
                'firma_id' => (int) $firma->id,
                'kullanici_id' => (int) $record->id,
                'rol_id' => $rolId,
                'durum' => 'aktif',
                'varsayilan_firma_mi' => true,
            ];
            if (SaaSemaYardimcisi::firmaKullanicilariOnayDurumuKolonuVarMi()) {
                $payload['onay_durumu'] = 'aktif';
            }
            FirmaKullanici::query()->create($payload);

            if (! empty($data['ilk_plan_id'])) {
                FirmaAboneligi::query()->create([
                    'firma_id' => (int) $firma->id,
                    'plan_id' => (int) $data['ilk_plan_id'],
                    'durum' => 'aktif',
                    'baslangic_tarihi' => now()->toDateString(),
                    'bitis_tarihi' => now()->addMonth()->toDateString(),
                    'otomatik_yenileme' => false,
                ]);
            }

            $moduleIds = $data['ilk_modul_ids'] ?? [];
            if (is_array($moduleIds)) {
                foreach ($moduleIds as $record) {
                    FirmaModulu::query()->firstOrCreate([
                        'firma_id' => (int) $firma->id,
                        'modul_id' => (int) $record,
                    ], [
                        'durum' => 'aktif',
                        'baslangic_tarihi' => now()->toDateString(),
                    ]);
                }
            }

            return $firma;
        });
    }

    protected function afterCreate(): void
    {
        DenetimYardimcisi::kaydet(
            'firma_olusturuldu',
            Firma::class,
            (int) $this->record->id,
            (int) $this->record->id,
            null,
            ['ad' => $this->record->ad, 'firma_kodu' => $this->record->firma_kodu]
        );
    }

    protected function getRedirectUrl(): string
    {
        return FirmaYonetimKaynagi::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Firma başarıyla oluşturuldu.';
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title($this->getCreatedNotificationTitle())
            ->body('Kayıt tamamlandı ve firma listesine yönlendirildiniz.');
    }
}
