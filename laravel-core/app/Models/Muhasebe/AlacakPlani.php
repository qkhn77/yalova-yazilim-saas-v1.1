<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlacakPlani extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'muhasebe_alacak_planlari';

    protected $fillable = [
        'firma_id',
        'islem_no',
        'cari_id',
        'kaynak_turu',
        'kaynak_id',
        'plan_turu',
        'toplam_tutar',
        'pesinat_tutari',
        'vade_farki_tipi',
        'vade_farki_orani',
        'vade_farki_tutari',
        'faiz_orani',
        'faiz_tutari',
        'planlanan_tutar',
        'odenen_tutar',
        'kalan_tutar',
        'para_birimi',
        'baslangic_tarihi',
        'son_vade_tarihi',
        'durum',
        'aciklama',
        'olusturan_id',
    ];

    protected function casts(): array
    {
        return [
            'toplam_tutar' => 'decimal:2',
            'pesinat_tutari' => 'decimal:2',
            'vade_farki_orani' => 'decimal:4',
            'vade_farki_tutari' => 'decimal:2',
            'faiz_orani' => 'decimal:4',
            'faiz_tutari' => 'decimal:2',
            'planlanan_tutar' => 'decimal:2',
            'odenen_tutar' => 'decimal:2',
            'kalan_tutar' => 'decimal:2',
            'baslangic_tarihi' => 'date',
            'son_vade_tarihi' => 'date',
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

    public function taksitler(): HasMany
    {
        return $this->hasMany(AlacakPlanTaksiti::class, 'alacak_plan_id');
    }

    public function tahsilatEslesmeleri(): HasMany
    {
        return $this->hasMany(AlacakTahsilatEslesmesi::class, 'alacak_plan_id');
    }

    public function takipNotlari(): HasMany
    {
        return $this->hasMany(AlacakTakipNotu::class, 'alacak_plan_id');
    }

    public function revizyonlar(): HasMany
    {
        return $this->hasMany(AlacakPlanRevizyonu::class, 'alacak_plan_id');
    }

    public function onayTalepleri(): HasMany
    {
        return $this->hasMany(AlacakPlanOnayTalebi::class, 'alacak_plan_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }
}
