<?php

namespace App\Models;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelDepartmani;
use App\Models\Personel\PersonelVardiyaSablonu;
use App\Models\Personel\PersonelVardiyasi;
use App\Services\PersonelTakip\SubeKayitKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sube extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'subeler';

    protected $fillable = [
        'firma_id',
        'ad',
        'kod',
        'telefon',
        'adres',
        'aktif_mi',
    ];

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $sube): void {
            app(SubeKayitKuralServisi::class)->dogrula($sube);
        });
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function personeller(): HasMany
    {
        return $this->hasMany(Personel::class, 'sube_id');
    }

    public function personelDepartmanlari(): HasMany
    {
        return $this->hasMany(PersonelDepartmani::class, 'sube_id');
    }

    public function personelVardiyaSablonlari(): HasMany
    {
        return $this->hasMany(PersonelVardiyaSablonu::class, 'sube_id');
    }

    public function personelVardiyalari(): HasMany
    {
        return $this->hasMany(PersonelVardiyasi::class, 'sube_id');
    }
}
