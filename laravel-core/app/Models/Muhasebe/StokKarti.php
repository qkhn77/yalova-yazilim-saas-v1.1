<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Modules\Urun\Servisler\UrunServisi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\OlculuStokTakipTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Support\SaaSemaYardimcisi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StokKarti extends Model
{
    public const STOK_TAKIP_TIPI_BASIT = 'basit';
    public const STOK_TAKIP_TIPI_SERI = 'seri';

    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'stok_kartlari';

    protected $attributes = [
        'olculu_takip_turu' => 'standart',
        'parcali_kullanima_izin' => false,
    ];

    private static ?bool $stokKartiGorselleriTablosuVar = null;

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if ($model->exists && ($model->isDirty('olculu_takip_turu') || $model->isDirty('olcu_yapisi'))) {
                // Tenant kapsamı veya mevcut kullanıcı bağlamı değişmiş olsa bile
                // kilit, kartın gerçek geçmiş kayıtlarına göre uygulanmalıdır.
                $hareketVar = $model->stokHareketleri()->withoutGlobalScopes()->exists()
                    || $model->olcuBakiyeleri()->withoutGlobalScopes()->exists()
                    || $model->stokSeriNolari()->withoutGlobalScopes()->exists();
                if ($hareketVar) {
                    throw new InvalidArgumentException('Hareket veya bakiye görmüş stok kartının ölçü takip yöntemi ya da ölçü yapısı doğrudan değiştirilemez.');
                }
            }
            $olculuTur = $model->olculu_takip_turu instanceof OlculuStokTakipTuru
                ? $model->olculu_takip_turu
                : OlculuStokTakipTuru::tryFrom((string) ($model->olculu_takip_turu ?? 'standart'));
            if ($olculuTur?->olculuMu()) {
                if ((string) $model->stok_takip_tipi === self::STOK_TAKIP_TIPI_SERI) {
                    throw new InvalidArgumentException('Ölçülü stok ile seri numarası takibi birlikte kullanılamaz.');
                }
                if (! $model->ana_birim_id || ! $model->ikincil_birim_id || (int) $model->ana_birim_id === (int) $model->ikincil_birim_id) {
                    throw new InvalidArgumentException('Ölçülü stokta ana ve ikincil birimler farklı ve dolu olmalıdır.');
                }
            }
            $slug = trim((string) ($model->slug ?? ''));
            if ($slug === '') {
                $slug = Str::slug((string) ($model->ad ?? ''));
            }
            if ($slug === '') {
                $slug = 'urun';
            }

            $model->slug = static::benzersizSlugUret(
                slug: $slug,
                firmaId: (int) ($model->firma_id ?? 0),
                haricId: $model->exists ? (int) $model->getKey() : null
            );
        });

        $clear = static function (): void {
            Cache::forget('sitemap_xml');
            UrunServisi::cacheTemizle();
        };

        static::saved($clear);
        static::deleted($clear);
        static::restored($clear);
    }

    protected $fillable = [
        'firma_id',
        'kod',
        'sku',
        'upc',
        'ean',
        'gtin',
        'mpn',
        'amazon_asin',
        'fba_kodu',
        'ad',
        'kisa_ad',
        'slug',
        'barkod',
        'seri_no',
        'imei_no',
        'tur',
        'kategori_kodu',
        'kategori_id',
        'birim',
        'alis_fiyati',
        'satis_fiyati',
        'indirimli_fiyat',
        'para_birimi',
        'kdv_orani',
        'gumruk_orani',
        'kritik_seviye_miktar',
        'aciklama',
        'durum',
        'stok_takip',
        'stok_takip_tipi',
        'olculu_takip_turu', 'olcu_yapisi', 'ana_birim_id', 'ikincil_birim_id',
        'varsayilan_islem_birimi_id', 'varsayilan_fiyat_birimi_id', 'parcali_kullanima_izin', 'agirlik_turu',
        'minimum_stok',
        'maksimum_stok',
        'stok_miktari',
        'rezerve_miktar',
        'depo_id',
        'marka_id',
        'marka_uretici',
        'model_id',
        'tasarim_id',
        'malzeme_turu_id',
        'logo_turu_id',
        'varyant_id',
        'tedarikci_id',
        'agirlik',
        'hacim',
        'kargo_sinifi',
        'satis_adedi',
        'goruntulenme_sayisi',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'og_gorsel',
        'og_baslik',
        'og_aciklama',
        'og_etiket',
        'guncel_birim_maliyet',
        'stok_degeri',
        'son_giris_maliyeti',
        'son_hareket_tarihi',
        'negative_flag',
    ];

    protected function casts(): array
    {
        return [
            'tur' => StokKartiTuru::class,
            'durum' => HesapDurumu::class,
            'alis_fiyati' => 'decimal:8',
            'satis_fiyati' => 'decimal:8',
            'indirimli_fiyat' => 'decimal:8',
            'kdv_orani' => 'decimal:2',
            'gumruk_orani' => 'decimal:2',
            'kritik_seviye_miktar' => 'decimal:8',
            'stok_takip' => 'boolean',
            'minimum_stok' => 'decimal:8',
            'maksimum_stok' => 'decimal:8',
            'stok_miktari' => 'decimal:8',
            'rezerve_miktar' => 'decimal:8',
            'olculu_takip_turu' => OlculuStokTakipTuru::class,
            'parcali_kullanima_izin' => 'boolean',
            'agirlik' => 'decimal:2',
            'hacim' => 'decimal:3',
            'depo_id' => 'integer',
            'guncel_birim_maliyet' => 'decimal:8',
            'stok_degeri' => 'decimal:8',
            'son_giris_maliyeti' => 'decimal:8',
            'son_hareket_tarihi' => 'datetime',
            'negative_flag' => 'boolean',
        ];
    }

    /** @param  Builder<static>  $sorgu */
    public function scopeFirma(Builder $sorgu, int $firmaId): Builder
    {
        return $sorgu->where('firma_id', $firmaId);
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function stokHareketleri(): HasMany
    {
        return $this->hasMany(StokHareketi::class, 'stok_id');
    }

    public function olculer(): HasMany { return $this->hasMany(StokOlcusu::class, 'stok_id'); }
    public function olcuBakiyeleri(): HasMany { return $this->hasMany(StokOlcuBakiyesi::class, 'stok_id'); }
    public function olcuHareketDagilimlari(): HasMany { return $this->hasMany(StokHareketiOlcuDagilimi::class, 'stok_id'); }

    public function stokSeriNolari(): HasMany
    {
        return $this->hasMany(StokSeriNo::class, 'stok_id');
    }

    public function stokOlculeri(): HasMany
    {
        return $this->hasMany(StokOlcusu::class, 'stok_id');
    }

    public function parcaTakibiAktifMi(): bool
    {
        return (bool) $this->parcali_kullanima_izin;
    }

    /** Bir adet/plaka için ölçüler girilmişse metrekare karşılığını verir. */
    public function birimMetrekare(): ?float
    {
        $olculer = $this->stokOlculeri()
            ->where('aktif_mi', true)
            ->where('takip_turu', OlculuStokTakipTuru::Alan->value)
            ->get(['en_m', 'boy_m']);
        if ($olculer->count() !== 1) {
            return null;
        }
        $olcu = $olculer->first();
        if (! $olcu || (float) $olcu->en_m <= 0 || (float) $olcu->boy_m <= 0) {
            return null;
        }

        return round((float) $olcu->en_m * (float) $olcu->boy_m, 6);
    }

    /** Mevcut adet/plaka stoğunun ölçü bazlı toplam metrekare karşılığı. */
    public function toplamMetrekare(): ?float
    {
        if (! $this->stokOlculeri()->where('aktif_mi', true)->where('takip_turu', OlculuStokTakipTuru::Alan->value)->exists()) {
            return null;
        }

        $toplam = (float) StokOlcuBakiyesi::query()
            ->where('stok_id', $this->getKey())
            ->where('durum', 'aktif')
            ->sum('ana_miktar');

        return round($toplam, 6);
    }

    public function gorseller(): HasMany
    {
        return $this->hasMany(StokKartiGorseli::class, 'stok_karti_id')
            ->orderByDesc('kapak_mi')
            ->orderBy('sira')
            ->orderBy('id');
    }

    public function barkodlar(): HasMany
    {
        return $this->hasMany(StokBarkodu::class, 'stok_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(StokKategorisi::class, 'kategori_id');
    }

    public function faturaKalemleri(): HasMany
    {
        return $this->hasMany(FaturaKalemi::class, 'stok_id');
    }

    public function marka(): BelongsTo
    {
        return $this->belongsTo(MuhasebeMarka::class, 'marka_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(MuhasebeStokModeli::class, 'model_id');
    }

    public function tasarim(): BelongsTo
    {
        return $this->belongsTo(MuhasebeTasarim::class, 'tasarim_id');
    }

    public function malzemeTuru(): BelongsTo
    {
        return $this->belongsTo(MuhasebeMalzemeTuru::class, 'malzeme_turu_id');
    }

    public function logoTuru(): BelongsTo
    {
        return $this->belongsTo(MuhasebeLogoTuru::class, 'logo_turu_id');
    }

    public function varyant(): BelongsTo
    {
        return $this->belongsTo(MuhasebeVaryant::class, 'varyant_id');
    }

    public function tedarikci(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'tedarikci_id');
    }

    public function depo(): BelongsTo
    {
        return $this->belongsTo(Depo::class, 'depo_id');
    }

    private static function benzersizSlugUret(string $slug, int $firmaId, ?int $haricId = null): string
    {
        $aday = $slug;
        $i = 1;

        while (static::slugVarMi($aday, $firmaId, $haricId)) {
            $aday = $slug.'-'.$i;
            $i++;
        }

        return $aday;
    }

    /**
     * Stok takibinde satılabilir miktar: fiziksel − rezervasyon havuzu.
     */
    public function musaitStokMiktari(): float
    {
        if (! (bool) $this->stok_takip) {
            return PHP_FLOAT_MAX;
        }

        return max(0.0, (float) $this->stok_miktari - (float) ($this->rezerve_miktar ?? 0));
    }

    private static function slugVarMi(string $slug, int $firmaId, ?int $haricId): bool
    {
        return static::tenantScopeOlmadan(function () use ($slug, $firmaId, $haricId): bool {
            $q = static::query()
                ->where('firma_id', $firmaId)
                ->where('slug', $slug);

            if ($haricId !== null) {
                $q->whereKeyNot($haricId);
            }

            return $q->exists();
        });
    }

    public function kapakGorseliKaydi(): ?StokKartiGorseli
    {
        if (! self::stokKartiGorselleriTablosuVarMi()) {
            return null;
        }

        if ($this->relationLoaded('gorseller')) {
            /** @var \Illuminate\Support\Collection<int, StokKartiGorseli> $items */
            $items = $this->getRelation('gorseller');

            return $items
                ->where('aktif_mi', true)
                ->sortBy([
                    ['kapak_mi', 'desc'],
                    ['sira', 'asc'],
                    ['id', 'asc'],
                ])
                ->first();
        }

        return $this->gorseller()
            ->where('aktif_mi', true)
            ->first();
    }

    /**
     * @return array<int, StokKartiGorseli>
     */
    public function galeriGorseliKayitlari(): array
    {
        if (! self::stokKartiGorselleriTablosuVarMi()) {
            return [];
        }

        if ($this->relationLoaded('gorseller')) {
            /** @var \Illuminate\Support\Collection<int, StokKartiGorseli> $items */
            $items = $this->getRelation('gorseller');

            return $items
                ->where('aktif_mi', true)
                ->sortBy([
                    ['kapak_mi', 'desc'],
                    ['sira', 'asc'],
                    ['id', 'asc'],
                ])
                ->values()
                ->all();
        }

        return $this->gorseller()
            ->where('aktif_mi', true)
            ->get()
            ->all();
    }

    public function getKapakGorselYoluAttribute(): ?string
    {
        return $this->kapakGorseliKaydi()?->dosya_yolu;
    }

    public function getKapakGorselUrlAttribute(): ?string
    {
        return $this->kapakGorseliKaydi()?->url;
    }

    /**
     * @return array<int, string>
     */
    public function getGaleriGorselYollariAttribute(): array
    {
        return array_values(array_filter(array_map(
            static fn (StokKartiGorseli $image): string => (string) $image->dosya_yolu,
            $this->galeriGorseliKayitlari()
        )));
    }

    private static function stokKartiGorselleriTablosuVarMi(): bool
    {
        if (self::$stokKartiGorselleriTablosuVar === null) {
            self::$stokKartiGorselleriTablosuVar = SaaSemaYardimcisi::tabloVarMi('stok_karti_gorselleri');
        }

        return self::$stokKartiGorselleriTablosuVar;
    }
}
