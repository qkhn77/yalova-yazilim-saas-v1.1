<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Sube;
use App\Models\User;
use App\Services\PersonelTakip\PersonelVardiyaKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonelVardiyasi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'personel_vardiyalari';

    protected $fillable = [
        'firma_id',
        'sube_id',
        'personel_id',
        'vardiya_sablonu_id',
        'tarih',
        'baslangic_at',
        'bitis_at',
        'baslangic_saati',
        'bitis_saati',
        'mola_dakika',
        'durum',
        'notlar',
        'olusturan_id',
        'onaylayan_id',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $vardiya): void {
            app(PersonelVardiyaKuralServisi::class)->dogrula($vardiya);
        });
    }

    protected function casts(): array
    {
        return [
            'tarih' => 'date',
            'baslangic_at' => 'datetime',
            'bitis_at' => 'datetime',
            'mola_dakika' => 'integer',
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

    public function personel(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'personel_id');
    }

    public function sablon(): BelongsTo
    {
        return $this->belongsTo(PersonelVardiyaSablonu::class, 'vardiya_sablonu_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }

    public function onaylayan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'onaylayan_id');
    }

    public function girisCikislari(): HasMany
    {
        return $this->hasMany(PersonelGirisCikisi::class, 'vardiya_id');
    }
}
