<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknikServisGorevAtamasi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'teknik_servis_gorev_atamalari';

    protected $fillable = [
        'firma_id',
        'teknik_servis_kaydi_id',
        'atanan_kullanici_id',
        'atayan_kullanici_id',
        'rol',
        'baslangic_tarihi',
        'bitis_tarihi',
        'durum',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'baslangic_tarihi' => 'datetime',
            'bitis_tarihi' => 'datetime',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function teknikServisKaydi(): BelongsTo
    {
        return $this->belongsTo(TeknikServisKaydi::class, 'teknik_servis_kaydi_id');
    }

    public function atanan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atanan_kullanici_id');
    }

    public function atayan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atayan_kullanici_id');
    }
}
