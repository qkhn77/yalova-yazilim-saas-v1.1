<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlacakPlanRevizyonu extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'muhasebe_alacak_plan_revizyonlari';

    protected $fillable = [
        'firma_id',
        'alacak_plan_id',
        'revizyon_turu',
        'onceki_veri',
        'sonraki_veri',
        'aciklama',
        'olusturan_id',
    ];

    protected function casts(): array
    {
        return [
            'onceki_veri' => 'array',
            'sonraki_veri' => 'array',
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

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }
}
