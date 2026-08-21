<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Services\PersonelTakip\PersonelMaasHareketKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonelMaasHareketi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'personel_maas_hareketleri';

    protected $fillable = [
        'firma_id',
        'maas_donemi_id',
        'personel_id',
        'brut_tutar',
        'fazla_mesai_tutari',
        'prim_tutari',
        'ek_odeme_tutari',
        'avans_kesintisi',
        'devamsizlik_kesintisi',
        'diger_kesinti',
        'net_tutar',
        'sgk_isveren_tutari',
        'issizlik_isveren_tutari',
        'gelir_vergisi_tutari',
        'damga_vergisi_tutari',
        'diger_maliyet_tutari',
        'maliyet_notu',
        'odenen_tutar',
        'kalan_tutar',
        'durum',
    ];

    protected function casts(): array
    {
        return [
            'brut_tutar' => 'decimal:2',
            'fazla_mesai_tutari' => 'decimal:2',
            'prim_tutari' => 'decimal:2',
            'ek_odeme_tutari' => 'decimal:2',
            'avans_kesintisi' => 'decimal:2',
            'devamsizlik_kesintisi' => 'decimal:2',
            'diger_kesinti' => 'decimal:2',
            'net_tutar' => 'decimal:2',
            'sgk_isveren_tutari' => 'decimal:2',
            'issizlik_isveren_tutari' => 'decimal:2',
            'gelir_vergisi_tutari' => 'decimal:2',
            'damga_vergisi_tutari' => 'decimal:2',
            'diger_maliyet_tutari' => 'decimal:2',
            'odenen_tutar' => 'decimal:2',
            'kalan_tutar' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $hareket): void {
            app(PersonelMaasHareketKuralServisi::class)->hazirlaVeDogrula($hareket);
        });
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function donem(): BelongsTo
    {
        return $this->belongsTo(PersonelMaasDonemi::class, 'maas_donemi_id');
    }

    public function personel(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'personel_id');
    }

    public function kalemler(): HasMany
    {
        return $this->hasMany(PersonelMaasKalemi::class, 'maas_hareketi_id');
    }

    public function odemeler(): HasMany
    {
        return $this->hasMany(PersonelMaasOdemeKaydi::class, 'maas_hareketi_id');
    }
}
