<?php

namespace App\Models\Ecommerce;

use Illuminate\Database\Eloquent\Model;

class EcommerceKampanyaKullanimi extends Model
{
    protected $table = 'ecommerce_kampanya_kullanimlari';

    protected $fillable = [
        'firma_id',
        'kampanya_id',
        'kullanici_id',
        'siparis_id',
        'adet',
    ];

    protected $casts = [
        'firma_id' => 'int',
        'kampanya_id' => 'int',
        'kullanici_id' => 'int',
        'siparis_id' => 'int',
        'adet' => 'int',
    ];
}