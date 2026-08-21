<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Services\PersonelTakip\PersonelBelgesiKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonelBelgesi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'personel_belgeleri';

    protected $fillable = [
        'firma_id',
        'personel_id',
        'belge_turu',
        'ad',
        'dosya_yolu',
        'duzenleme_tarihi',
        'gecerlilik_tarihi',
        'uyari_tarihi',
        'durum',
        'aciklama',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $belge): void {
            app(PersonelBelgesiKuralServisi::class)->dogrula($belge);
        });
    }

    protected function casts(): array
    {
        return [
            'duzenleme_tarihi' => 'date',
            'gecerlilik_tarihi' => 'date',
            'uyari_tarihi' => 'date',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function personel(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'personel_id');
    }
}
