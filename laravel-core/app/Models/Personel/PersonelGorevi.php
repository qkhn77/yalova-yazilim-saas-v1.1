<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Services\PersonelTakip\PersonelGorevKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonelGorevi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'personel_gorevleri';

    protected $fillable = [
        'firma_id',
        'departman_id',
        'ad',
        'kod',
        'varsayilan_maas_tipi',
        'varsayilan_ucret',
        'aktif_mi',
        'siralama',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $gorev): void {
            app(PersonelGorevKuralServisi::class)->dogrula($gorev);
        });
    }

    protected function casts(): array
    {
        return [
            'varsayilan_ucret' => 'decimal:2',
            'aktif_mi' => 'boolean',
            'siralama' => 'integer',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function departman(): BelongsTo
    {
        return $this->belongsTo(PersonelDepartmani::class, 'departman_id');
    }

    public function personeller(): HasMany
    {
        return $this->hasMany(Personel::class, 'gorev_id');
    }
}
