<?php

namespace App\Models\Proje;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\Masraf;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\Fatura;
use App\Models\User;
use App\Services\TenantContextService;
use App\Services\YetkiService;
use App\Support\KullaniciRolYardimcisi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IsletmeProjesi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    public const DURUM_TASLAK = 'taslak';

    public const DURUM_AKTIF = 'aktif';

    public const DURUM_TAMAMLANDI = 'tamamlandi';

    public const DURUM_IPTAL = 'iptal';

    protected $table = 'isletme_projeleri';

    protected $fillable = [
        'firma_id',
        'kod',
        'ad',
        'durum',
        'baslangic_tarihi',
        'bitis_tarihi',
        'butce_tutari',
        'para_birimi',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'baslangic_tarihi' => 'date',
            'bitis_tarihi' => 'date',
            'butce_tutari' => 'decimal:2',
        ];
    }

    /** @param Builder<static> $query */
    public function scopeSecilebilir(Builder $query): Builder
    {
        return $query->whereIn('durum', [self::DURUM_TASLAK, self::DURUM_AKTIF]);
    }

    /** @param Builder<static> $query */
    public function scopeKullaniciIcinGorunur(Builder $query, ?User $kullanici = null, ?int $firmaId = null): Builder
    {
        $kullanici ??= auth()->user();
        $firmaId ??= (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);

        if (! $kullanici instanceof User || $firmaId < 1) {
            return $query;
        }

        if (KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici)
            || app(YetkiService::class)->firmaUstYonetimRoluMuKullaniciIcin($kullanici, $firmaId)) {
            return $query;
        }

        $kullaniciId = (int) $kullanici->getKey();

        return $query->where(function (Builder $visible) use ($kullaniciId): void {
            $visible->whereDoesntHave('kullanicilar')
                ->orWhereHas('kullanicilar', fn (Builder $assigned): Builder => $assigned->whereKey($kullaniciId));
        });
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function masraflar(): HasMany
    {
        return $this->hasMany(Masraf::class, 'isletme_proje_id');
    }

    public function faturalar(): HasMany
    {
        return $this->hasMany(Fatura::class, 'isletme_proje_id');
    }

    public function cariHareketleri(): HasMany
    {
        return $this->hasMany(CariHareketi::class, 'isletme_proje_id');
    }

    public function finansHareketleri(): HasMany
    {
        return $this->hasMany(FinansHareketi::class, 'isletme_proje_id');
    }

    public function kullanicilar(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'isletme_proje_kullanicilari', 'isletme_proje_id', 'kullanici_id')->withTimestamps();
    }
}
