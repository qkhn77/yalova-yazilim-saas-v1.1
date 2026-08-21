<?php

namespace App\Models;

use App\Database\Scopes\KullaniciSoftDeletingScope;
use App\Models\Muhasebe\Cari;
use App\Models\Proje\IsletmeProjesi;
use App\Support\KullaniciTablosuYardimcisi;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasFactory, Notifiable;

    use SoftDeletes {
        performDeleteOnModel as protected softDeletesPerformDeleteOnModel;
    }

    protected $fillable = [
        'name',
        'ad_soyad',
        'telefon',
        'profil_fotografi',
        'kullanici_adi',
        'email',
        'password',
        'super_admin_mi',
        'aktif_mi',
        'son_giris_tarihi',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'super_admin_mi' => 'boolean',
            'aktif_mi' => 'boolean',
            'son_giris_tarihi' => 'datetime',
        ];
    }

    public function firmaKullanicilari(): HasMany
    {
        return $this->hasMany(FirmaKullanici::class, 'kullanici_id');
    }

    public function cariler(): HasMany
    {
        return $this->hasMany(Cari::class, 'kullanici_id');
    }

    public function isletmeProjeleri(): BelongsToMany
    {
        return $this->belongsToMany(IsletmeProjesi::class, 'isletme_proje_kullanicilari', 'kullanici_id', 'isletme_proje_id')->withTimestamps();
    }

    /**
     * Varsayılan SoftDeletingScope yerine kolon yokken sorguyu kırmayan scope.
     */
    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(new KullaniciSoftDeletingScope);
    }

    /**
     * Kolon yokken soft delete güncellemesi SQL hatası vermesin; kalıcı silme kullanılır.
     */
    protected function performDeleteOnModel()
    {
        if (! KullaniciTablosuYardimcisi::usersDeletedAtKolonuVarMi()) {
            return parent::performDeleteOnModel();
        }

        return $this->softDeletesPerformDeleteOnModel();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $superAdminMi = (bool) ($this->super_admin_mi ?? false) || (bool) ($this->is_admin ?? false);
        if ($superAdminMi) {
            return true;
        }

        if ((bool) session('uye_oturumu', false)) {
            return false;
        }

        // Firma baglami gerektiren son karar FilamentTenantContextMiddleware tarafinda verilir.
        // Burada "aktif_firma_id" zorlamak, oturum acik ama baglam eksik durumlarda yonlendirme
        // yerine dogrudan 403'e dusulmesine neden oluyordu.
        return true;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $profilFotografi = (string) ($this->profil_fotografi ?? '');

        return $profilFotografi !== ''
            ? Storage::disk('public')->url($profilFotografi)
            : asset('images/default-avatar.png');
    }
}
