<?php

namespace App\Models\Ecommerce;

use Illuminate\Database\Eloquent\Model;

class EcommerceKargoYontemi extends Model
{
    protected $table = 'ecommerce_kargo_yontemleri';

    protected $fillable = [
        'firma_id',
        'ad',
        'kod',
        'tip',
        'hizmet_tipi',
        'aktif_mi',
        'yurt_ici_aktif',
        'yurt_disi_aktif',
        'para_birimi',
        'sabit_ucret',
        'ucretsiz_esik',
        'tahmini_teslim_gun',
        'sira',
        'entegrasyon_aktif',
        'entegrasyon',
        'entegrasyon_ayarlar',
        'kural',
        'bolge_kurali',
        'iade_kargo_aktif',
        'iade_kargo_ayarlar',
    ];

    protected $casts = [
        'aktif_mi' => 'bool',
        'yurt_ici_aktif' => 'bool',
        'yurt_disi_aktif' => 'bool',
        'sabit_ucret' => 'decimal:2',
        'ucretsiz_esik' => 'decimal:2',
        'tahmini_teslim_gun' => 'int',
        'sira' => 'int',
        'entegrasyon_aktif' => 'bool',
        'entegrasyon_ayarlar' => 'array',
        'kural' => 'array',
        'bolge_kurali' => 'array',
        'iade_kargo_aktif' => 'bool',
        'iade_kargo_ayarlar' => 'array',
    ];
}
