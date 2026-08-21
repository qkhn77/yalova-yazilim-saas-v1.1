<?php

namespace App\Models\Restoran;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestoranGunSonuKapanisi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'restoran_gun_sonu_kapanislari';

    protected $fillable = [
        'firma_id',
        'tarih',
        'toplam_tahsilat',
        'toplam_muhasebe',
        'toplam_fark',
        'mutabik_mi',
        'kanal_ozeti',
        'fark_aciklamasi',
        'notlar',
        'kapatan_id',
        'kapandi_at',
    ];

    protected function casts(): array
    {
        return [
            'tarih' => 'date',
            'toplam_tahsilat' => 'decimal:2',
            'toplam_muhasebe' => 'decimal:2',
            'toplam_fark' => 'decimal:2',
            'mutabik_mi' => 'boolean',
            'kanal_ozeti' => 'array',
            'kapandi_at' => 'datetime',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function kapatan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kapatan_id');
    }
}
