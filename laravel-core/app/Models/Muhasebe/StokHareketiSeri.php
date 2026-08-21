<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokHareketiSeri extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'stok_hareketi_serileri';
    protected $fillable = ['firma_id', 'stok_hareketi_id', 'stok_seri_no_id'];

    public function stokHareketi(): BelongsTo { return $this->belongsTo(StokHareketi::class, 'stok_hareketi_id'); }
    public function seriNo(): BelongsTo { return $this->belongsTo(StokSeriNo::class, 'stok_seri_no_id'); }
}
