<?php

namespace App\Models\Personel;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Sube;
use App\Models\User;
use App\Services\PersonelTakip\PersonelKayitKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Personel extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    public const DURUM_AKTIF = 'aktif';

    public const DURUM_PASIF = 'pasif';

    public const DURUM_ISTEN_AYRILDI = 'isten_ayrildi';

    public const DURUM_ASKIDA = 'askida';

    protected $table = 'personeller';

    protected $fillable = [
        'firma_id',
        'sube_id',
        'kullanici_id',
        'gorev_id',
        'departman_id',
        'personel_no',
        'ad_soyad',
        'telefon',
        'email',
        'tc_kimlik_no',
        'adres',
        'acil_durum_kisi',
        'acil_durum_telefon',
        'calisma_tipi',
        'maas_tipi',
        'maas_tutari',
        'saatlik_ucret',
        'gunluk_ucret',
        'ise_giris_tarihi',
        'isten_cikis_tarihi',
        'pin_kodu',
        'pin_kodu_hash',
        'durum',
        'notlar',
    ];

    protected $hidden = [
        'pin_kodu',
        'pin_kodu_hash',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $personel): void {
            app(PersonelKayitKuralServisi::class)->dogrula($personel);
        });
    }

    protected function casts(): array
    {
        return [
            'maas_tutari' => 'decimal:2',
            'saatlik_ucret' => 'decimal:2',
            'gunluk_ucret' => 'decimal:2',
            'ise_giris_tarihi' => 'date',
            'isten_cikis_tarihi' => 'date',
        ];
    }

    /** @return array<string, string> */
    public static function durumSecenekleri(): array
    {
        return [
            self::DURUM_AKTIF => 'Aktif',
            self::DURUM_PASIF => 'Pasif',
            self::DURUM_ISTEN_AYRILDI => 'İşten Ayrıldı',
            self::DURUM_ASKIDA => 'Askıda',
        ];
    }

    /** @return array<string, string> */
    public static function calismaTipiSecenekleri(): array
    {
        return [
            'tam_zamanli' => 'Tam Zamanlı',
            'part_time' => 'Part-time',
            'gunluk' => 'Günlük',
            'saatlik' => 'Saatlik',
            'stajyer' => 'Stajyer',
            'sozlesmeli' => 'Sözleşmeli',
        ];
    }

    /** @return array<string, string> */
    public static function maasTipiSecenekleri(): array
    {
        return [
            'aylik' => 'Aylık',
            'gunluk' => 'Günlük',
            'saatlik' => 'Saatlik',
            'primli' => 'Primli',
            'karma' => 'Karma',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function sube(): BelongsTo
    {
        return $this->belongsTo(Sube::class, 'sube_id');
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }

    public function gorev(): BelongsTo
    {
        return $this->belongsTo(PersonelGorevi::class, 'gorev_id');
    }

    public function departman(): BelongsTo
    {
        return $this->belongsTo(PersonelDepartmani::class, 'departman_id');
    }

    public function vardiyalar(): HasMany
    {
        return $this->hasMany(PersonelVardiyasi::class, 'personel_id');
    }

    public function girisCikislari(): HasMany
    {
        return $this->hasMany(PersonelGirisCikisi::class, 'personel_id');
    }

    public function izinler(): HasMany
    {
        return $this->hasMany(PersonelIzni::class, 'personel_id');
    }

    public function avanslar(): HasMany
    {
        return $this->hasMany(PersonelAvansi::class, 'personel_id');
    }

    public function belgeler(): HasMany
    {
        return $this->hasMany(PersonelBelgesi::class, 'personel_id');
    }
}
