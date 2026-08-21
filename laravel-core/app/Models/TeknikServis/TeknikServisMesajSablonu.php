<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknikServisMesajSablonu extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'teknik_servis_mesaj_sablonlari';

    protected $fillable = [
        'firma_id',
        'kanal',
        'kod',
        'ad',
        'mesaj',
        'aktif',
        'siralama',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'siralama' => 'integer',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }
}
