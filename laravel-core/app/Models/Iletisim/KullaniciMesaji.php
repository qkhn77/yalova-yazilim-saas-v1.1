<?php

namespace App\Models\Iletisim;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KullaniciMesaji extends Model
{
    use SoftDeletes;

    protected $table = 'kullanici_mesajlari';

    protected $fillable = [
        'konu_id',
        'gonderen_id',
        'mesaj',
        'ekler',
        'sistem_mesaji_mi',
    ];

    protected $casts = [
        'ekler' => 'array',
        'sistem_mesaji_mi' => 'boolean',
    ];

    public function konu(): BelongsTo
    {
        return $this->belongsTo(KullaniciMesajKonusu::class, 'konu_id');
    }

    public function gonderen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gonderen_id');
    }
}
