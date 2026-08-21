<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Sube;
use App\Services\PersonelTakip\PersonelVardiyaSablonuKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonelVardiyaSablonu extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'personel_vardiya_sablonlari';

    protected $fillable = [
        'firma_id',
        'sube_id',
        'ad',
        'baslangic_saati',
        'bitis_saati',
        'mola_dakika',
        'renk',
        'aktif_mi',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $sablon): void {
            app(PersonelVardiyaSablonuKuralServisi::class)->dogrula($sablon);
        });
    }

    protected function casts(): array
    {
        return [
            'mola_dakika' => 'integer',
            'aktif_mi' => 'boolean',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function sube(): BelongsTo
    {
        return $this->belongsTo(Sube::class, 'sube_id');
    }

    public function vardiyalar(): HasMany
    {
        return $this->hasMany(PersonelVardiyasi::class, 'vardiya_sablonu_id');
    }
}
