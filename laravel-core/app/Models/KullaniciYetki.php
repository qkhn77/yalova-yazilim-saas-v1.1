<?php

namespace App\Models;

use App\Traits\HasFirma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KullaniciYetki extends Model
{
    use HasFirma;

    protected $table = 'kullanici_yetkileri';

    protected $fillable = [
        'firma_id',
        'kullanici_id',
        'yetki_id',
        'izin_tipi',
    ];

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }

    public function yetki(): BelongsTo
    {
        return $this->belongsTo(Yetki::class, 'yetki_id');
    }
}

