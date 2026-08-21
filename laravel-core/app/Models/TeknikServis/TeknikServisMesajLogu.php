<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknikServisMesajLogu extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'teknik_servis_mesaj_loglari';

    protected $fillable = [
        'firma_id',
        'teknik_servis_kaydi_id',
        'kanal',
        'yon',
        'alici',
        'konu',
        'icerik_ozeti',
        'dis_id',
        'durum',
        'hata_mesaji',
        'gonderen_kullanici_id',
        'olay_tarihi',
    ];

    protected function casts(): array
    {
        return [
            'olay_tarihi' => 'datetime',
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

    public function gonderen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gonderen_kullanici_id');
    }
}
