<?php

namespace App\Models\Iletisim;

use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KullaniciBildirimi extends Model
{
    protected $table = 'kullanici_bildirimleri';

    protected $fillable = [
        'firma_id',
        'kullanici_id',
        'kaynak_turu',
        'kaynak_id',
        'baslik',
        'mesaj',
        'seviye',
        'okundu_at',
        'aksiyon_url',
        'data',
    ];

    protected $casts = [
        'okundu_at' => 'datetime',
        'data' => 'array',
    ];

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }
}
