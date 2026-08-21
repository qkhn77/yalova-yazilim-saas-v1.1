<?php

namespace App\Models\Ecommerce;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiparisGecmisi extends Model
{
    public const OLAY_DURUM_DEGISTI = 'durum_degisti';

    public const OLAY_IPTAL = 'siparis_iptal';

    public const OLAY_KARGO_GUNCELLENDI = 'kargo_guncellendi';

    public const OLAY_NOT_GUNCELLENDI = 'not_guncellendi';

    public const OLAY_MANUEL_ODEME = 'manuel_odeme_onayi';

    public const OLAY_ODEME_BASARILI = 'odeme_basarili';

    public const OLAY_ODEME_BASARISIZ = 'odeme_basarisiz';

    public const OLAY_ODEME_BASLATILDI = 'odeme_baslatildi';

    public const OLAY_ODEME_TEKRAR_DENENDI = 'odeme_tekrar_denendi';

    public const OLAY_FINANS_IADE_OLUSTURULDU = 'finans_iade_olusturuldu';

    public const OLAY_FINANS_IADE_BASARISIZ = 'finans_iade_basarisiz';

    public $timestamps = false;

    protected $table = 'siparis_gecmisleri';

    protected $fillable = [
        'siparis_id',
        'kullanici_id',
        'olay',
        'aciklama',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $kayit): void {
            if ($kayit->created_at === null) {
                $kayit->created_at = now();
            }
        });
    }

    public function siparis(): BelongsTo
    {
        return $this->belongsTo(Siparis::class, 'siparis_id');
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }
}
