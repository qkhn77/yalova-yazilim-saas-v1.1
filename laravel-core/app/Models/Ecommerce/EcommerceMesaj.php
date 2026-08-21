<?php

namespace App\Models\Ecommerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceMesaj extends Model
{
    protected $table = 'ecommerce_mesajlar';

    protected $fillable = [
        'konu_id',
        'firma_id',
        'kullanici_id',
        'gonderen_tipi',
        'ic_not_mu',
        'icerik',
        'ekler',
        'okundu_at',
    ];

    protected $casts = [
        'ic_not_mu' => 'bool',
        'ekler' => 'array',
        'okundu_at' => 'datetime',
    ];

    public function konu(): BelongsTo
    {
        return $this->belongsTo(EcommerceMesajKonu::class, 'konu_id');
    }
}