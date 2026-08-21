<?php

namespace App\Filament\Resources\FirmaIciKullaniciKaynagi\Pages;

use App\Filament\Resources\FirmaIciKullaniciKaynagi;
use App\Models\FirmaKullanici;
use App\Models\Rol;
use App\Models\User;
use App\Services\TenantContextService;
use App\Support\RolYardimcisi;
use App\Support\SaaSemaYardimcisi;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FirmaIciKullaniciOlustur extends CreateRecord
{
    protected static string $resource = FirmaIciKullaniciKaynagi::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $firmaId = (int) ($data['hedef_firma_id'] ?? app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId <= 0) {
            throw ValidationException::withMessages(['hedef_firma_id' => 'Geçerli firma seçilmelidir.']);
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '') {
            throw ValidationException::withMessages(['email' => 'E-posta zorunludur.']);
        }

        $telefon = trim((string) ($data['telefon'] ?? ''));

        $mevcutKullanici = User::query()->where('email', $email)->first();
        $kullaniciAdi = trim((string) ($data['kullanici_adi'] ?? ''));

        if ($kullaniciAdi === '') {
            throw ValidationException::withMessages(['kullanici_adi' => 'Kullanıcı adı zorunludur.']);
        }
        if (! empty($data['password']) && mb_strlen((string) $data['password']) < 6) {
            throw ValidationException::withMessages(['password' => 'Şifre en az 6 karakter olmalıdır.']);
        }
        if (! empty($data['rol_id']) && ! Rol::query()->whereKey((int) $data['rol_id'])->exists()) {
            throw ValidationException::withMessages(['rol_id' => 'Seçilen rol geçersiz.']);
        }

        if ($mevcutKullanici) {
            if (FirmaKullanici::query()
                ->withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->where('kullanici_id', $mevcutKullanici->id)
                ->whereNull('deleted_at')
                ->exists()) {
                $this->logDuplicateAttempt('email', $email, $firmaId);
                throw ValidationException::withMessages(['email' => 'Bu e-posta adresi başka bir kullanıcıya ait.']);
            }

            if ((bool) ($mevcutKullanici->super_admin_mi ?? false)) {
                throw ValidationException::withMessages(['email' => 'Sistem yöneticisi bu ekrandan firmaya eklenemez.']);
            }

            $cakisiyor = FirmaKullanici::query()
                ->withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->where('kullanici_id', '!=', $mevcutKullanici->id)
                ->whereNull('deleted_at')
                ->whereHas('kullanici', fn (Builder $query) => $query->where('kullanici_adi', $kullaniciAdi))
                ->exists();

            if ($cakisiyor) {
                $this->logDuplicateAttempt('kullanici_adi', $kullaniciAdi, $firmaId);
                throw ValidationException::withMessages(['kullanici_adi' => 'Bu kullanıcı adı zaten kullanılıyor.']);
            }

            $globalKullaniciAdiCakisma = User::query()
                ->where('kullanici_adi', $kullaniciAdi)
                ->whereKeyNot((int) $mevcutKullanici->id)
                ->exists();
            if ($globalKullaniciAdiCakisma) {
                $this->logDuplicateAttempt('kullanici_adi', $kullaniciAdi, $firmaId);
                throw ValidationException::withMessages(['kullanici_adi' => 'Bu kullanıcı adı zaten kullanılıyor.']);
            }

            $this->telefonCakismaKontrolEt($telefon, $firmaId, (int) $mevcutKullanici->id);

            $guncelle = [
                'kullanici_adi' => $kullaniciAdi,
                'ad_soyad' => $data['ad_soyad'] ?? $mevcutKullanici->ad_soyad,
                'name' => $data['ad_soyad'] ?? $kullaniciAdi ?? $mevcutKullanici->name,
            ];
            if (! empty($data['password'])) {
                $guncelle['password'] = Hash::make((string) $data['password']);
            }
            $mevcutKullanici->update($guncelle);
            $kullanici = $mevcutKullanici;
        } else {
            if (empty($data['password'])) {
                throw ValidationException::withMessages(['password' => 'Yeni kullanıcı için şifre zorunludur.']);
            }

            $cakisiyor = FirmaKullanici::query()
                ->withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->whereNull('deleted_at')
                ->whereHas('kullanici', fn (Builder $query) => $query->where('kullanici_adi', $kullaniciAdi))
                ->exists();

            if ($cakisiyor) {
                $this->logDuplicateAttempt('kullanici_adi', $kullaniciAdi, $firmaId);
                throw ValidationException::withMessages(['kullanici_adi' => 'Bu kullanıcı adı zaten kullanılıyor.']);
            }

            $globalKullaniciAdiCakisma = User::query()
                ->where('kullanici_adi', $kullaniciAdi)
                ->exists();
            if ($globalKullaniciAdiCakisma) {
                $this->logDuplicateAttempt('kullanici_adi', $kullaniciAdi, $firmaId);
                throw ValidationException::withMessages(['kullanici_adi' => 'Bu kullanıcı adı zaten kullanılıyor.']);
            }

            $this->telefonCakismaKontrolEt($telefon, $firmaId);

            $kullanici = User::query()->create([
                'email' => $email,
                'password' => Hash::make((string) $data['password']),
                'kullanici_adi' => $kullaniciAdi,
                'ad_soyad' => $data['ad_soyad'] ?? null,
                'name' => $data['ad_soyad'] ?? $kullaniciAdi,
            ]);
        }

        $varsayilanRolId = RolYardimcisi::varsayilanFirmaYoneticisiRolId();
        $rolId = filled($data['rol_id'] ?? null) ? (int) $data['rol_id'] : $varsayilanRolId;
        if (! $rolId) {
            throw ValidationException::withMessages([
                'rol_id' => 'Rol seçin veya veritabanında `firma_yoneticisi` / `firma_sahibi` sistem rolünün tanımlı olduğundan emin olun.',
            ]);
        }

        $payload = [
            'firma_id' => $firmaId,
            'kullanici_id' => $kullanici->id,
            'rol_id' => $rolId,
            'durum' => $data['durum'] ?? 'aktif',
            'varsayilan_firma_mi' => (bool) ($data['varsayilan_firma_mi'] ?? false),
        ];
        if (SaaSemaYardimcisi::firmaKullanicilariOnayDurumuKolonuVarMi()) {
            $payload['onay_durumu'] = $data['onay_durumu'] ?? 'aktif';
        }

        return FirmaKullanici::query()->create($payload);
    }

    private function telefonCakismaKontrolEt(string $telefon, int $firmaId, ?int $mevcutKullaniciId = null): void
    {
        if ($telefon === '' || ! Schema::hasColumn('users', 'telefon')) {
            return;
        }

        $sorgu = User::query()->where('telefon', $telefon);
        if ($mevcutKullaniciId !== null && $mevcutKullaniciId > 0) {
            $sorgu->whereKeyNot($mevcutKullaniciId);
        }

        if ($sorgu->exists()) {
            $this->logDuplicateAttempt('telefon', $telefon, $firmaId);
            throw ValidationException::withMessages([
                'telefon' => 'Bu telefon numarası zaten kayıtlı.',
            ]);
        }
    }

    private function logDuplicateAttempt(string $field, string $value, int $firmaId): void
    {
        Log::warning('firma_ici_kullanici.duplicate_deneme', [
            'alan' => $field,
            'deger' => $value,
            'firma_id' => $firmaId,
            'kullanici_id' => Auth::id(),
        ]);
    }
}
