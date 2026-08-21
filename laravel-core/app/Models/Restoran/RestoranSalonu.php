<?php

namespace App\Models\Restoran;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Sube;
use App\Services\Restoran\RestoranSalonKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestoranSalonu extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'restoran_salonlari';

    protected $fillable = [
        'firma_id',
        'sube_id',
        'ad',
        'kod',
        'aktif_mi',
        'siralama',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $salon): void {
            app(RestoranSalonKuralServisi::class)->dogrula($salon);
        });
    }

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
            'siralama' => 'integer',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function sube(): BelongsTo
    {
        return $this->belongsTo(Sube::class, 'sube_id');
    }

    public function masalar(): HasMany
    {
        return $this->hasMany(RestoranMasasi::class, 'salon_id');
    }
}
