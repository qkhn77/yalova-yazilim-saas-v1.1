<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlacakPlanOnayTalebi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'muhasebe_alacak_plan_onay_talepleri';

    protected $fillable = [
        'firma_id',
        'alacak_plan_id',
        'talep_turu',
        'durum',
        'risk_tutari',
        'para_birimi',
        'onceki_veri',
        'istenen_veri',
        'gerekce',
        'talep_eden_id',
        'karar_veren_id',
        'karar_notu',
        'karar_tarihi',
    ];

    protected function casts(): array
    {
        return [
            'risk_tutari' => 'decimal:2',
            'onceki_veri' => 'array',
            'istenen_veri' => 'array',
            'karar_tarihi' => 'datetime',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AlacakPlani::class, 'alacak_plan_id');
    }

    public function talepEden(): BelongsTo
    {
        return $this->belongsTo(User::class, 'talep_eden_id');
    }

    public function kararVeren(): BelongsTo
    {
        return $this->belongsTo(User::class, 'karar_veren_id');
    }
}
