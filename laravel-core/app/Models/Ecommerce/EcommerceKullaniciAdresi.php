<?php

namespace App\Models\Ecommerce;

use Illuminate\Database\Eloquent\Model;

class EcommerceKullaniciAdresi extends Model
{
    public const TIP_TESLIMAT = 'teslimat';
    public const TIP_FATURA = 'fatura';

    protected $table = 'ecommerce_kullanici_adresleri';

    protected $fillable = [
        'firma_id',
        'kullanici_id',
        'adres_tipi',
        'baslik',
        'ad_soyad',
        'telefon',
        'vergi_dairesi',
        'vergi_no',
        'ulke_kodu',
        'sehir',
        'ilce',
        'mahalle',
        'posta_kodu',
        'acik_adres',
        'adres_notu',
        'varsayilan_teslimat_mi',
        'varsayilan_fatura_mi',
    ];

    protected $casts = [
        'varsayilan_teslimat_mi' => 'boolean',
        'varsayilan_fatura_mi' => 'boolean',
    ];
}
