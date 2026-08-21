<?php

namespace App\Models\TeklifYonetimi;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeklifBaskiSablonu extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'teklif_baski_sablonlari';

    protected $fillable = [
        'firma_id',
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
