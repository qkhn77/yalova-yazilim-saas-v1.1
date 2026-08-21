<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlacakTakipNotu extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'muhasebe_alacak_takip_notlari';

    protected $fillable = [
        'firma_id',
        'cari_id',
        'alacak_plan_id',
        'alacak_plan_taksiti_id',
        'takip_tipi',
        'durum',
        'takip_tarihi',
        'sonraki_takip_tarihi',
        'odeme_sozu_tarihi',
        'odeme_sozu_tutari',
        'odeme_sozu_durumu',
        'kapanis_tarihi',
        'beklenen_tutar',
        'para_birimi',
        'not',
        'sonuc_notu',
        'olusturan_id',
    ];

    protected function casts(): array
    {
        return [
            'takip_tarihi' => 'datetime',
            'sonraki_takip_tarihi' => 'datetime',
            'odeme_sozu_tarihi' => 'datetime',
            'odeme_sozu_tutari' => 'decimal:2',
            'kapanis_tarihi' => 'datetime',
            'beklenen_tutar' => 'decimal:2',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AlacakPlani::class, 'alacak_plan_id');
    }

    public function taksit(): BelongsTo
    {
        return $this->belongsTo(AlacakPlanTaksiti::class, 'alacak_plan_taksiti_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }
}
