<?php

namespace App\Models\Masraf;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\Masraf;
use App\Models\Muhasebe\MasrafKategorisi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DuzenliFaturaTanimi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'duzenli_fatura_tanimlari';

    protected $fillable = [
        'firma_id',
        'masraf_kategorisi_id',
        'ad',
        'abone_no',
        'tedarikci',
        'aktif_mi',
        'notlar',
    ];

    protected function casts(): array
    {
        return ['aktif_mi' => 'boolean'];
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

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(MasrafKategorisi::class, 'masraf_kategorisi_id');
    }

    public function masraflar(): HasMany
    {
        return $this->hasMany(Masraf::class, 'kaynak_id')->where('kaynak_turu', 'duzenli_fatura');
    }
}
