<?php

namespace App\Models\Ecommerce;

use Illuminate\Database\Eloquent\Model;

class EcommercePazaryeriEntegrasyon extends Model
{
    protected $table = 'ecommerce_pazaryeri_entegrasyonlari';

    protected $fillable = [
        'firma_id',
        'pazaryeri_kodu',
        'pazaryeri_adi',
        'aktif_mi',
        'senkron_yonu',
        'siparis_cekme_periyodu',
        'stok_senkron_aktif',
        'fiyat_senkron_aktif',
        'siparis_cekme_aktif',
        'hata_uyari_aktif',
        'max_deneme',
        'kimlik_bilgileri',
        'ayarlar',
        'son_senkron_at',
    ];

    protected $casts = [
        'aktif_mi' => 'bool',
        'stok_senkron_aktif' => 'bool',
        'fiyat_senkron_aktif' => 'bool',
        'siparis_cekme_aktif' => 'bool',
        'hata_uyari_aktif' => 'bool',
        'siparis_cekme_periyodu' => 'int',
        'max_deneme' => 'int',
        'kimlik_bilgileri' => 'array',
        'ayarlar' => 'array',
        'son_senkron_at' => 'datetime',
    ];
}