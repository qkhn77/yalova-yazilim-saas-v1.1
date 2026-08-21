<?php

namespace App\Models\Ecommerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Odeme extends Model
{
    public const DURUM_BEKLEMEDE = 'beklemede';

    public const DURUM_BASARILI = 'basarili';

    public const DURUM_BASARISIZ = 'basarisiz';

    /** Süperseed edilen veya iptal edilen bekleyen deneme */
    public const DURUM_IPTAL = 'iptal';

    public const PROVIDER_MOCK = 'mock';

    public const PROVIDER_PAYTR = 'paytr';

    public const PROVIDER_IYZICO = 'iyzico';

    public const PROVIDER_HAVALE_EFT = 'havale_eft';

    /**
     * @return array<string, string>
     */
    public static function durumEtiketleri(): array
    {
        return [
            self::DURUM_BEKLEMEDE => 'Beklemede',
            self::DURUM_BASARILI => 'Başarılı',
            self::DURUM_BASARISIZ => 'Başarısız',
            self::DURUM_IPTAL => 'İptal (ödeme)',
        ];
    }

    protected $table = 'odemeler';

    protected $fillable = [
        'siparis_id',
        'odeme_no',
        'tutar',
        'para_birimi',
        'durum',
        'provider',
        'provider_ref',
    ];

    protected function casts(): array
    {
        return [
            'tutar' => 'decimal:2',
        ];
    }

    public function siparis(): BelongsTo
    {
        return $this->belongsTo(Siparis::class, 'siparis_id');
    }
}
