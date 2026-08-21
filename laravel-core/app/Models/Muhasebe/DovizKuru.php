<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasMuhasebeTanimKaydi;
use App\Models\Concerns\TanimGorunurFirmaIle;
use App\Models\Firma;
use App\Services\Front\FrontFiyatServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DovizKuru extends Model
{
    use HasMuhasebeTanimKaydi;
    use TanimGorunurFirmaIle;

    protected $table = 'muhasebe_doviz_kurlari';

    protected $fillable = [
        'firma_id',
        'kaynak_para_birimi',
        'hedef_para_birimi',
        'is_sabit',
        'tanim_firma_kapsami',
        'tarih',
        'kur',
        'saglayici',
        'manuel_mi',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'tarih' => 'date',
            'kur' => 'decimal:8',
            'is_sabit' => 'boolean',
            'manuel_mi' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            self::frontDovizCacheTemizle();
        });
        static::deleted(function (): void {
            self::frontDovizCacheTemizle();
        });
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    private static function frontDovizCacheTemizle(): void
    {
        FrontFiyatServisi::dovizKuruCacheTemizle();
    }
}
