<?php

namespace App\Models;

use App\Models\Muhasebe\ParaBirimi;
use App\Models\Muhasebe\Depo;
use App\Support\SaaSemaYardimcisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Firma extends Model
{
    use SoftDeletes;

    public const DURUM_BEKLEMEDE = 'beklemede';

    public const DURUM_AKTIF = 'aktif';

    public const DURUM_ASKIDA = 'askida';

    public const DURUM_SURESI_DOLDU = 'suresi_doldu';

    public const DURUM_IPTAL_EDILDI = 'iptal_edildi';

    /** @return array<string, string> */
    public static function durumSecenekleri(): array
    {
        return [
            self::DURUM_BEKLEMEDE => 'Beklemede',
            self::DURUM_AKTIF => 'Aktif',
            self::DURUM_ASKIDA => 'Askıda',
            self::DURUM_SURESI_DOLDU => 'Süresi doldu',
            self::DURUM_IPTAL_EDILDI => 'İptal edildi',
        ];
    }

    /**
     * Tam silme yerine arşiv / pasifleştirme için önerilen durum kodu (STEP 11+ iş akışları).
     */
    public static function onerilenPasifDurumKodu(): string
    {
        return self::DURUM_ASKIDA;
    }

    protected $table = 'firmalar';

    protected $fillable = [
        'ad',
        'kisa_ad',
        'firma_kodu',
        'vergi_no',
        'telefon',
        'eposta',
        'adres',
        'durum',
        'onaylandi_mi',
        'onaylayan_kullanici_id',
        'onay_tarihi',
    ];

    protected $casts = [
        'onaylandi_mi' => 'boolean',
        'onay_tarihi' => 'datetime',
    ];

    public function onaylayanKullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'onaylayan_kullanici_id');
    }

    public function firmaKullanicilari(): HasMany
    {
        return $this->hasMany(FirmaKullanici::class, 'firma_id');
    }

    public function firmaModulleri(): HasMany
    {
        return $this->hasMany(FirmaModulu::class, 'firma_id');
    }

    public function firmaAbonelikleri(): HasMany
    {
        return $this->hasMany(FirmaAboneligi::class, 'firma_id');
    }

    public function denetimKayitlari(): HasMany
    {
        return $this->hasMany(DenetimKayidi::class, 'firma_id');
    }

    public function firmaAyarlari(): HasMany
    {
        return $this->hasMany(FirmaAyari::class, 'firma_id');
    }

    /**
     * Firma ilişkilerini geri dönüşsüz temizler (hard delete).
     *
     * @param  array<string, int>  $sayaclar
     */
    public function tumIliskileriSil(array &$sayaclar = [], ?int $islemYapanKullaniciId = null): void
    {
        $firmaId = (int) $this->getKey();
        $sayaclar = array_merge([
            'firma_kullanicilari' => 0,
            'firma_modulleri' => 0,
            'firma_abonelikleri' => 0,
            'kullanici_yetkileri' => 0,
            'rol_yetkileri' => 0,
            'denetim_kayitlari' => 0,
            'firma_ayarlari' => 0,
            'silinen_kullanicilar' => 0,
        ], $sayaclar);

        $bagliKullaniciIds = [];
        if (SaaSemaYardimcisi::firmaKullanicilariTablosuVarMi()) {
            $bagliKullaniciIds = DB::table('firma_kullanicilari')
                ->where('firma_id', $firmaId)
                ->pluck('kullanici_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        if (SaaSemaYardimcisi::kullaniciYetkileriTablosuVarMi()) {
            $sayaclar['kullanici_yetkileri'] += DB::table('kullanici_yetkileri')->where('firma_id', $firmaId)->delete();
        }
        if (SaaSemaYardimcisi::rolYetkileriTablosuVarMi() && Schema::hasColumn('rol_yetkileri', 'firma_id')) {
            $sayaclar['rol_yetkileri'] += DB::table('rol_yetkileri')->where('firma_id', $firmaId)->delete();
        }
        if (SaaSemaYardimcisi::firmaModulleriTablosuVarMi()) {
            $sayaclar['firma_modulleri'] += DB::table('firma_modulleri')->where('firma_id', $firmaId)->delete();
        }
        if (SaaSemaYardimcisi::firmaAbonelikleriTablosuVarMi()) {
            $sayaclar['firma_abonelikleri'] += DB::table('firma_abonelikleri')->where('firma_id', $firmaId)->delete();
        }
        if (SaaSemaYardimcisi::tabloVarMi('firma_ayarlari')) {
            $sayaclar['firma_ayarlari'] += DB::table('firma_ayarlari')->where('firma_id', $firmaId)->delete();
        }
        if (SaaSemaYardimcisi::tabloVarMi('denetim_kayitlari')) {
            $sayaclar['denetim_kayitlari'] += DB::table('denetim_kayitlari')->where('firma_id', $firmaId)->delete();
        }
        if (SaaSemaYardimcisi::firmaKullanicilariTablosuVarMi()) {
            $sayaclar['firma_kullanicilari'] += DB::table('firma_kullanicilari')->where('firma_id', $firmaId)->delete();
        }

        if (! SaaSemaYardimcisi::tabloVarMi('users')) {
            return;
        }

        $firmaKullanicilariDeletedAtVar = SaaSemaYardimcisi::kolonVarMi('firma_kullanicilari', 'deleted_at');
        foreach ($bagliKullaniciIds as $kullaniciId) {
            if ($islemYapanKullaniciId !== null && (int) $kullaniciId === (int) $islemYapanKullaniciId) {
                continue;
            }

            $digerFirmaSorgu = DB::table('firma_kullanicilari')
                ->where('kullanici_id', (int) $kullaniciId)
                ->where('firma_id', '!=', $firmaId);
            if ($firmaKullanicilariDeletedAtVar) {
                $digerFirmaSorgu->whereNull('deleted_at');
            }

            if ($digerFirmaSorgu->exists()) {
                continue;
            }

            $sayaclar['silinen_kullanicilar'] += DB::table('users')->where('id', (int) $kullaniciId)->delete();
        }
    }

    protected static function booted(): void
    {
        static::created(function (Firma $firma): void {
            if (! Schema::hasTable('muhasebe_para_birimleri')) {
                return;
            }

            ParaBirimi::tenantScopeOlmadan(function () use ($firma): void {
                if (ParaBirimi::query()
                    ->whereNull('firma_id')
                    ->where('is_sabit', true)
                    ->where('kod', 'TRY')
                    ->exists()) {
                    return;
                }

                ParaBirimi::query()->firstOrCreate(
                    ['firma_id' => $firma->id, 'kod' => 'TRY'],
                    ['ad' => 'Türk Lirası', 'aktif_mi' => true, 'is_sabit' => false]
                );
            });

            if (Schema::hasTable('muhasebe_depolar')) {
                Depo::query()->firstOrCreate(
                    ['firma_id' => $firma->id, 'kod' => 'MERKEZ'],
                    ['ad' => 'Merkez Depo', 'aktif_mi' => true, 'varsayilan_mi' => true]
                );
            }
        });
    }
}
