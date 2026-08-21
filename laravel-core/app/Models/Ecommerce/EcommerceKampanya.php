<?php

namespace App\Models\Ecommerce;

use Illuminate\Database\Eloquent\Model;

class EcommerceKampanya extends Model
{
    protected $table = 'ecommerce_kampanyalar';

    protected $fillable = [
        'firma_id',
        'ad',
        'tip',
        'aktif_mi',
        'kupon_gerekli',
        'kupon_kodu',
        'baslangic_tarihi',
        'bitis_tarihi',
        'suresiz_mi',
        'oncelik',
        'birlesebilir_mi',
        'indirim_orani',
        'indirim_tutari',
        'x_adet',
        'y_adet',
        'hedef_tipi',
        'hedef_idler',
        'kullanici_basi_limit',
        'sistem_geneli_limit',
        'kullanilan_adet',
        'min_sepet_tutari',
        'ucretsiz_kargo',
        'para_birimi',
        'aciklama',
        'kosullar',
    ];

    protected $casts = [
        'aktif_mi' => 'bool',
        'kupon_gerekli' => 'bool',
        'baslangic_tarihi' => 'date',
        'bitis_tarihi' => 'date',
        'suresiz_mi' => 'bool',
        'oncelik' => 'int',
        'birlesebilir_mi' => 'bool',
        'indirim_orani' => 'decimal:4',
        'indirim_tutari' => 'decimal:2',
        'x_adet' => 'int',
        'y_adet' => 'int',
        'hedef_idler' => 'array',
        'kullanici_basi_limit' => 'int',
        'sistem_geneli_limit' => 'int',
        'kullanilan_adet' => 'int',
        'min_sepet_tutari' => 'decimal:2',
        'ucretsiz_kargo' => 'bool',
        'kosullar' => 'array',
    ];
}