<?php

namespace App\Models\Restoran;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Muhasebe\StokKarti;
use App\Services\Restoran\RestoranReceteKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestoranReceteKalemi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'restoran_recete_kalemleri';

    protected $fillable = [
        'firma_id',
        'recete_id',
        'stok_karti_id',
        'miktar',
        'fire_orani',
        'notlar',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $kalem): void {
            app(RestoranReceteKuralServisi::class)->kalemDogrula($kalem);
        });
    }

    protected function casts(): array
    {
        return [
            'miktar' => 'decimal:8',
            'fire_orani' => 'decimal:2',
        ];
    }

    public function recete(): BelongsTo
    {
        return $this->belongsTo(RestoranRecetesi::class, 'recete_id');
    }

    public function stokKarti(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_karti_id');
    }
}
