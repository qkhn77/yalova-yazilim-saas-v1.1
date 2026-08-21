<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaturaKalemi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'fatura_kalemleri';

    protected $fillable = [
        'firma_id',
        'fatura_id',
        'kaynak_fatura_kalemi_id',
        'satir_no',
        'kalem_tipi',
        'stok_id',
        'depo_id',
        'seri_nolari',
        'garanti_baslangic_tarihi',
        'garanti_bitis_tarihi',
        'birim',
        'hizmet_mi',
        'aciklama',
        'miktar',
        'ana_miktar',
        'olcu_donusum_snapshot',
        'birim_fiyat',
        'baz_birim_fiyat',
        'indirim_orani',
        'kdv_orani',
        'satir_indirim_tutari',
        'indirim_tutari',
        'baz_indirim_tutari',
        'net_tutar',
        'baz_net_tutar',
        'kdv_tutari',
        'baz_kdv_tutari',
        'satir_toplami',
        'baz_satir_toplami',
        'satir_genel_toplam',
        'baz_satir_genel_toplam',
        'para_birimi',
        'baz_para_birimi',
        'toplam',
    ];

    protected function casts(): array
    {
        return [
            'hizmet_mi' => 'boolean',
            'seri_nolari' => 'array',
            'garanti_baslangic_tarihi' => 'date',
            'garanti_bitis_tarihi' => 'date',
            'satir_no' => 'integer',
            'miktar' => 'decimal:8',
            'ana_miktar' => 'decimal:8',
            'birim_fiyat' => 'decimal:8',
            'baz_birim_fiyat' => 'decimal:8',
            'indirim_orani' => 'decimal:2',
            'indirim_tutari' => 'decimal:8',
            'baz_indirim_tutari' => 'decimal:8',
            'kdv_orani' => 'decimal:2',
            'satir_indirim_tutari' => 'decimal:8',
            'net_tutar' => 'decimal:8',
            'baz_net_tutar' => 'decimal:8',
            'kdv_tutari' => 'decimal:8',
            'baz_kdv_tutari' => 'decimal:8',
            'satir_toplami' => 'decimal:8',
            'baz_satir_toplami' => 'decimal:8',
            'satir_genel_toplam' => 'decimal:8',
            'baz_satir_genel_toplam' => 'decimal:8',
            'toplam' => 'decimal:8',
            'depo_id' => 'integer',
        ];
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'fatura_id');
    }

    public function kaynakFaturaKalemi(): BelongsTo
    {
        return $this->belongsTo(self::class, 'kaynak_fatura_kalemi_id');
    }

    public function stokKarti(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_id');
    }

    public function olcuDagilimlari()
    {
        return $this->hasMany(FaturaKalemiOlcuDagilimi::class, 'fatura_kalemi_id');
    }
}
