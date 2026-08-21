<?php

namespace App\Models\TeknikServis;

use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknikServisKayitliCihazDegisikligi extends Model
{
    protected $table = 'teknik_servis_kayitli_cihaz_degisiklikleri';

    protected $fillable = [
        'firma_id', 'kayitli_cihaz_id', 'kullanici_id', 'olay',
        'eski_degerler', 'yeni_degerler', 'ip_adresi',
    ];

    protected $casts = [
        'eski_degerler' => 'array',
        'yeni_degerler' => 'array',
    ];

    public function firma(): BelongsTo { return $this->belongsTo(Firma::class); }
    public function cihaz(): BelongsTo { return $this->belongsTo(TeknikServisKayitliCihazi::class, 'kayitli_cihaz_id'); }
    public function kullanici(): BelongsTo { return $this->belongsTo(User::class, 'kullanici_id'); }
}
