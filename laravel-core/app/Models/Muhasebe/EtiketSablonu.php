<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtiketSablonu extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'muhasebe_etiket_sablonlari';

    protected $fillable = [
        'firma_id',
        'ad',
        'kod',
        'genislik_mm',
        'yukseklik_mm',
        'barkod_tipi',
        'tasarim_tipi',
        'varsayilan_mi',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'genislik_mm' => 'integer',
            'yukseklik_mm' => 'integer',
            'varsayilan_mi' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }
}
