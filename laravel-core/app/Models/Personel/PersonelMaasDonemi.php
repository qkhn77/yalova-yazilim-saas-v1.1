<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Sube;
use App\Models\User;
use App\Services\PersonelTakip\PersonelMaasDonemiKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonelMaasDonemi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'personel_maas_donemleri';

    protected $fillable = [
        'firma_id',
        'sube_id',
        'ad',
        'donem_yil',
        'donem_ay',
        'baslangic_tarihi',
        'bitis_tarihi',
        'durum',
        'toplam_brut',
        'toplam_kesinti',
        'toplam_net',
        'para_birimi',
        'aciklama',
        'olusturan_id',
        'onaylayan_id',
        'onay_at',
    ];

    protected function casts(): array
    {
        return [
            'donem_yil' => 'integer',
            'donem_ay' => 'integer',
            'baslangic_tarihi' => 'date',
            'bitis_tarihi' => 'date',
            'toplam_brut' => 'decimal:2',
            'toplam_kesinti' => 'decimal:2',
            'toplam_net' => 'decimal:2',
            'onay_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $donem): void {
            app(PersonelMaasDonemiKuralServisi::class)->hazirlaVeDogrula($donem);
        });
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function sube(): BelongsTo
    {
        return $this->belongsTo(Sube::class, 'sube_id');
    }

    public function hareketler(): HasMany
    {
        return $this->hasMany(PersonelMaasHareketi::class, 'maas_donemi_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }

    public function onaylayan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'onaylayan_id');
    }
}
