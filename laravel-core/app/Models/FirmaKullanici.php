<?php

namespace App\Models;

use App\Traits\HasFirma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FirmaKullanici extends Model
{
    use HasFirma;
    use SoftDeletes;

    protected $table = 'firma_kullanicilari';

    protected $fillable = [
        'firma_id',
        'kullanici_id',
        'rol_id',
        'durum',
        'onay_durumu',
        'varsayilan_firma_mi',
    ];

    protected $casts = [
        'varsayilan_firma_mi' => 'boolean',
    ];

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    /**
     * Aynı firma + kullanıcı için özel yetki satırları.
     */
    public function ozelYetkiler(): HasMany
    {
        return $this->hasMany(KullaniciYetki::class, 'kullanici_id', 'kullanici_id')
            ->where('firma_id', $this->firma_id);
    }
}

