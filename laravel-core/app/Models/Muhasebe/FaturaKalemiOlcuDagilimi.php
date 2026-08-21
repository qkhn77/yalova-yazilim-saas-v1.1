<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaturaKalemiOlcuDagilimi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'fatura_kalemi_olcu_dagilimlari';

    protected $fillable = [
        'firma_id', 'fatura_kalemi_id', 'kaynak_olcu_dagilimi_id', 'stok_id', 'stok_olcusu_id', 'stok_olcu_bakiyesi_id',
        'depo_id', 'stok_parcasi_id', 'islem_birimi_id', 'girilen_miktar', 'ana_miktar', 'adet_esdegeri',
        'sira', 'takip_turu', 'olcu_birimi', 'en', 'boy', 'yukseklik', 'en_m', 'boy_m', 'yukseklik_m', 'bir_adet_ana_miktar',
    ];

    protected function casts(): array
    {
        return array_fill_keys(['girilen_miktar', 'ana_miktar', 'adet_esdegeri', 'en', 'boy', 'yukseklik', 'en_m', 'boy_m', 'yukseklik_m', 'bir_adet_ana_miktar'], 'decimal:8') + ['sira' => 'integer'];
    }

    public function faturaKalemi(): BelongsTo { return $this->belongsTo(FaturaKalemi::class, 'fatura_kalemi_id'); }
    public function kaynakOlcuDagilimi(): BelongsTo { return $this->belongsTo(self::class, 'kaynak_olcu_dagilimi_id'); }
    public function stok(): BelongsTo { return $this->belongsTo(StokKarti::class, 'stok_id'); }
    public function olcu(): BelongsTo { return $this->belongsTo(StokOlcusu::class, 'stok_olcusu_id'); }
    public function bakiye(): BelongsTo { return $this->belongsTo(StokOlcuBakiyesi::class, 'stok_olcu_bakiyesi_id'); }
    public function depo(): BelongsTo { return $this->belongsTo(Depo::class, 'depo_id'); }
    public function parca(): BelongsTo { return $this->belongsTo(StokParcasi::class, 'stok_parcasi_id'); }
}
