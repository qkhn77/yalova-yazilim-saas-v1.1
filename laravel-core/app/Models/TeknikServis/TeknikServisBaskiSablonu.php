<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknikServisBaskiSablonu extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'teknik_servis_baski_sablonlari';

    protected $fillable = [
        'firma_id',
        'sablon_turu',
        'ad',
        'kod',
        'sayfa_tipi',
        'sablon_logo',
        'sablon_html',
        'sablon_css',
        'varsayilan_mi',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'varsayilan_mi' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }
}
