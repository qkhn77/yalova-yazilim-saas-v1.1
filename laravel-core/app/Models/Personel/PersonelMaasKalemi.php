<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonelMaasKalemi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'personel_maas_kalemleri';

    protected $fillable = [
        'firma_id',
        'maas_hareketi_id',
        'kalem_turu',
        'aciklama',
        'tutar',
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

    public function maasHareketi(): BelongsTo
    {
        return $this->belongsTo(PersonelMaasHareketi::class, 'maas_hareketi_id');
    }
}
