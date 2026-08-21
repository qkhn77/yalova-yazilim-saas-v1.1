<?php

namespace App\Models\Muhasebe;

use App\Models\Firma;
use App\Muhasebe\Enumlar\HareketDurumu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankaHareketi extends Model
{
    protected $table = 'banka_hareketleri';

    protected $fillable = [
        'firma_id',
        'finans_hareket_id',
        'banka_hesap_id',
        'tutar',
        'para_birimi',
        'dekont_no',
        'islem_referansi',
        'detay_aciklama',
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

    public function bankaHesabi(): BelongsTo
    {
        return $this->belongsTo(BankaHesabi::class, 'banka_hesap_id');
    }

    public function iptalEdilenHareket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'iptal_edilen_hareket_id');
    }
}
