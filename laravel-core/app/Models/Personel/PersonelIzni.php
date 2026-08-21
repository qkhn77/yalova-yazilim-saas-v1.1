<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use App\Services\PersonelTakip\PersonelIzinKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonelIzni extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'personel_izinleri';

    protected $fillable = [
        'firma_id',
        'personel_id',
        'izin_turu',
        'baslangic_tarihi',
        'bitis_tarihi',
        'baslangic_at',
        'bitis_at',
        'gun_sayisi',
        'saat_sayisi',
        'durum',
        'onay_durumu',
        'onaylayan_id',
        'onay_at',
        'aciklama',
        'belge_path',
        'belge_yolu',
    ];

    protected function casts(): array
    {
        return [
            'baslangic_tarihi' => 'date',
            'bitis_tarihi' => 'date',
            'baslangic_at' => 'datetime',
            'bitis_at' => 'datetime',
            'gun_sayisi' => 'decimal:2',
            'saat_sayisi' => 'decimal:2',
            'onay_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $izin): void {
            app(PersonelIzinKuralServisi::class)->hazirlaVeDogrula($izin);
        });
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function personel(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'personel_id');
    }

    public function onaylayan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'onaylayan_id');
    }
}
