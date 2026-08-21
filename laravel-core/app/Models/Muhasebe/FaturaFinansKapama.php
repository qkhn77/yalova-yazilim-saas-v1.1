<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaturaFinansKapama extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'fatura_finans_kapatmalari';

    protected $fillable = [
        'firma_id',
        'fatura_id',
        'finans_hareket_id',
        'uygulanan_tutar',
        'baz_uygulanan_tutar',
        'para_birimi',
        'baz_para_birimi',
        'kur',
    ];

    protected function casts(): array
    {
        return [
            'uygulanan_tutar' => 'decimal:8',
            'baz_uygulanan_tutar' => 'decimal:8',
            'kur' => 'decimal:8',
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
}
