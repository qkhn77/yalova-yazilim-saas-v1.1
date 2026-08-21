<?php

namespace App\Models\Restoran;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Services\Restoran\RestoranReceteKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestoranRecetesi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'restoran_receteleri';

    protected $fillable = [
        'firma_id',
        'menu_urunu_id',
        'ad',
        'aktif_mi',
        'notlar',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $recete): void {
            app(RestoranReceteKuralServisi::class)->receteDogrula($recete);
        });
    }

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function menuUrunu(): BelongsTo
    {
        return $this->belongsTo(RestoranMenuUrunu::class, 'menu_urunu_id');
    }

    public function kalemler(): HasMany
    {
        return $this->hasMany(RestoranReceteKalemi::class, 'recete_id');
    }
}
