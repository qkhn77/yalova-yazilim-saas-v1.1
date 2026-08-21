<?php

namespace App\Models\Restoran;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Personel\Personel;
use App\Models\Sube;
use App\Services\Restoran\RestoranAdisyonKuralServisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestoranAdisyonu extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    public const DURUM_ACIK = 'acik';

    public const DURUM_ODEMEDE = 'odemede';

    public const DURUM_KAPANDI = 'kapandi';

    public const DURUM_IPTAL = 'iptal';

    public const PAKET_DURUM_HAZIRLANIYOR = 'hazirlaniyor';

    public const PAKET_DURUM_KURYEE_ATANDI = 'kuryeye_atandi';

    public const PAKET_DURUM_YOLDA = 'yolda';

    public const PAKET_DURUM_TESLIM_EDILDI = 'teslim_edildi';

    public const PAKET_DURUM_IPTAL = 'iptal';

    protected $table = 'restoran_adisyonlari';

    protected $fillable = [
        'firma_id',
        'sube_id',
        'masa_id',
        'cari_id',
        'garson_personel_id',
        'kasiyer_personel_id',
        'kurye_personel_id',
        'kasa_hesap_id',
        'banka_hesap_id',
        'pos_hesap_id',
        'finans_hareketi_id',
        'adisyon_no',
        'acilis_at',
        'kapanis_at',
        'durum',
        'siparis_tipi',
        'paket_durum',
        'teslimat_telefon',
        'teslimat_adresi',
        'tahmini_teslimat_at',
        'teslimat_notu',
        'odeme_kanali',
        'musteri_sayisi',
        'ara_toplam',
        'indirim_toplam',
        'ikram_toplam',
        'kdv_toplam',
        'servis_ucreti',
        'genel_toplam',
        'para_birimi',
        'tahsilat_at',
        'teslimat_at',
        'notlar',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $adisyon): void {
            app(RestoranAdisyonKuralServisi::class)->hazirlaVeDogrula($adisyon);
        });

        static::saved(static function (self $adisyon): void {
            app(RestoranAdisyonKuralServisi::class)->masaDurumunuGuncelle($adisyon);
        });
    }

    protected function casts(): array
    {
        return [
            'acilis_at' => 'datetime',
            'kapanis_at' => 'datetime',
            'tahsilat_at' => 'datetime',
            'teslimat_at' => 'datetime',
            'tahmini_teslimat_at' => 'datetime',
            'musteri_sayisi' => 'integer',
            'ara_toplam' => 'decimal:2',
            'indirim_toplam' => 'decimal:2',
            'ikram_toplam' => 'decimal:2',
            'kdv_toplam' => 'decimal:2',
            'servis_ucreti' => 'decimal:2',
            'genel_toplam' => 'decimal:2',
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

    public function masa(): BelongsTo
    {
        return $this->belongsTo(RestoranMasasi::class, 'masa_id');
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function garson(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'garson_personel_id');
    }

    public function kasiyer(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'kasiyer_personel_id');
    }

    public function kurye(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'kurye_personel_id');
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

    public function finansHareketi(): BelongsTo
    {
        return $this->belongsTo(FinansHareketi::class, 'finans_hareketi_id');
    }

    public function kalemler(): HasMany
    {
        return $this->hasMany(RestoranAdisyonKalemi::class, 'adisyon_id');
    }

    public function tahsilatlar(): HasMany
    {
        return $this->hasMany(RestoranAdisyonTahsilati::class, 'adisyon_id');
    }
}
