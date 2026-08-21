<?php

namespace App\Models\Ecommerce;

use App\Models\Muhasebe\StokKarti;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SepetKalemi extends Model
{
    protected $table = 'sepet_kalemleri';

    protected $fillable = [
        'sepet_id',
        'stok_karti_id',
        'urun_adi_snapshot',
        'urun_kodu_snapshot',
        'birim_fiyat',
        'para_birimi',
        'kdv_orani',
        'miktar',
        'satir_toplami',
    ];

    protected function casts(): array
    {
        return [
            'birim_fiyat' => 'decimal:2',
            'kdv_orani' => 'decimal:2',
            'miktar' => 'decimal:8',
            'satir_toplami' => 'decimal:2',
        ];
    }

    public function sepet(): BelongsTo
    {
        return $this->belongsTo(Sepet::class, 'sepet_id');
    }

    public function stokKarti(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_karti_id')->withoutGlobalScopes();
    }
}
