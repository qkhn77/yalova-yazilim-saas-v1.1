<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasTeknikServisTanimTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeknikServisMarkaTanimi extends Model
{
    use HasTeknikServisTanimTenantScope;
    use SoftDeletes;

    protected $table = 'teknik_servis_tanim_markalar';

    protected $fillable = [
        'firma_id',
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

    public function teknikServisKayitlari(): HasMany
    {
        return $this->hasMany(TeknikServisKaydi::class, 'marka_id');
    }
}
