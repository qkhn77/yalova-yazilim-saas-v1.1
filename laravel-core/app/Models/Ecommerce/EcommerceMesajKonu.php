<?php

namespace App\Models\Ecommerce;

use App\Models\Ecommerce\Siparis;
use App\Models\Muhasebe\StokKarti;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EcommerceMesajKonu extends Model
{
    protected $table = 'ecommerce_mesaj_konulari';

    protected $fillable = [
        'firma_id',
        'konu_tipi',
        'kullanici_id',
        'stok_karti_id',
        'siparis_id',
        'visible_on_product',
        'baslik',
        'durum',
        'okunmamis_mi',
        'okunmamis_mesaj_sayisi',
        'musteri_ad_soyad',
        'musteri_email',
        'musteri_telefon',
        'son_musteri_mesaji_at',
        'son_admin_mesaji_at',
        'ilk_yanit_at',
        'sla_son_tarih_at',
        'sla_ihlal_mi',
        'tamamlandi_at',
    ];

    protected $casts = [
        'okunmamis_mi' => 'bool',
        'visible_on_product' => 'bool',
        'okunmamis_mesaj_sayisi' => 'int',
        'son_musteri_mesaji_at' => 'datetime',
        'son_admin_mesaji_at' => 'datetime',
        'ilk_yanit_at' => 'datetime',
        'sla_son_tarih_at' => 'datetime',
        'sla_ihlal_mi' => 'bool',
        'tamamlandi_at' => 'datetime',
    ];

    public function mesajlar(): HasMany
    {
        return $this->hasMany(EcommerceMesaj::class, 'konu_id')->orderBy('created_at');
    }

    public function sonMesaj(): HasOne
    {
        return $this->hasOne(EcommerceMesaj::class, 'konu_id')->latestOfMany();
    }

    public function stokKarti(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_karti_id');
    }

    public function siparis(): BelongsTo
    {
        return $this->belongsTo(Siparis::class, 'siparis_id');
    }
}
