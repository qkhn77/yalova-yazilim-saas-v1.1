<?php

namespace App\Models\Restoran;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Sube;
use App\Services\Restoran\RestoranMasaKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RestoranMasasi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    public const DURUM_BOS = 'bos';

    public const DURUM_DOLU = 'dolu';

    public const DURUM_REZERVE = 'rezerve';

    public const DURUM_KAPALI = 'kapali';

    protected $table = 'restoran_masalari';

    protected $fillable = [
        'firma_id',
        'sube_id',
        'salon_id',
        'ad',
        'kod',
        'qr_siparis_kodu',
        'kapasite',
        'durum',
        'aktif_mi',
        'siralama',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $masa): void {
            if (! $masa->qr_siparis_kodu) {
                $masa->qr_siparis_kodu = self::benzersizQrSiparisKodu((int) $masa->firma_id);
            }

            app(RestoranMasaKuralServisi::class)->dogrula($masa);
        });
    }

    protected function casts(): array
    {
        return [
            'kapasite' => 'integer',
            'aktif_mi' => 'boolean',
            'siralama' => 'integer',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function sube(): BelongsTo
    {
        return $this->belongsTo(Sube::class, 'sube_id');
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(RestoranSalonu::class, 'salon_id');
    }

    public function adisyonlar(): HasMany
    {
        return $this->hasMany(RestoranAdisyonu::class, 'masa_id');
    }

    public function qrSiparisKodunuYenile(): void
    {
        $this->forceFill([
            'qr_siparis_kodu' => self::benzersizQrSiparisKodu((int) $this->firma_id),
        ])->save();
    }

    private static function benzersizQrSiparisKodu(int $firmaId): string
    {
        do {
            $kod = Str::random(40);
        } while (self::withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('qr_siparis_kodu', $kod)
            ->exists());

        return $kod;
    }
}
