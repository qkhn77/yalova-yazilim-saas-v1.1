<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Muhasebe\Enumlar\OlculuStokTakipTuru;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokHareketiOlcuDagilimi extends Model
{
    use HasFirmaTenantScope;
    protected $table = 'stok_hareketi_olcu_dagilimlari';
    protected $guarded = ['id'];
    protected function casts(): array { return ['takip_turu' => OlculuStokTakipTuru::class, 'ana_miktar' => 'decimal:8', 'adet_esdegeri' => 'decimal:8', 'girilen_miktar' => 'decimal:8', 'en' => 'decimal:8', 'boy' => 'decimal:8', 'yukseklik' => 'decimal:8', 'en_m' => 'decimal:8', 'boy_m' => 'decimal:8', 'yukseklik_m' => 'decimal:8', 'bir_adet_ana_miktar' => 'decimal:8']; }
    public function firma(): BelongsTo { return $this->belongsTo(Firma::class); }
    public function hareket(): BelongsTo { return $this->belongsTo(StokHareketi::class, 'stok_hareketi_id'); }
    public function stokKarti(): BelongsTo { return $this->belongsTo(StokKarti::class, 'stok_id'); }
    public function olcu(): BelongsTo { return $this->belongsTo(StokOlcusu::class, 'stok_olcusu_id'); }
    public function bakiye(): BelongsTo { return $this->belongsTo(StokOlcuBakiyesi::class, 'stok_olcu_bakiyesi_id'); }
    public function depo(): BelongsTo { return $this->belongsTo(Depo::class); }
    public function islemBirimi(): BelongsTo { return $this->belongsTo(Birim::class, 'islem_birimi_id'); }
}
