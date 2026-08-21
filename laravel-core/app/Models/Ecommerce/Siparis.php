<?php

namespace App\Models\Ecommerce;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Siparis extends Model
{
    public const DURUM_DETAY_BEKLEYEN = 'detay_bekleyen';

    public const DURUM_ONAY_BEKLIYOR = 'onay_bekliyor';

    public const DURUM_EFT_ONAYI_BEKLIYOR = 'eft_onayi_bekliyor';

    public const DURUM_ONAYLANDI_YENI = 'onaylandi';

    public const DURUM_GONDERILDI = 'gonderildi';

    public const DURUM_TESLIM_EDILDI = 'teslim_edildi';

    public const DURUM_IPTAL_TALEBI = 'iptal_talebi';

    public const DURUM_IPTAL_EDILDI = 'iptal_edildi';

    public const DURUM_IADE_TALEBI = 'iade_talebi';

    public const DURUM_IADE_EDILDI = 'iade_edildi';

    public const DURUM_BASARISIZ_ODEME = 'basarisiz_odeme';

    public const DURUM_BEKLEMEDE = 'beklemede';

    /** @deprecated Yerine {@see self::DURUM_ONAY_BEKLIYOR} kullanın. */
    public const DURUM_ODEME_BEKLENIYOR = 'odeme_bekleniyor';

    /** @deprecated Yerine {@see self::DURUM_ONAYLANDI_YENI} kullanın. */
    public const DURUM_ONAYLANDI = 'onaylandi';

    /** @deprecated Yerine {@see self::DURUM_ONAYLANDI_YENI} kullanın. */
    public const DURUM_ODENDI = 'odendi';

    /** @deprecated Yerine {@see self::DURUM_ONAYLANDI_YENI} kullanın. */
    public const DURUM_HAZIRLANIYOR = 'hazirlaniyor';

    /** @deprecated Yerine {@see self::DURUM_GONDERILDI} kullanın. */
    public const DURUM_KARGOLANDI = 'kargolandi';

    /** @deprecated Yerine {@see self::DURUM_TESLIM_EDILDI} kullanın. */
    public const DURUM_TAMAMLANDI = 'tamamlandi';

    /** @deprecated Yerine {@see self::DURUM_IPTAL_EDILDI} kullanın. */
    public const DURUM_IPTAL = 'iptal';

    /** Finans hareketi referans_turu */
    public const REFERANS_TURU_FINANS = 'siparis';

    /**
     * @return array<string, string>
     */
    public static function durumEtiketleri(): array
    {
        return [
            self::DURUM_DETAY_BEKLEYEN => 'Detay Bekleyen',
            self::DURUM_ONAY_BEKLIYOR => 'Onay Bekliyor',
            self::DURUM_EFT_ONAYI_BEKLIYOR => 'EFT Onayı Bekliyor',
            self::DURUM_ONAYLANDI_YENI => 'Onaylandı',
            self::DURUM_GONDERILDI => 'Kargolandı',
            self::DURUM_TESLIM_EDILDI => 'Teslim Edildi',
            self::DURUM_IPTAL_TALEBI => 'İptal Talebi',
            self::DURUM_IPTAL_EDILDI => 'İptal Edildi',
            self::DURUM_IADE_TALEBI => 'İade Talebi',
            self::DURUM_IADE_EDILDI => 'İade Edildi',
            self::DURUM_BASARISIZ_ODEME => 'Başarısız Ödeme',
            self::DURUM_BEKLEMEDE => 'Beklemede',
            self::DURUM_ODEME_BEKLENIYOR => 'Onay Bekliyor',
            self::DURUM_ODENDI => 'Onaylandı',
            self::DURUM_HAZIRLANIYOR => 'Onaylandı',
            self::DURUM_KARGOLANDI => 'Kargolandı',
            self::DURUM_TAMAMLANDI => 'Teslim Edildi',
            self::DURUM_IPTAL => 'İptal Edildi',
        ];
    }

    public static function durumEtiketi(?string $durum): string
    {
        if (! is_string($durum) || $durum === '') {
            return '—';
        }

        return self::durumEtiketleri()[$durum] ?? $durum;
    }

    /**
     * @return array<string, string>
     */
    public static function durumRenkleri(): array
    {
        return [
            self::DURUM_DETAY_BEKLEYEN => 'warning',
            self::DURUM_ONAY_BEKLIYOR => 'warning',
            self::DURUM_EFT_ONAYI_BEKLIYOR => 'warning',
            self::DURUM_BASARISIZ_ODEME => 'danger',
            self::DURUM_ONAYLANDI_YENI => 'info',
            self::DURUM_GONDERILDI => 'primary',
            self::DURUM_TESLIM_EDILDI => 'success',
            self::DURUM_IPTAL_TALEBI => 'danger',
            self::DURUM_IPTAL_EDILDI => 'danger',
            self::DURUM_IADE_TALEBI => 'warning',
            self::DURUM_IADE_EDILDI => 'danger',
            self::DURUM_BEKLEMEDE => 'info',
            self::DURUM_ODEME_BEKLENIYOR => 'warning',
            self::DURUM_ODENDI => 'info',
            self::DURUM_HAZIRLANIYOR => 'info',
            self::DURUM_KARGOLANDI => 'primary',
            self::DURUM_TAMAMLANDI => 'success',
            self::DURUM_IPTAL => 'danger',
        ];
    }

    public static function durumRengi(?string $durum): string
    {
        if (! is_string($durum) || $durum === '') {
            return 'gray';
        }

        return self::durumRenkleri()[$durum] ?? 'gray';
    }

