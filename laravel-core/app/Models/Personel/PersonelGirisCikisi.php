<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Sube;
use App\Models\User;
use App\Services\PersonelTakip\PersonelGirisCikisKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonelGirisCikisi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'personel_giris_cikislari';

    protected $fillable = [
        'firma_id',
        'sube_id',
        'personel_id',
        'vardiya_id',
        'tarih',
        'giris_at',
        'cikis_at',
        'giris_tipi',
        'cikis_tipi',
        'kaynak',
        'giris_ip',
        'cikis_ip',
        'cihaz_bilgisi',
        'konum_lat',
        'konum_lng',
        'gec_kalma_dakika',
        'erken_cikis_dakika',
        'fazla_mesai_dakika',
        'eksik_calisma_dakika',
        'onay_durumu',
        'onaylayan_id',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'tarih' => 'date',
            'giris_at' => 'datetime',
            'cikis_at' => 'datetime',
            'konum_lat' => 'decimal:7',
            'konum_lng' => 'decimal:7',
            'gec_kalma_dakika' => 'integer',
            'erken_cikis_dakika' => 'integer',
            'fazla_mesai_dakika' => 'integer',
            'eksik_calisma_dakika' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $kayit): void {
            app(PersonelGirisCikisKuralServisi::class)->hazirlaVeDogrula($kayit);
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

    public function personel(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'personel_id');
    }

    public function vardiya(): BelongsTo
    {
        return $this->belongsTo(PersonelVardiyasi::class, 'vardiya_id');
    }

    public function onaylayan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'onaylayan_id');
    }
}
