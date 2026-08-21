<?php

namespace App\Models\Restoran;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Muhasebe\StokKarti;
use App\Models\Personel\Personel;
use App\Services\Restoran\RestoranAdisyonKalemKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestoranAdisyonKalemi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    public const DURUM_YENI = 'yeni';

    public const DURUM_HAZIRLANIYOR = 'hazirlaniyor';

    public const DURUM_HAZIR = 'hazir';

    public const DURUM_SERVIS_EDILDI = 'servis_edildi';

    public const DURUM_IPTAL = 'iptal';

    protected $table = 'restoran_adisyon_kalemleri';

    protected $fillable = [
        'firma_id',
        'adisyon_id',
        'menu_urunu_id',
        'stok_karti_id',
        'hazirlayan_personel_id',
        'urun_adi',
        'miktar',
        'birim_fiyat',
        'kdv_orani',
        'iskonto_tutari',
        'ikram_mi',
        'ikram_tutari',
        'ara_tutar',
        'kdv_tutari',
        'toplam_tutar',
        'durum',
        'mutfak_notu',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $kalem): void {
            app(RestoranAdisyonKalemKuralServisi::class)->hazirlaVeDogrula($kalem);
        });

        static::saved(static function (self $kalem): void {
            app(RestoranAdisyonKalemKuralServisi::class)->adisyonToplamlariniGuncelle($kalem);
        });

        static::deleted(static function (self $kalem): void {
            app(RestoranAdisyonKalemKuralServisi::class)->adisyonToplamlariniGuncelle($kalem);
        });
    }

    protected function casts(): array
    {
        return [
            'miktar' => 'decimal:8',
            'birim_fiyat' => 'decimal:2',
            'kdv_orani' => 'decimal:2',
            'iskonto_tutari' => 'decimal:2',
            'ikram_mi' => 'boolean',
            'ikram_tutari' => 'decimal:2',
            'ara_tutar' => 'decimal:2',
            'kdv_tutari' => 'decimal:2',
            'toplam_tutar' => 'decimal:2',
        ];
    }

    public function adisyon(): BelongsTo
    {
        return $this->belongsTo(RestoranAdisyonu::class, 'adisyon_id');
    }

    public function stokKarti(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_karti_id');
    }

    public function menuUrunu(): BelongsTo
    {
        return $this->belongsTo(RestoranMenuUrunu::class, 'menu_urunu_id');
    }

    public function hazirlayan(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'hazirlayan_personel_id');
    }
}
