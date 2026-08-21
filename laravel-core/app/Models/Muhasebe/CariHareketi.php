<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Proje\IsletmeProjesi;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CariHareketi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'cari_hareketleri';

    protected $fillable = [
        'firma_id',
        'isletme_proje_id',
        'cari_id',
        'belge_turu',
        'belge_id',
        'islem_tarihi',
        'vade_tarihi',
        'borc',
        'baz_borc',
        'alacak',
        'baz_alacak',
        'para_birimi',
        'baz_para_birimi',
        'kur',
        'aciklama',
        'durum',
        'iptal_edilen_hareket_id',
    ];

    protected function casts(): array
    {
        return [
            'belge_turu' => CariHareketBelgeTuru::class,
            'durum' => CariHareketDurumu::class,
            'islem_tarihi' => 'datetime',
            'vade_tarihi' => 'date',
            'borc' => 'decimal:8',
            'baz_borc' => 'decimal:8',
            'alacak' => 'decimal:8',
            'baz_alacak' => 'decimal:8',
            'kur' => 'decimal:8',
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

    public function isletmeProjesi(): BelongsTo
    {
        return $this->belongsTo(IsletmeProjesi::class, 'isletme_proje_id');
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'belge_id');
    }

    public function finansHareketi(): BelongsTo
    {
        return $this->belongsTo(FinansHareketi::class, 'belge_id');
    }

    public function iptalEdilenHareket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'iptal_edilen_hareket_id');
    }
}
