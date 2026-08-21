<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasTeknikServisTanimTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeknikServisAksesuarTanimi extends Model
{
    use HasTeknikServisTanimTenantScope;
    use SoftDeletes;

    protected $table = 'teknik_servis_tanim_aksesuarlar';

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

    public function teknikServisKayitlari(): BelongsToMany
    {
        return $this->belongsToMany(
            TeknikServisKaydi::class,
            'teknik_servis_aksesuar_kayitlari',
            'aksesuar_id',
            'teknik_servis_kaydi_id'
        )->using(TeknikServisAksesuarKaydi::class)
            ->withPivot(['firma_id', 'adet', 'not', 'created_at', 'updated_at']);
    }
}
