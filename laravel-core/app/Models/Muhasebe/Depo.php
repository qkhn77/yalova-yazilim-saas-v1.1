<?php

namespace App\Models\Muhasebe;

use App\Models\Firma;
use App\Models\Concerns\HasFirmaTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Depo extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'muhasebe_depolar';

    protected $fillable = [
        'firma_id',
        'kod',
        'ad',
        'adres',
        'varsayilan_mi',
        'aktif_mi',
    ];

    protected function casts(): array
    {
        return [
            'varsayilan_mi' => 'boolean',
            'aktif_mi' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $depo): void {
            if (! $depo->varsayilan_mi) {
                return;
            }

            static::query()
                ->where('firma_id', $depo->firma_id)
                ->whereKeyNot($depo->getKey() ?: 0)
                ->update(['varsayilan_mi' => false]);
        });
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif_mi', true);
    }
}
