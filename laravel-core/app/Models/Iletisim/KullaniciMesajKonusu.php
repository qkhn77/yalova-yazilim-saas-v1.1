<?php

namespace App\Models\Iletisim;

use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KullaniciMesajKonusu extends Model
{
    use SoftDeletes;

    protected $table = 'kullanici_mesaj_konulari';

    protected $fillable = [
        'firma_id',
        'olusturan_id',
        'baslik',
        'oncelik',
        'durum',
        'son_mesaj_id',
        'son_mesaj_at',
    ];

    protected $casts = [
        'son_mesaj_at' => 'datetime',
    ];

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }

    public function sonMesaj(): BelongsTo
    {
        return $this->belongsTo(KullaniciMesaji::class, 'son_mesaj_id');
    }

    public function mesajlar(): HasMany
    {
        return $this->hasMany(KullaniciMesaji::class, 'konu_id');
    }

    public function katilimcilar(): HasMany
    {
        return $this->hasMany(KullaniciMesajKatilimcisi::class, 'konu_id');
    }

    public function kullanicilar(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kullanici_mesaj_katilimcilari', 'konu_id', 'kullanici_id')
            ->withPivot(['son_okuma_at', 'favori_mi', 'arsivlendi_mi', 'sessize_alindi_mi'])
            ->withTimestamps();
    }
}
