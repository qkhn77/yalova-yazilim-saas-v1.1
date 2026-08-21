<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Servisler\CariKoduUretici;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cari extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'cariler';

    protected static function booted(): void
    {
        static::saving(function (self $cari): void {
            if (self::normalTelefonAlanlariVarMi()) {
                $cari->telefon_normalize = self::telefonuNormalizeEt($cari->telefon);
                $cari->gsm_normalize = self::telefonuNormalizeEt($cari->gsm);
            }
        });

        static::creating(function (self $cari): void {
            if (filled($cari->kod)) {
                return;
            }

            $cari->kod = app(CariKoduUretici::class)->sonraki((int) $cari->firma_id);
        });
    }

    private static function normalTelefonAlanlariVarMi(): bool
    {
        return Schema::hasTable('cariler')
            && Schema::hasColumn('cariler', 'telefon_normalize')
            && Schema::hasColumn('cariler', 'gsm_normalize');
    }

    protected $fillable = [
        'firma_id',
        'kullanici_id',
        'cari_grubu_id',
        'kod',
        'ad',
        'kisa_ad',
        'tur',
        'vergi_dairesi',
        'vergi_no',
        'tc_no',
        'telefon',
        'telefon_normalize',
        'gsm',
        'gsm_normalize',
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
        'durum',
    ];

    protected function casts(): array
    {
        return [
            'tur' => CariTuru::class,
            'durum' => CariDurumu::class,
            'risk_limiti' => 'decimal:2',
            'vade_gunu' => 'integer',
        ];
    }

    /** @param  Builder<static>  $sorgu */
    public function scopeFirma(Builder $sorgu, int $firmaId): Builder
    {
        return $sorgu->where('firma_id', $firmaId);
    }

    private static function telefonuNormalizeEt(mixed $telefon): ?string
    {
        $normalize = preg_replace('/\D+/', '', (string) ($telefon ?? '')) ?? '';

        return $normalize !== '' ? $normalize : null;
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }

    public function cariGrubu(): BelongsTo
    {
        return $this->belongsTo(CariGrubu::class, 'cari_grubu_id');
    }

    public function yetkiliKisiler(): HasMany
    {
        return $this->hasMany(CariYetkiliKisi::class, 'cari_id')->orderBy('sira')->orderBy('id');
    }

    public function adresler(): HasMany
    {
        return $this->hasMany(CariAdresi::class, 'cari_id')->orderBy('sira')->orderBy('id');
    }

    public function bankaHesaplari(): HasMany
    {
        return $this->hasMany(CariBankaHesabi::class, 'cari_id')->orderBy('sira')->orderBy('id');
    }

    public function cariHareketleri(): HasMany
    {
        return $this->hasMany(CariHareketi::class, 'cari_id');
    }

    public function faturalar(): HasMany
    {
        return $this->hasMany(Fatura::class, 'cari_id');
    }

    public function finansHareketleri(): HasMany
    {
        return $this->hasMany(FinansHareketi::class, 'cari_id');
    }

    public function alacakPlanlari(): HasMany
    {
        return $this->hasMany(AlacakPlani::class, 'cari_id');
    }

    public function alacakTaksitleri(): HasMany
    {
        return $this->hasMany(AlacakPlanTaksiti::class, 'cari_id');
    }

    public function alacakTakipNotlari(): HasMany
    {
        return $this->hasMany(AlacakTakipNotu::class, 'cari_id');
    }
}
