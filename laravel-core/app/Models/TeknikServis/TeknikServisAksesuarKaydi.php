<?php

namespace App\Models\TeknikServis;

use App\Models\Firma;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TeknikServisAksesuarKaydi extends Pivot
{
    public $incrementing = true;

    protected $table = 'teknik_servis_aksesuar_kayitlari';

    protected $fillable = [
        'firma_id',
        'teknik_servis_kaydi_id',
        'aksesuar_id',
        'adet',
        'not',
    ];

    protected function casts(): array
    {
        return [
            'adet' => 'decimal:2',
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

    public function aksesuar(): BelongsTo
    {
        return $this->belongsTo(TeknikServisAksesuarTanimi::class, 'aksesuar_id');
    }
}
