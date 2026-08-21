<?php

namespace App\Models\Muhasebe;

use App\Models\Firma;
use App\Muhasebe\Enumlar\HareketDurumu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KasaHareketi extends Model
{
    protected $table = 'kasa_hareketleri';

    protected $fillable = [
        'firma_id',
        'finans_hareket_id',
        'kasa_hesap_id',
        'tutar',
        'para_birimi',
        'durum',
        'iptal_edilen_hareket_id',
    ];

    protected function casts(): array
    {
        return [
            'tutar' => 'decimal:2',
            'durum' => HareketDurumu::class,
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

    public function finansHareketi(): BelongsTo
    {
        return $this->belongsTo(FinansHareketi::class, 'finans_hareket_id');
    }

    public function kasaHesabi(): BelongsTo
    {
        return $this->belongsTo(KasaHesabi::class, 'kasa_hesap_id');
    }

    public function iptalEdilenHareket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'iptal_edilen_hareket_id');
    }
}
