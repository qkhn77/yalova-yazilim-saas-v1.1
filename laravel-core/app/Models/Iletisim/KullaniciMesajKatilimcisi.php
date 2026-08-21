<?php

namespace App\Models\Iletisim;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KullaniciMesajKatilimcisi extends Model
{
    protected $table = 'kullanici_mesaj_katilimcilari';

    protected $fillable = [
        'konu_id',
        'kullanici_id',
        'son_okuma_at',
        'favori_mi',
        'arsivlendi_mi',
        'sessize_alindi_mi',
    ];

    protected $casts = [
        'son_okuma_at' => 'datetime',
        'favori_mi' => 'boolean',
        'arsivlendi_mi' => 'boolean',
        'sessize_alindi_mi' => 'boolean',
    ];

    public function konu(): BelongsTo
    {
        return $this->belongsTo(KullaniciMesajKonusu::class, 'konu_id');
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }
}
