<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StokHareketi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'stok_hareketleri';

    protected $fillable = [
        'firma_id',
        'cari_id',
        'stok_id',
        'depo_id',
        'islem_turu',
        'miktar',
        'onceki_miktar',
        'sonraki_miktar',
        'birim_fiyat',
        'birim_maliyet',
        'toplam',
        'toplam_maliyet',
        'belge_turu',
        'referans_tipi',
        'belge_id',
        'referans_id',
        'tarih',
        'islem_tarihi',
        'durum',
        'aciklama',
        'iptal_edilen_hareket_id',
    ];

    protected function casts(): array
    {
        return [
            'islem_turu' => StokHareketIslemTuru::class,
            'belge_turu' => StokBelgeTuru::class,
            'durum' => StokHareketDurumu::class,
            'tarih' => 'datetime',
            'islem_tarihi' => 'datetime',
            'miktar' => 'decimal:8',
            'onceki_miktar' => 'decimal:8',
            'sonraki_miktar' => 'decimal:8',
            'birim_fiyat' => 'decimal:8',
            'birim_maliyet' => 'decimal:8',
            'toplam' => 'decimal:8',
            'toplam_maliyet' => 'decimal:8',
            'depo_id' => 'integer',
        ];
    }

    /** @param  Builder<static>  $sorgu */
    public function scopeFirma(Builder $sorgu, int $firmaId): Builder
    {
        return $sorgu->where('firma_id', $firmaId);
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function stokKarti(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_id');
    }

    public function depo(): BelongsTo
    {
        return $this->belongsTo(Depo::class, 'depo_id');
    }

    public function seriHareketleri(): HasMany
    {
        return $this->hasMany(StokHareketiSeri::class, 'stok_hareketi_id');
    }

    public function olcuDagilimlari(): HasMany { return $this->hasMany(StokHareketiOlcuDagilimi::class, 'stok_hareketi_id'); }

    public function iptalEdilenHareket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'iptal_edilen_hareket_id');
    }
}
