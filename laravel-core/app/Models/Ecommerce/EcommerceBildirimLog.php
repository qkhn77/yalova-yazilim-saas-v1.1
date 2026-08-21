<?php

namespace App\Models\Ecommerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceBildirimLog extends Model
{
    public const DURUM_KUYRUKTA = 'kuyrukta';
    public const DURUM_GONDERILDI = 'gonderildi';
    public const DURUM_BASARISIZ = 'basarisiz';

    protected $table = 'ecommerce_bildirim_loglari';

    protected $fillable = [
        'firma_id',
        'siparis_id',
        'olay',
        'kanal',
        'locale',
        'hedef',
        'baslik',
        'icerik',
        'durum',
        'deneme_sayisi',
        'hata',
        'gonderildi_at',
    ];

    protected function casts(): array
    {
        return [
            'deneme_sayisi' => 'integer',
            'gonderildi_at' => 'datetime',
        ];
    }

    public function siparis(): BelongsTo
    {
        return $this->belongsTo(Siparis::class, 'siparis_id');
    }
}

