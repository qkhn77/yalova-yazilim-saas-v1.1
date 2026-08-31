<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KurFarkiHareketi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'kur_farki_hareketleri';

    protected $fillable = [
        'firma_id',
        'fatura_id',
        'finans_hareket_id',
        'fatura_finans_kapama_id',
        'tutar',
        'yon',
        'para_birimi',
        'durum',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'tutar' => 'decimal:8',
        ];
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'fatura_id');
    }

    public function finansHareketi(): BelongsTo
    {
        return $this->belongsTo(FinansHareketi::class, 'finans_hareket_id');
    }

    public function faturaFinansKapama(): BelongsTo
    {
        return $this->belongsTo(FaturaFinansKapama::class, 'fatura_finans_kapama_id');
    }
}
