<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CariAdresi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'cari_adresleri';

    protected $fillable = [
        'firma_id',
        'cari_id',
        'baslik',
        'tur',
        'adres',
        'ulke',
        'il',
        'ilce',
        'posta_kodu',
        'sira',
    ];

    protected function casts(): array
    {
        return ['sira' => 'integer'];
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }
}
