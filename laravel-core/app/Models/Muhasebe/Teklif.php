<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teklif extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    public const DURUMLAR = [
        'taslak' => 'Taslak',
        'hazirlaniyor' => 'Hazırlanıyor',
        'gonderildi' => 'Müşteriye Gönderildi',
        'revizyon_bekliyor' => 'Revizyon Bekliyor',
        'onaylandi' => 'Onaylandı',
        'reddedildi' => 'Reddedildi',
        'suresi_doldu' => 'Süresi Doldu',
    ];

    protected $table = 'teklifler';

    protected $fillable = [
        'firma_id',
        'cari_id',
        'teklif_no',
        'durum',
        'baslik',
        'tarih',
        'gecerlilik_tarihi',
        'teklif_baski_sablonu_id',
        'para_birimi',
        'kur_seti',
        'kur_seti_alindi_at',
        'kur_seti_kaynagi',
        'kur_seti_kur_tipi',
        'ara_toplam',
        'toplam_indirim',
        'kdv_toplam',
        'genel_toplam',
        'aciklama',
        'notlar',
        'kosullar',
        'odeme_plani',
        'teslim_suresi',
        'revizyon_no',
        'gonderildi_at',
        'yanitlandi_at',
        'faturaya_donustu_fatura_id',
    ];

    protected function casts(): array
    {
        return [
            'tarih' => 'datetime',
            'gecerlilik_tarihi' => 'date',
            'kur_seti_alindi_at' => 'datetime',
            'ara_toplam' => 'decimal:2',
            'toplam_indirim' => 'decimal:2',
            'kdv_toplam' => 'decimal:2',
            'genel_toplam' => 'decimal:2',
            'revizyon_no' => 'integer',
            'gonderildi_at' => 'datetime',
            'yanitlandi_at' => 'datetime',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function baskiSablonu(): BelongsTo
    {
        return $this->belongsTo(TeklifBaskiSablonu::class, 'teklif_baski_sablonu_id');
    }

    public function kalemler(): HasMany
    {
        return $this->hasMany(TeklifKalemi::class, 'teklif_id');
    }

    public function faturayaDonusenFatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'faturaya_donustu_fatura_id');
    }

    /**
     * @return array{ara_toplam: float, toplam_indirim: float, kdv_toplam: float, genel_toplam: float}
     */
    public function kalemToplamlariniHesapla(): array
    {
        $kalemler = $this->relationLoaded('kalemler')
            ? $this->kalemler
            : TeklifKalemi::tenantScopeOlmadan(fn () => $this->kalemler()
                ->get(['miktar', 'birim_fiyat', 'net_tutar', 'kdv_tutari', 'toplam']));

        $araToplam = 0.0;
        $toplamIndirim = 0.0;
        $kdvToplam = 0.0;
        $genelToplam = 0.0;

        foreach ($kalemler as $kalem) {
            $satirAraToplam = round((float) $kalem->miktar * (float) $kalem->birim_fiyat, 2);

            $araToplam += $satirAraToplam;
            $toplamIndirim += round($satirAraToplam - (float) $kalem->net_tutar, 2);
            $kdvToplam += (float) $kalem->kdv_tutari;
            $genelToplam += (float) $kalem->toplam;
        }

        return [
            'ara_toplam' => round($araToplam, 2),
            'toplam_indirim' => round($toplamIndirim, 2),
            'kdv_toplam' => round($kdvToplam, 2),
            'genel_toplam' => round($genelToplam, 2),
        ];
    }

    public function toplamlariniKalemlerdenGuncelle(): void
    {
        $this->forceFill($this->kalemToplamlariniHesapla())->saveQuietly();
    }
}