    public static function teslimEdildiDurumMu(?string $durum): bool
    {
        return in_array($durum, [self::DURUM_TESLIM_EDILDI, self::DURUM_TAMAMLANDI], true);
    }

    public static function iptalEdildiDurumMu(?string $durum): bool
    {
        return in_array($durum, [self::DURUM_IPTAL_EDILDI, self::DURUM_IPTAL], true);
    }

    public static function odemeAkisindaDurumMu(?string $durum): bool
    {
        return in_array($durum, [
            self::DURUM_ONAY_BEKLIYOR,
            self::DURUM_EFT_ONAYI_BEKLIYOR,
            self::DURUM_ODEME_BEKLENIYOR,
            self::DURUM_BASARISIZ_ODEME,
        ], true);
    }

    public static function odemeAlindiDurumMu(?string $durum): bool
    {
        return in_array($durum, self::odemeAlindiDurumlari(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function odemeAlindiDurumlari(): array
    {
        return [
            self::DURUM_ONAYLANDI_YENI,
            self::DURUM_ODENDI,
            self::DURUM_HAZIRLANIYOR,
            self::DURUM_GONDERILDI,
            self::DURUM_KARGOLANDI,
            self::DURUM_TESLIM_EDILDI,
            self::DURUM_TAMAMLANDI,
            self::DURUM_IADE_TALEBI,
            self::DURUM_IADE_EDILDI,
        ];
    }

    public static function kargoTakipZorunluDurumMu(?string $durum): bool
    {
        return in_array($durum, [self::DURUM_GONDERILDI, self::DURUM_KARGOLANDI], true);
    }

    protected $table = 'siparisler';

    protected $fillable = [
        'siparis_no',
        'firma_id',
        'kullanici_id',
        'musteri_ad_soyad',
        'musteri_email',
        'musteri_telefon',
        'teslimat_adresi',
        'teslimat_ulke',
        'teslimat_il',
        'teslimat_ilce',
        'teslimat_posta_kodu',
        'notlar',
        'para_birimi',
        'ara_toplam',
        'kdv_toplam',
        'indirim_toplami',
        'genel_toplam',
        'durum',
        'kampanya_id',
        'kampanya_adi',
        'kupon_kodu',
        'stok_dusuldu_mi',
        'odeme_yontemi_kodu',
        'odeme_yontemi_ad',
        'odeme_provider',
        'ecommerce_odeme_yontemi_id',
        'odeme_suresi_bitis_at',
        'odeme_deneme_sayisi',
        'havale_banka_hesap_id',
        'havale_banka_adi',
        'havale_hesap_sahibi',
        'havale_iban',
        'havale_aciklama_notu',
        'havale_referans_kodu',
        'kargo_yontemi_id',
        'kargo_ucreti',
        'kargo_para_birimi',
        'kargo_firmasi',
        'takip_no',
        'kargo_tarihi',
        'teslim_tarihi',
        'iptal_nedeni',
        'ic_not',
        'musteri_notu',
        'operasyon_notu',
        'muhasebe_cari_id',
        'proforma_fatura_id',
        'tahsilat_finans_hareketi_id',
        'muhasebe_entegrasyon_durumu',
        'muhasebe_entegrasyon_notu',
        'muhasebe_entegrasyon_at',
    ];

    protected function casts(): array
    {
        return [
            'ara_toplam' => 'decimal:2',
            'kdv_toplam' => 'decimal:2',
            'indirim_toplami' => 'decimal:2',
            'genel_toplam' => 'decimal:2',
            'kampanya_id' => 'integer',
            'stok_dusuldu_mi' => 'boolean',
            'havale_banka_hesap_id' => 'integer',
            'odeme_suresi_bitis_at' => 'datetime',
            'odeme_deneme_sayisi' => 'integer',
            'ecommerce_odeme_yontemi_id' => 'integer',
            'kargo_yontemi_id' => 'integer',
            'kargo_ucreti' => 'decimal:2',
            'kargo_tarihi' => 'date',
            'teslim_tarihi' => 'date',
            'muhasebe_cari_id' => 'integer',
            'proforma_fatura_id' => 'integer',
            'tahsilat_finans_hareketi_id' => 'integer',
            'muhasebe_entegrasyon_at' => 'datetime',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }

    public function kalemler(): HasMany
    {
        return $this->hasMany(SiparisKalemi::class, 'siparis_id');
    }

    public function odemeler(): HasMany
    {
        return $this->hasMany(Odeme::class, 'siparis_id')->orderByDesc('id');
    }

    public function sonOdeme(): HasOne
    {
        return $this->hasOne(Odeme::class)->latestOfMany();
    }

    public function kargoYontemi(): BelongsTo
    {
        return $this->belongsTo(EcommerceKargoYontemi::class, 'kargo_yontemi_id');
    }

    public function odemeYontemi(): BelongsTo
    {
        return $this->belongsTo(EcommerceOdemeYontemi::class, 'ecommerce_odeme_yontemi_id');
    }

    public function muhasebeCari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'muhasebe_cari_id');
    }

    public function proformaFatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'proforma_fatura_id');
    }

    public function tahsilatFinansHareketi(): BelongsTo
    {
        return $this->belongsTo(FinansHareketi::class, 'tahsilat_finans_hareketi_id');
    }

    public function gecmisleri(): HasMany
    {
        return $this->hasMany(SiparisGecmisi::class, 'siparis_id')->orderByDesc('created_at');
    }
}
