<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FinansHareketi;
use App\TeknikServis\Enumlar\TeknikServisMuhasebeIslemTipi;
use App\TeknikServis\Enumlar\TeknikServisMuhasebeSenkronDurumu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknikServisMuhasebeBaglantisi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'teknik_servis_muhasebe_baglantilari';

    protected $fillable = [
        'firma_id',
        'teknik_servis_kaydi_id',
        'islem_tipi',
        'idempotency_key',
        'satis_faturasi_id',
        'gider_faturasi_id',
        'finans_hareketi_id',
        'son_senkron_tarihi',
        'senkron_durumu',
        'hata_mesaji',
    ];

    protected function casts(): array
    {
        return [
            'islem_tipi' => TeknikServisMuhasebeIslemTipi::class,
            'senkron_durumu' => TeknikServisMuhasebeSenkronDurumu::class,
            'son_senkron_tarihi' => 'datetime',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function teknikServisKaydi(): BelongsTo
    {
        return $this->belongsTo(TeknikServisKaydi::class, 'teknik_servis_kaydi_id');
    }

    public function satisFaturasi(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'satis_faturasi_id');
    }

    public function giderFaturasi(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'gider_faturasi_id');
    }

    public function finansHareketi(): BelongsTo
    {
        return $this->belongsTo(FinansHareketi::class, 'finans_hareketi_id');
    }
}
