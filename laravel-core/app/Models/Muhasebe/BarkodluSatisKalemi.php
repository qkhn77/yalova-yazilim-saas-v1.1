<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarkodluSatisKalemi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'muhasebe_barkodlu_satis_kalemleri';

    protected $fillable = [
        'firma_id',
        'satis_id',
        'stok_id',
        'depo_id',
        'barkod',
        'seri_nolari',
        'stok_adi',
        'birim',
        'miktar',
        'birim_fiyat',
        'iskonto_tutari',
        'kdv_orani',
        'kdv_tutari',
        'satir_toplami',
    ];

    protected function casts(): array
    {
        return [
            'miktar' => 'decimal:8',
            'seri_nolari' => 'array',
            'birim_fiyat' => 'decimal:2',
            'iskonto_tutari' => 'decimal:2',
            'kdv_orani' => 'decimal:2',
            'kdv_tutari' => 'decimal:2',
            'satir_toplami' => 'decimal:2',
            'depo_id' => 'integer',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function satis(): BelongsTo
    {
        return $this->belongsTo(BarkodluSatis::class, 'satis_id');
    }

    public function stok(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_id');
    }
}
