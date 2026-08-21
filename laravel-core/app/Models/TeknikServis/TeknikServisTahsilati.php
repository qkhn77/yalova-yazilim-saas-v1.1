<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeknikServisTahsilati extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'teknik_servis_tahsilatlari';

    protected $fillable = [
        'firma_id',
        'teknik_servis_kaydi_id',
        'satis_faturasi_id',
        'finans_hareketi_id',
        'iptal_finans_hareketi_id',
        'kanal',
        'kasa_hesap_id',
        'banka_hesap_id',
        'pos_hesap_id',
        'kaynak_para_birimi',
        'hedef_para_birimi',
        'doviz_kuru_turu',
        'doviz_kuru',
        'tutar',
        'hedef_tutar',
        'tarih',
        'aciklama',
        'durum',
        'olusturan_id',
        'guncelleyen_id',
    ];

    protected function casts(): array
    {
        return [
            'doviz_kuru' => 'decimal:8',
            'tutar' => 'decimal:2',
            'hedef_tutar' => 'decimal:2',
            'tarih' => 'datetime',
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

    public function satisFaturasi(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'satis_faturasi_id');
    }

    public function finansHareketi(): BelongsTo
    {
        return $this->belongsTo(FinansHareketi::class, 'finans_hareketi_id');
    }

    public function iptalFinansHareketi(): BelongsTo
    {
        return $this->belongsTo(FinansHareketi::class, 'iptal_finans_hareketi_id');
    }

    public function kasaHesabi(): BelongsTo
    {
        return $this->belongsTo(KasaHesabi::class, 'kasa_hesap_id');
    }

    public function bankaHesabi(): BelongsTo
    {
        return $this->belongsTo(BankaHesabi::class, 'banka_hesap_id');
    }

    public function posHesabi(): BelongsTo
    {
        return $this->belongsTo(PosHesabi::class, 'pos_hesap_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }

    public function guncelleyen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guncelleyen_id');
    }
}
