<?php

namespace App\Models\Muhasebe;

use App\Models\Firma;
use App\Models\Concerns\HasFirmaTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokDepoBakiyesi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'stok_depo_bakiyeleri';

    protected $fillable = ['firma_id', 'depo_id', 'stok_id', 'miktar', 'rezerve_miktar'];

    protected function casts(): array
    {
        return [
            'miktar' => 'decimal:8',
            'rezerve_miktar' => 'decimal:8',
        ];
    }

    public function firma(): BelongsTo { return $this->belongsTo(Firma::class, 'firma_id'); }

    public function depo(): BelongsTo { return $this->belongsTo(Depo::class, 'depo_id'); }

    public function stokKarti(): BelongsTo { return $this->belongsTo(StokKarti::class, 'stok_id'); }
}
