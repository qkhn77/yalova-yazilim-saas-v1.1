<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Sube;
use App\Services\PersonelTakip\PersonelDepartmanKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonelDepartmani extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'personel_departmanlari';

    protected $fillable = [
        'firma_id',
        'sube_id',
        'ad',
        'kod',
        'aktif_mi',
        'siralama',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $departman): void {
            app(PersonelDepartmanKuralServisi::class)->dogrula($departman);
        });
    }

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
            'siralama' => 'integer',
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

    public function gorevler(): HasMany
    {
        return $this->hasMany(PersonelGorevi::class, 'departman_id');
    }

    public function personeller(): HasMany
    {
        return $this->hasMany(Personel::class, 'departman_id');
    }
}
