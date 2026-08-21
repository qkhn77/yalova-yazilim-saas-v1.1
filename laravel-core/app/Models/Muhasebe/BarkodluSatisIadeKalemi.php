<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarkodluSatisIadeKalemi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'muhasebe_barkodlu_satis_iade_kalemleri';

    protected $fillable = [
        'firma_id',
        'iade_id',
        'satis_kalem_id',
        'stok_id',
        'parca_kodu',
        'parca_dagilimi',
        'seri_nolari',
        'miktar',
        'birim_fiyat',
        'kdv_orani',
        'kdv_tutari',
        'satir_toplami',
    ];

    protected function casts(): array
    {
        return [
            'miktar' => 'decimal:8',
            'parca_dagilimi' => 'array',
            'seri_nolari' => 'array',
            'birim_fiyat' => 'decimal:2',
            'kdv_orani' => 'decimal:2',
            'kdv_tutari' => 'decimal:2',
            'satir_toplami' => 'decimal:2',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function iade(): BelongsTo
    {
        return $this->belongsTo(BarkodluSatisIade::class, 'iade_id');
    }

    public function satisKalemi(): BelongsTo
    {
        return $this->belongsTo(BarkodluSatisKalemi::class, 'satis_kalem_id');
    }

    public function stok(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_id');
    }
}
