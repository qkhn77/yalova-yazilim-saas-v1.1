<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StokOlcuBakiyesi extends Model
{
    use HasFirmaTenantScope;
    protected $table = 'stok_olcu_bakiyeleri';
    protected $guarded = ['id'];
    protected function casts(): array { return ['ana_miktar' => 'decimal:8', 'adet_esdegeri' => 'decimal:8', 'rezerve_ana_miktar' => 'decimal:8', 'rezerve_adet_esdegeri' => 'decimal:8', 'donusum_ana_miktari' => 'decimal:8']; }
    public function firma(): BelongsTo { return $this->belongsTo(Firma::class); }
    public function stokKarti(): BelongsTo { return $this->belongsTo(StokKarti::class, 'stok_id'); }
    public function olcu(): BelongsTo { return $this->belongsTo(StokOlcusu::class, 'stok_olcusu_id'); }
    public function depo(): BelongsTo { return $this->belongsTo(Depo::class); }
    public function hareketDagilimlari(): HasMany { return $this->hasMany(StokHareketiOlcuDagilimi::class, 'stok_olcu_bakiyesi_id'); }
}
