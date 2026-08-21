<?php

namespace App\Models\Muhasebe;

use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetteFaturaGelenBelge extends Model
{
    protected $table = 'nette_fatura_gelen_belgeler';

    protected $fillable = [
        'firma_id',
        'belge_turu',
        'provider_invoice_id',
        'invoice_number',
        'invoice_date',
        'company_name',
        'total_amount',
        'currency_code',
        'status',
        'report_status',
        'cancel_report_status',
        'ettn',
        'raw_payload',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'total_amount' => 'decimal:8',
            'raw_payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }
}
