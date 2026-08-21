<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasrafFaturaDagitimi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'masraf_fatura_dagitilari';

    protected $fillable = [
        'firma_id',
        'masraf_id',
        'fatura_id',
        'tutar',
        'para_birimi',
    ];

    protected function casts(): array
    {
        return [
            'tutar' => 'decimal:2',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function masraf(): BelongsTo
    {
        return $this->belongsTo(Masraf::class, 'masraf_id');
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'fatura_id');
    }
}
