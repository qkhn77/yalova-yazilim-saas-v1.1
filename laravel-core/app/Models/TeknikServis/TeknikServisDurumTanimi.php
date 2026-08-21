<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasTeknikServisTanimTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeknikServisDurumTanimi extends Model
{
    use HasTeknikServisTanimTenantScope;
    use SoftDeletes;

    protected $table = 'teknik_servis_tanim_servis_durumlari';

    protected $fillable = [
        'firma_id',
        'ad',
        'kod',
        'aktif',
        'siralama',
        'varsayilan_mi',
        'is_fiyat_verildi',
        'is_teslim_edildi',
        'is_iptal',
        'is_iade',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'varsayilan_mi' => 'boolean',
            'is_fiyat_verildi' => 'boolean',
            'is_teslim_edildi' => 'boolean',
            'is_iptal' => 'boolean',
            'is_iade' => 'boolean',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function teknikServisKayitlari(): HasMany
    {
        return $this->hasMany(TeknikServisKaydi::class, 'servis_durumu_id');
    }
}
