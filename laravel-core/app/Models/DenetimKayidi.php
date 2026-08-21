<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DenetimKayidi extends Model
{
    public $timestamps = false;

    protected $table = 'denetim_kayitlari';

    protected $fillable = [
        'firma_id',
        'kullanici_id',
        'olay',
        'konu_tipi',
        'konu_id',
        'eski_veri',
        'yeni_veri',
        'ip_adresi',
        'kullanici_ajan',
        'created_at',
    ];

    protected $casts = [
        'eski_veri' => 'array',
        'yeni_veri' => 'array',
        'created_at' => 'datetime',
    ];

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }
}

