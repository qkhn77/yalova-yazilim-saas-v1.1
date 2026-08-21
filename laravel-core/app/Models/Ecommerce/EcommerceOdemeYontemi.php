<?php

namespace App\Models\Ecommerce;

use Illuminate\Database\Eloquent\Model;

class EcommerceOdemeYontemi extends Model
{
    protected $table = 'ecommerce_odeme_yontemleri';

    protected $fillable = [
        'firma_id',
        'kod',
        'ad',
        'saglayici',
        'aktif_mi',
        'varsayilan_mi',
        'uc_d_secure_zorunlu',
        'taksit_aktif',
        'max_taksit',
        'komisyon_orani',
        'para_birimleri',
        'iade_api_aktif',
        'yeniden_deneme_aktif',
        'max_yeniden_deneme',
        'webhook_dogrulama_anahtari',
        'saglayici_ayarlar',
    ];

    protected $casts = [
        'aktif_mi' => 'bool',
        'varsayilan_mi' => 'bool',
        'uc_d_secure_zorunlu' => 'bool',
        'taksit_aktif' => 'bool',
        'max_taksit' => 'int',
        'komisyon_orani' => 'decimal:4',
        'para_birimleri' => 'array',
        'iade_api_aktif' => 'bool',
        'yeniden_deneme_aktif' => 'bool',
        'max_yeniden_deneme' => 'int',
        'saglayici_ayarlar' => 'array',
    ];
}