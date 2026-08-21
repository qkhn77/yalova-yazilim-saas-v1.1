<?php

namespace App\Models;

use App\Traits\HasFirma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FirmaModulu extends Model
{
    use HasFirma;

    protected $table = 'firma_modulleri';

    protected $fillable = [
        'firma_id',
        'modul_id',
        'durum',
        'baslangic_tarihi',
        'bitis_tarihi',
    ];

    protected $casts = [
        'baslangic_tarihi' => 'date',
        'bitis_tarihi' => 'date',
    ];

protected static function booted(): void
{
    static::saved(function (self $firmaModulu): void {
        app(\App\Services\ModulErisimService::class)
            ->firmaModuluCacheTemizle((int) $firmaModulu->firma_id);
    });

    static::deleted(function (self $firmaModulu): void {
        app(\App\Services\ModulErisimService::class)
            ->firmaModuluCacheTemizle((int) $firmaModulu->firma_id);
    });
}


    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function modul(): BelongsTo
    {
        return $this->belongsTo(Modul::class, 'modul_id');
    }
}

