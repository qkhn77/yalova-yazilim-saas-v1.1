<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknikServisFisNumarasi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'teknik_servis_fis_numaralari';

    protected $fillable = [
        'firma_id',
        'yil',
        'prefix',
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
