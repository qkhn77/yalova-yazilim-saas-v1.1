<?php

namespace App\Models\Restoran;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\StokKarti;
use App\Services\Restoran\RestoranMenuUrunKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestoranMenuUrunu extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'restoran_menu_urunleri';

    protected $fillable = [
        'firma_id',
        'kategori_id',
        'stok_karti_id',
        'ad',
        'aciklama',
        'fiyat',
        'kdv_orani',
        'gorsel_yolu',
        'aktif_mi',
        'qr_menu_gorunur_mu',
        'stokta_var_mi',
        'siralama',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $urun): void {
            app(RestoranMenuUrunKuralServisi::class)->dogrula($urun);
        });
    }

    protected function casts(): array
    {
        return [
            'fiyat' => 'decimal:2',
            'kdv_orani' => 'decimal:2',
            'aktif_mi' => 'boolean',
            'qr_menu_gorunur_mu' => 'boolean',
            'stokta_var_mi' => 'boolean',
            'siralama' => 'integer',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(RestoranMenuKategorisi::class, 'kategori_id');
    }

    public function stokKarti(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_karti_id');
    }

    public function recete(): HasOne
    {
        return $this->hasOne(RestoranRecetesi::class, 'menu_urunu_id');
    }
}
