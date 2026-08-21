<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarkodluSatisIade extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'muhasebe_barkodlu_satis_iadeler';

    protected $fillable = [
        'firma_id',
        'satis_id',
        'iade_no',
        'dogrulama_kodu',
        'iade_tarihi',
        'neden',
        'toplam_iade_tutari',
        'olusturan_id',
    ];

    protected function casts(): array
    {
        return [
            'iade_tarihi' => 'datetime',
            'toplam_iade_tutari' => 'decimal:2',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function satis(): BelongsTo
    {
        return $this->belongsTo(BarkodluSatis::class, 'satis_id');
    }

    public function kalemler(): HasMany
    {
        return $this->hasMany(BarkodluSatisIadeKalemi::class, 'iade_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }
}
