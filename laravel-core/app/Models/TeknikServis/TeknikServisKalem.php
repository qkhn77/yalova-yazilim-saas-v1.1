<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\StokKarti;
use App\TeknikServis\Enumlar\TeknikServisKalemMuhasebeDurumu;
use App\TeknikServis\Enumlar\TeknikServisKalemRolu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeknikServisKalem extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'teknik_servis_kalemleri';

    protected $fillable = [
        'firma_id',
        'teknik_servis_kaydi_id',
        'kalem_rolu',
        'muhasebe_durumu',
        'aciklama',
        'stok_id',
        'birim',
        'miktar',
        'birim_fiyat',
        'kdv_orani',
        'kdv_dahil_mi',
        'iskonto_tipi',
        'iskonto_orani',
        'iskonto_tutari',
        'satir_toplami',
        'para_birimi',
    ];

    protected function casts(): array
    {
        return [
            'kalem_rolu' => TeknikServisKalemRolu::class,
            'muhasebe_durumu' => TeknikServisKalemMuhasebeDurumu::class,
            'miktar' => 'decimal:8',
            'birim_fiyat' => 'decimal:4',
            'kdv_orani' => 'decimal:2',
            'kdv_dahil_mi' => 'boolean',
            'iskonto_tipi' => 'string',
            'iskonto_orani' => 'decimal:2',
            'iskonto_tutari' => 'decimal:2',
            'satir_toplami' => 'decimal:2',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function teknikServisKaydi(): BelongsTo
    {
        return $this->belongsTo(TeknikServisKaydi::class, 'teknik_servis_kaydi_id');
    }

    public function stok(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_id');
    }
}
