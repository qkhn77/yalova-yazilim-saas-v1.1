<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasTeknikServisTanimTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeknikServisArizaTanimi extends Model
{
    use HasTeknikServisTanimTenantScope;
    use SoftDeletes;

    protected $table = 'teknik_servis_tanim_arizalar';

    protected $fillable = [
        'firma_id',
        'cihaz_id',
        'ad',
        'kod',
        'aktif',
        'siralama',
        'varsayilan_mi',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'varsayilan_mi' => 'boolean',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function cihaz(): BelongsTo
    {
        return $this->belongsTo(TeknikServisCihazTanimi::class, 'cihaz_id');
    }

    public function teknikServisKayitlari(): HasMany
    {
        return $this->hasMany(TeknikServisKaydi::class, 'ariza_id');
    }

    public function teknikServisKayitCoklu(): BelongsToMany
    {
        return $this->belongsToMany(
            TeknikServisKaydi::class,
            'teknik_servis_ariza_kayitlari',
            'ariza_id',
            'teknik_servis_kaydi_id'
        )->withPivot(['firma_id', 'created_at', 'updated_at']);
    }
}
