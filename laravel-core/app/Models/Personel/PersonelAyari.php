<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonelAyari extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'personel_ayarlar';

    protected $fillable = [
        'firma_id',
        'anahtar',
        'deger',
    ];

    protected function casts(): array
    {
        return [
            'deger' => 'array',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }
}
