<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaturaNumaraSayaci extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'fatura_numara_sayaclari';

    protected $fillable = [
        'firma_id',
        'yil',
        'son_sira',
    ];

    protected function casts(): array
    {
        return [
            'yil' => 'integer',
            'son_sira' => 'integer',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }
}
