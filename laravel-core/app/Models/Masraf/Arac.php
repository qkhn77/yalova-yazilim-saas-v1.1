<?php

namespace App\Models\Masraf;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\Masraf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Arac extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'araclar';

    protected $fillable = [
        'firma_id',
        'plaka',
        'marka',
        'model',
        'model_yili',
        'yakit_tipi',
        'kilometre',
        'sigorta_bitis',
        'muayene_bitis',
        'aktif_mi',
        'notlar',
    ];

    protected function casts(): array
    {
        return [
            'model_yili' => 'integer',
            'kilometre' => 'integer',
            'sigorta_bitis' => 'date',
            'muayene_bitis' => 'date',
            'aktif_mi' => 'boolean',
        ];
    }

    /** @param Builder<static> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif_mi', true);
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function masraflar(): HasMany
    {
        return $this->hasMany(Masraf::class, 'kaynak_id')->where('kaynak_turu', 'arac');
    }

    public function masrafDetaylari(): HasMany
    {
        return $this->hasMany(MasrafAracDetayi::class, 'arac_id');
    }
}
