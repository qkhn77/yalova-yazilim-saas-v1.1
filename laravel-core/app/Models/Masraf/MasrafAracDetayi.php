<?php

namespace App\Models\Masraf;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\Masraf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasrafAracDetayi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'masraf_arac_detaylari';

    protected $fillable = [
        'firma_id',
        'masraf_id',
        'arac_id',
        'yakit_litre',
        'litre_fiyati',
        'kilometre',
    ];

    protected function casts(): array
    {
        return [
            'yakit_litre' => 'decimal:3',
            'litre_fiyati' => 'decimal:4',
            'kilometre' => 'integer',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function masraf(): BelongsTo
    {
        return $this->belongsTo(Masraf::class, 'masraf_id');
    }

    public function arac(): BelongsTo
    {
        return $this->belongsTo(Arac::class, 'arac_id');
    }
}
