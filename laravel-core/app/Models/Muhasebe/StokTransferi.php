<?php

namespace App\Models\Muhasebe;

use App\Models\Firma;
use App\Models\Concerns\HasFirmaTenantScope;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StokTransferi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'stok_transferleri';

    protected $fillable = ['firma_id', 'transfer_no', 'kaynak_depo_id', 'hedef_depo_id', 'tarih', 'durum', 'aciklama'];

    protected function casts(): array
    {
        return ['tarih' => 'datetime'];
    }

    public function firma(): BelongsTo { return $this->belongsTo(Firma::class, 'firma_id'); }

    public function kaynakDepo(): BelongsTo { return $this->belongsTo(Depo::class, 'kaynak_depo_id'); }

    public function hedefDepo(): BelongsTo { return $this->belongsTo(Depo::class, 'hedef_depo_id'); }

    public function cikisHareketi(): HasOne
    {
        return $this->hasOne(StokHareketi::class, 'belge_id')
            ->where('belge_turu', StokBelgeTuru::Transfer)
            ->where('islem_turu', StokHareketIslemTuru::TransferCikis);
    }
}
