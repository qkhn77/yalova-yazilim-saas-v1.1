<?php

namespace App\Models\Restoran;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestoranAdisyonTahsilati extends Model
{
    use HasFirmaTenantScope;

    public const DURUM_AKTIF = 'aktif';

    public const DURUM_IPTAL = 'iptal';

    protected $table = 'restoran_adisyon_tahsilatlari';

    protected $fillable = [
        'firma_id',
        'adisyon_id',
        'finans_hareketi_id',
        'iptal_finans_hareketi_id',
        'kasa_hesap_id',
        'banka_hesap_id',
        'pos_hesap_id',
        'odeme_kanali',
        'tutar',
        'para_birimi',
        'tahsilat_at',
        'iptal_at',
        'durum',
        'notlar',
        'iptal_notu',
    ];

    protected function casts(): array
    {
        return [
            'tutar' => 'decimal:2',
            'tahsilat_at' => 'datetime',
            'iptal_at' => 'datetime',
        ];
    }

    public function adisyon(): BelongsTo
    {
        return $this->belongsTo(RestoranAdisyonu::class, 'adisyon_id');
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
}
