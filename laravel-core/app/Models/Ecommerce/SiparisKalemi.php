<?php

namespace App\Models\Ecommerce;

use App\Models\Muhasebe\StokKarti;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiparisKalemi extends Model
{
    protected $table = 'siparis_kalemleri';

    protected $fillable = [
        'siparis_id',
        'stok_karti_id',
        'depo_id',
        'urun_adi_snapshot',
        'urun_kodu_snapshot',
        'miktar',
        'stok_rezerv_miktari',
        'seri_nolari',
        'birim_fiyat',
        'para_birimi',
        'kdv_orani',
        'satir_toplami',
    ];

    protected function casts(): array
    {
        return [
            'miktar' => 'decimal:8',
            'depo_id' => 'integer',
            'stok_rezerv_miktari' => 'decimal:8',
            'seri_nolari' => 'array',
            'birim_fiyat' => 'decimal:2',
            'kdv_orani' => 'decimal:2',
            'satir_toplami' => 'decimal:2',
        ];
    }

    public function siparis(): BelongsTo
    {
        return $this->belongsTo(Siparis::class, 'siparis_id');
    }

    public function stokKarti(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_karti_id')->withoutGlobalScopes();
    }
}
