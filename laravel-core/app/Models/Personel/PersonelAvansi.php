<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\User;
use App\Services\PersonelTakip\PersonelAvansKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonelAvansi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'personel_avanslari';

    protected $fillable = [
        'firma_id',
        'personel_id',
        'tarih',
        'tutar',
        'para_birimi',
        'odeme_kanali',
        'odeme_kaynagi',
        'kasa_hesap_id',
        'kasa_hesabi_id',
        'banka_hesap_id',
        'banka_hesabi_id',
        'pos_hesap_id',
        'durum',
        'mahsup_durumu',
        'onay_durumu',
        'onaylayan_id',
        'onaylayan_kullanici_id',
        'onay_tarihi',
        'finans_hareketi_id',
        'aciklama',
        'maastan_dusuldu_mu',
        'kalan_tutar',
    ];

    protected function casts(): array
    {
        return [
            'tarih' => 'date',
            'tutar' => 'decimal:2',
            'kalan_tutar' => 'decimal:2',
            'maastan_dusuldu_mu' => 'boolean',
            'onay_tarihi' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $avans): void {
            app(PersonelAvansKuralServisi::class)->hazirlaVeDogrula($avans);
        });
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function personel(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'personel_id');
    }

    public function onaylayan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'onaylayan_id');
    }

    public function finansHareketi(): BelongsTo
    {
        return $this->belongsTo(FinansHareketi::class, 'finans_hareketi_id');
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
