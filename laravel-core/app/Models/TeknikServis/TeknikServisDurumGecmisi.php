<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknikServisDurumGecmisi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'teknik_servis_durum_gecmisleri';

    protected $fillable = [
        'firma_id',
        'teknik_servis_kaydi_id',
        'onceki_servis_durumu_id',
        'yeni_servis_durumu_id',
        'degisim_notu',
        'degistiren_id',
        'degisim_tarihi',
    ];

    protected function casts(): array
    {
        return [
            'degisim_tarihi' => 'datetime',
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

    public function oncekiDurum(): BelongsTo
    {
        return $this->belongsTo(TeknikServisDurumTanimi::class, 'onceki_servis_durumu_id');
    }

    public function yeniDurum(): BelongsTo
    {
        return $this->belongsTo(TeknikServisDurumTanimi::class, 'yeni_servis_durumu_id');
    }

    public function degistiren(): BelongsTo
    {
        return $this->belongsTo(User::class, 'degistiren_id');
    }
}
