<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknikServisHatirlatma extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'teknik_servis_hatirlatmalari';

    protected $fillable = [
        'firma_id',
        'teknik_servis_kaydi_id',
        'hatirlatma_tipi',
        'periyot_ay',
        'ilk_tarih',
        'sonraki_tarih',
        'durum',
        'not',
        'olusturan_id',
    ];

    protected function casts(): array
    {
        return [
            'ilk_tarih' => 'date',
            'sonraki_tarih' => 'date',
            'periyot_ay' => 'integer',
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

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }
}
