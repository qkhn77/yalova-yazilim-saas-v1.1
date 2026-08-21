<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Services\PersonelTakip\PersonelMaasOdemeKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonelMaasOdemeKaydi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'personel_maas_odeme_kayitlari';

    protected $fillable = [
        'firma_id',
        'maas_hareketi_id',
        'tarih',
        'tutar',
        'para_birimi',
        'odeme_kanali',
        'kasa_hesap_id',
        'banka_hesap_id',
        'finans_hareketi_id',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'tarih' => 'date',
            'tutar' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $odeme): void {
            app(PersonelMaasOdemeKuralServisi::class)->hazirlaVeDogrula($odeme);
        });

        static::saved(static function (self $odeme): void {
            app(PersonelMaasOdemeKuralServisi::class)->hareketOdemesiniGuncelle($odeme);
        });

        static::deleted(static function (self $odeme): void {
            app(PersonelMaasOdemeKuralServisi::class)->hareketOdemesiniGuncelle($odeme);
        });
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function maasHareketi(): BelongsTo
    {
        return $this->belongsTo(PersonelMaasHareketi::class, 'maas_hareketi_id');
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
}
