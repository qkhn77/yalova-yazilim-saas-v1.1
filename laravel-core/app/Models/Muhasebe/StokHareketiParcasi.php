<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokHareketiParcasi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'stok_hareketi_parcalari';

    protected $fillable = ['firma_id', 'stok_hareketi_id', 'stok_parcasi_id', 'miktar', 'birim_maliyet'];

    protected function casts(): array
    {
        return ['miktar' => 'decimal:8', 'birim_maliyet' => 'decimal:8'];
    }

    public function stokHareketi(): BelongsTo { return $this->belongsTo(StokHareketi::class, 'stok_hareketi_id'); }
    public function stokParcasi(): BelongsTo { return $this->belongsTo(StokParcasi::class, 'stok_parcasi_id'); }
}
