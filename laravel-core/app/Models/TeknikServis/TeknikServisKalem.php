<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\StokKarti;
use App\TeknikServis\Enumlar\TeknikServisKalemMuhasebeDurumu;
use App\TeknikServis\Enumlar\TeknikServisKalemRolu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeknikServisKalem extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'teknik_servis_kalemleri';

    protected $fillable = [
        'firma_id',
        'teknik_servis_kaydi_id',
        'satir_no',
        'kalem_tipi',
        'kalem_rolu',
        'muhasebe_durumu',
        'aciklama',
        'stok_id',
        'depo_id',
        'seri_nolari',
        'garanti_baslangic_tarihi',
        'garanti_bitis_tarihi',
        'birim',
        'fiyat_birimi_id',
        'miktar',
        'fiyat_miktari',
        'ana_miktar',
        'adet_esdegeri',
        'olcu_donusum_snapshot',
        'olcu_satis_birimi',
        'dogrudan_ortak_adet_fiyati',
        'birim_fiyat',
        'kdv_orani',
        'kdv_dahil_mi',
        'iskonto_tipi',
        'iskonto_orani',
        'iskonto_tutari',
        'indirim_orani',
        'indirim_tutari',
        'kdv_tutari',
        'satir_toplami',
        'toplam',
        'net_tutar',
        'satir_genel_toplam',
        'satir_indirim_tutari',
        'para_birimi',
    ];

    protected function casts(): array
    {
        return [
            'kalem_rolu' => TeknikServisKalemRolu::class,
            'muhasebe_durumu' => TeknikServisKalemMuhasebeDurumu::class,
            'satir_no' => 'integer',
            'depo_id' => 'integer',
            'fiyat_birimi_id' => 'integer',
            'seri_nolari' => 'array',
            'garanti_baslangic_tarihi' => 'date',
            'garanti_bitis_tarihi' => 'date',
            'fiyat_miktari' => 'decimal:8',
            'ana_miktar' => 'decimal:8',
            'adet_esdegeri' => 'decimal:8',
            'olcu_donusum_snapshot' => 'array',
            'dogrudan_ortak_adet_fiyati' => 'boolean',
            'miktar' => 'decimal:8',
            'birim_fiyat' => 'decimal:4',
            'kdv_orani' => 'decimal:2',
            'kdv_dahil_mi' => 'boolean',
            'iskonto_tipi' => 'string',
            'iskonto_orani' => 'decimal:2',
            'iskonto_tutari' => 'decimal:2',
            'indirim_orani' => 'decimal:2',
            'indirim_tutari' => 'decimal:2',
            'kdv_tutari' => 'decimal:2',
            'satir_toplami' => 'decimal:2',
            'toplam' => 'decimal:2',
            'net_tutar' => 'decimal:2',
            'satir_genel_toplam' => 'decimal:2',
            'satir_indirim_tutari' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $kalem): void {
            $kalem->kalem_tipi ??= 'stok_kalemi';
            $kalem->kalem_rolu ??= TeknikServisKalemRolu::Satis;
            $kalem->muhasebe_durumu ??= TeknikServisKalemMuhasebeDurumu::Taslak;
            $kalem->iskonto_tipi ??= 'oran';
            $kalem->indirim_orani ??= $kalem->iskonto_orani ?? 0;
            $kalem->indirim_tutari ??= $kalem->iskonto_tutari ?? 0;
            $kalem->toplam ??= $kalem->satir_toplami ?? 0;
            $kalem->satir_genel_toplam ??= $kalem->toplam ?? 0;
            $kalem->satir_indirim_tutari ??= $kalem->indirim_tutari ?? 0;
        });
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function teknikServisKaydi(): BelongsTo
    {
        return $this->belongsTo(TeknikServisKaydi::class, 'teknik_servis_kaydi_id');
    }

    public function stok(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_id');
    }
}
