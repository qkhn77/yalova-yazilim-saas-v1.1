<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknikServisIslemLogu extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'teknik_servis_islem_loglari';

    protected $fillable = [
        'firma_id',
        'teknik_servis_kaydi_id',
        'entity_type',
        'entity_id',
        'olay_kodu',
        'olay_etiketi',
        'eski_veri',
        'yeni_veri',
        'aciklama',
        'kritik_mi',
        'kullanici_id',
        'olay_tarihi',
    ];

    protected function casts(): array
    {
        return [
            'eski_veri' => 'array',
            'yeni_veri' => 'array',
            'kritik_mi' => 'boolean',
            'olay_tarihi' => 'datetime',
            'entity_id' => 'integer',
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

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }
}
