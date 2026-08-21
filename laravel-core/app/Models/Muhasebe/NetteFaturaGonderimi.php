<?php

namespace App\Models\Muhasebe;

use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetteFaturaGonderimi extends Model
{
    protected $table = 'nette_fatura_gonderimleri';

    protected $fillable = [
        'firma_id',
        'fatura_id',
        'islem_tipi',
        'durum',
        'endpoint',
        'dosya_adi',
        'document_hash',
        'request_hash',
        'provider_instance_identifier',
        'response_message',
        'error_message',
        'request_meta',
        'response_meta',
        'sent_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'request_meta' => 'array',
            'response_meta' => 'array',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'fatura_id');
    }
}
