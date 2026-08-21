<?php

namespace App\Models;

use App\Models\Concerns\HasFirmaTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SekreterHatirlatmasi extends \Illuminate\Database\Eloquent\Model
{
    use HasFirmaTenantScope;

    protected $table = 'sekreter_hatirlatmalari';
    protected $fillable = ['firma_id', 'hatirlanabilir_type', 'hatirlanabilir_id', 'hatirlatma_tipi', 'hatirlatma_zamani', 'gonderildi_at', 'okundu_at'];
    protected $casts = ['hatirlatma_zamani' => 'datetime', 'gonderildi_at' => 'datetime', 'okundu_at' => 'datetime'];

    public function hatirlanabilir(): MorphTo { return $this->morphTo(); }
    public function firma(): BelongsTo { return $this->belongsTo(Firma::class, 'firma_id'); }
}
