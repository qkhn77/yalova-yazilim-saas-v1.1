<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Masraf\MasrafAracDetayi;
use App\Models\Firma;
use App\Models\Proje\IsletmeProjesi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Masraf extends Model
{
    use HasFirmaTenantScope;

    public const DURUM_AKTIF = 'aktif';

    public const DURUM_IPTAL = 'iptal';

    protected $table = 'masraflar';

    protected $fillable = [
        'firma_id',
        'masraf_kategorisi_id',
        'isletme_proje_id',
        'kaynak_turu',
        'kaynak_id',
        'tarih',
        'tutar',
        'para_birimi',
        'aciklama',
        'notlar',
        'belge_yolu',
        'belge_adi',
        'belge_mime',
        'belge_boyutu',
        'belge_yukleyen_kullanici_id',
        'durum',
        'idempotency_key',
        'olusturan_kullanici_id',
        'iptal_eden_kullanici_id',
        'iptal_nedeni',
        'iptal_edildi_at',
    ];

    protected function casts(): array
    {
        return [
            'tarih' => 'date',
            'tutar' => 'decimal:2',
            'iptal_edildi_at' => 'datetime',
        ];
    }

    /** @param Builder<static> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('durum', self::DURUM_AKTIF);
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(MasrafKategorisi::class, 'masraf_kategorisi_id');
    }

    public function isletmeProjesi(): BelongsTo
    {
        return $this->belongsTo(IsletmeProjesi::class, 'isletme_proje_id');
    }

    public function faturaDagitimlari(): HasMany
    {
        return $this->hasMany(MasrafFaturaDagitimi::class, 'masraf_id');
    }

    public function faturalar(): HasManyThrough
    {
        return $this->hasManyThrough(
            Fatura::class,
            MasrafFaturaDagitimi::class,
            'masraf_id',
            'id',
            'id',
            'fatura_id',
        );
    }

    public function aracDetayi(): HasOne
    {
        return $this->hasOne(MasrafAracDetayi::class, 'masraf_id');
    }

    public function olusturanKullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_kullanici_id');
    }

    public function iptalEdenKullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iptal_eden_kullanici_id');
    }

    public function belgeYukleyenKullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'belge_yukleyen_kullanici_id');
    }
}
