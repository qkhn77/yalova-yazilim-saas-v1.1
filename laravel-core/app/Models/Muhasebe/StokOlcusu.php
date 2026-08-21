<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Muhasebe\Enumlar\OlculuStokTakipTuru;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class StokOlcusu extends Model
{
    use HasFirmaTenantScope, SoftDeletes;

    protected $table = 'stok_olculeri';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['takip_turu' => OlculuStokTakipTuru::class, 'en' => 'decimal:8', 'boy' => 'decimal:8', 'yukseklik' => 'decimal:8', 'bir_adet_agirlik' => 'decimal:8', 'en_m' => 'decimal:8', 'boy_m' => 'decimal:8', 'yukseklik_m' => 'decimal:8', 'bir_adet_ana_miktar' => 'decimal:8', 'aktif_mi' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $olcu): void {
            if ($olcu->bakiyeler()->exists() || $olcu->hareketDagilimlari()->exists()) {
                throw new LogicException('Bakiye veya hareket görmüş ölçü silinemez; pasifleştirilmelidir.');
            }
        });
    }

    public function firma(): BelongsTo { return $this->belongsTo(Firma::class); }
    public function stokKarti(): BelongsTo { return $this->belongsTo(StokKarti::class, 'stok_id'); }
    public function bakiyeler(): HasMany { return $this->hasMany(StokOlcuBakiyesi::class, 'stok_olcusu_id'); }
    public function hareketDagilimlari(): HasMany { return $this->hasMany(StokHareketiOlcuDagilimi::class, 'stok_olcusu_id'); }
}
