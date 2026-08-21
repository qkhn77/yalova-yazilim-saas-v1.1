<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokBarkodu extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'stok_barkodlari';

    protected $fillable = [
        'firma_id',
        'stok_id',
        'barkod',
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

    public function stok(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_id');
    }
}

