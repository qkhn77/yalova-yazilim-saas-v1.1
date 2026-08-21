<?php

namespace App\Models\Ecommerce;

use Illuminate\Database\Eloquent\Model;

class EcommerceBildirimSablonu extends Model
{
    protected $table = 'ecommerce_bildirim_sablonlari';

    protected $fillable = [
        'firma_id',
        'olay',
        'kanal',
        'locale',
        'baslik',
        'icerik',
        'aktif_mi',
    ];

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
        ];
    }
}

