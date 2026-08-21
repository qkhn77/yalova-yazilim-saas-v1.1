<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MasrafKategorisi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'masraf_kategorileri';

    protected $fillable = [
        'firma_id',
        'ust_kategori_id',
        'kod',
        'ad',
        'sira',
        'sistem_mi',
        'secilir_mi',
        'aktif_mi',
    ];

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
            'sistem_mi' => 'boolean',
            'secilir_mi' => 'boolean',
        ];
    }

    /** @param Builder<static> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif_mi', true);
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function ustKategori(): BelongsTo
    {
        return $this->belongsTo(self::class, 'ust_kategori_id');
    }

    public function altKategoriler(): HasMany
    {
        return $this->hasMany(self::class, 'ust_kategori_id')->orderBy('sira')->orderBy('ad');
    }

    public static function secenekCacheAnahtari(int $firmaId): string
    {
        return 'masraf-takip:kategori-secenekleri:v3:firma:'.$firmaId;
    }

    public static function anaKategoriCacheAnahtari(int $firmaId): string
    {
        return 'masraf-takip:ana-kategori-secenekleri:v1:firma:'.$firmaId;
    }

    public static function varsayilanHazirlikCacheAnahtari(int $firmaId): string
    {
        return 'masraf-takip:varsayilan-kategoriler-hazir:v1:firma:'.$firmaId;
    }

    public function masraflar(): HasMany
    {
        return $this->hasMany(Masraf::class, 'masraf_kategorisi_id');
    }

    /** @return array<int, array{kod:string, ad:string, sira:int}> */
    public static function varsayilanlar(): array
    {
        return [
            ['kod' => 'personel', 'ad' => 'Personel', 'sira' => 10],
            ['kod' => 'elektrik', 'ad' => 'Elektrik', 'sira' => 20],
            ['kod' => 'su', 'ad' => 'Su', 'sira' => 30],
            ['kod' => 'dogalgaz', 'ad' => 'Doğalgaz', 'sira' => 40],
            ['kod' => 'telefon_internet', 'ad' => 'Telefon / İnternet', 'sira' => 50],
            ['kod' => 'arac', 'ad' => 'Araç', 'sira' => 60],
            ['kod' => 'kira', 'ad' => 'Kira', 'sira' => 70],
            ['kod' => 'vergi_harc', 'ad' => 'Vergi / Harç', 'sira' => 80],
            ['kod' => 'bakim_onarim', 'ad' => 'Bakım / Onarım', 'sira' => 90],
            ['kod' => 'ofis', 'ad' => 'Ofis', 'sira' => 100],
            ['kod' => 'pazarlama', 'ad' => 'Pazarlama', 'sira' => 110],
            ['kod' => 'diger', 'ad' => 'Diğer', 'sira' => 999],
        ];
    }

    /** @return array<int, array{kod:string, ad:string, sira:int, alt_turler:array<int, array{kod:string, ad:string, sira:int}>}> */
    public static function sabitHiyerarsi(): array
    {
        return [
            ['kod' => 'duzenli_faturalar', 'ad' => 'Düzenli Faturalar', 'sira' => 10, 'alt_turler' => [
                ['kod' => 'elektrik', 'ad' => 'Elektrik', 'sira' => 10],
                ['kod' => 'su', 'ad' => 'Su', 'sira' => 20],
                ['kod' => 'dogalgaz', 'ad' => 'Doğalgaz', 'sira' => 30],
                ['kod' => 'telefon', 'ad' => 'Telefon', 'sira' => 40],
                ['kod' => 'internet', 'ad' => 'İnternet', 'sira' => 50],
                ['kod' => 'hosting_domain', 'ad' => 'Hosting / Domain', 'sira' => 60],
            ]],
            ['kod' => 'personel_giderleri', 'ad' => 'Personel Giderleri', 'sira' => 20, 'alt_turler' => [
                ['kod' => 'personel', 'ad' => 'Personel', 'sira' => 10],
                ['kod' => 'maas', 'ad' => 'Maaş', 'sira' => 20],
                ['kod' => 'sgk', 'ad' => 'SGK', 'sira' => 30],
                ['kod' => 'personel_yemek', 'ad' => 'Yemek', 'sira' => 40],
                ['kod' => 'personel_yol', 'ad' => 'Yol', 'sira' => 50],
                ['kod' => 'personel_prim', 'ad' => 'Prim', 'sira' => 60],
                ['kod' => 'personel_egitim', 'ad' => 'Eğitim', 'sira' => 70],
            ]],
            ['kod' => 'arac_ve_ulasim', 'ad' => 'Araç ve Ulaşım', 'sira' => 30, 'alt_turler' => [
                ['kod' => 'arac', 'ad' => 'Araç', 'sira' => 10],
                ['kod' => 'yakit', 'ad' => 'Yakıt', 'sira' => 20],
                ['kod' => 'arac_bakim', 'ad' => 'Bakım', 'sira' => 30],
                ['kod' => 'arac_onarim', 'ad' => 'Onarım', 'sira' => 40],
                ['kod' => 'arac_sigorta', 'ad' => 'Sigorta', 'sira' => 50],
                ['kod' => 'mtv', 'ad' => 'MTV', 'sira' => 60],
                ['kod' => 'otopark', 'ad' => 'Otopark', 'sira' => 70],
                ['kod' => 'kopru_otoyol', 'ad' => 'Köprü / Otoyol', 'sira' => 80],
            ]],
            ['kod' => 'kira_ve_isletme', 'ad' => 'Kira ve İşletme', 'sira' => 40, 'alt_turler' => [
                ['kod' => 'kira', 'ad' => 'Kira', 'sira' => 10],
                ['kod' => 'aidat', 'ad' => 'Aidat', 'sira' => 20],
                ['kod' => 'isletme_sigortasi', 'ad' => 'İşletme Sigortası', 'sira' => 30],
                ['kod' => 'ruhsat', 'ad' => 'Ruhsat', 'sira' => 40],
                ['kod' => 'vergi_harc', 'ad' => 'Vergi / Harç', 'sira' => 50],
            ]],
            ['kod' => 'ofis_ve_sarf', 'ad' => 'Ofis ve Sarf', 'sira' => 50, 'alt_turler' => [
                ['kod' => 'ofis', 'ad' => 'Ofis', 'sira' => 10],
                ['kod' => 'kirtasiye', 'ad' => 'Kırtasiye', 'sira' => 20],
                ['kod' => 'temizlik', 'ad' => 'Temizlik', 'sira' => 30],
                ['kod' => 'mutfak', 'ad' => 'Mutfak', 'sira' => 40],
                ['kod' => 'buro_malzemeleri', 'ad' => 'Büro Malzemeleri', 'sira' => 50],
            ]],
            ['kod' => 'teknik_servis_operasyon', 'ad' => 'Teknik Servis ve Operasyon', 'sira' => 60, 'alt_turler' => [
                ['kod' => 'bakim_onarim', 'ad' => 'Bakım / Onarım', 'sira' => 10],
                ['kod' => 'taseron', 'ad' => 'Taşeron', 'sira' => 20],
                ['kod' => 'kurye', 'ad' => 'Kurye', 'sira' => 30],
                ['kod' => 'servis_yol', 'ad' => 'Servis Yol Gideri', 'sira' => 40],
                ['kod' => 'kucuk_malzeme', 'ad' => 'Küçük Malzeme', 'sira' => 50],
            ]],
            ['kod' => 'pazarlama_ve_satis', 'ad' => 'Pazarlama ve Satış', 'sira' => 70, 'alt_turler' => [
                ['kod' => 'pazarlama', 'ad' => 'Pazarlama', 'sira' => 10],
                ['kod' => 'reklam', 'ad' => 'Reklam', 'sira' => 20],
                ['kod' => 'sosyal_medya', 'ad' => 'Sosyal Medya', 'sira' => 30],
                ['kod' => 'baski', 'ad' => 'Baskı', 'sira' => 40],
                ['kod' => 'web_sitesi', 'ad' => 'Web Sitesi', 'sira' => 50],
                ['kod' => 'musteri_ikrami', 'ad' => 'Müşteri İkramı', 'sira' => 60],
            ]],
            ['kod' => 'finans_ve_banka', 'ad' => 'Finans ve Banka', 'sira' => 80, 'alt_turler' => [
                ['kod' => 'banka_masrafi', 'ad' => 'Banka Masrafı', 'sira' => 10],
                ['kod' => 'pos_komisyonu', 'ad' => 'POS Komisyonu', 'sira' => 20],
                ['kod' => 'havale_eft', 'ad' => 'Havale / EFT', 'sira' => 30],
                ['kod' => 'kredi_faizi', 'ad' => 'Kredi Faizi', 'sira' => 40],
            ]],
            ['kod' => 'muhasebe_ve_hukuk', 'ad' => 'Muhasebe ve Hukuk', 'sira' => 90, 'alt_turler' => [
                ['kod' => 'mali_musavir', 'ad' => 'Mali Müşavir', 'sira' => 10],
                ['kod' => 'noter', 'ad' => 'Noter', 'sira' => 20],
                ['kod' => 'danismanlik', 'ad' => 'Danışmanlık', 'sira' => 30],
                ['kod' => 'hukuk', 'ad' => 'Hukuk', 'sira' => 40],
            ]],
            ['kod' => 'diger_grubu', 'ad' => 'Diğer', 'sira' => 999, 'alt_turler' => [
                ['kod' => 'diger', 'ad' => 'Diğer', 'sira' => 10],
            ]],
        ];
    }

    public static function varsayilanlariHazirla(int $firmaId): void
    {
        if ($firmaId < 1) {
            return;
        }

        Cache::remember(
            static::varsayilanHazirlikCacheAnahtari($firmaId),
            now()->addMinutes(10),
            function () use ($firmaId): bool {
                DB::transaction(function () use ($firmaId): void {
            $mevcutlar = static::query()
                ->where('firma_id', $firmaId)
                ->get()
                ->keyBy('kod');
            $degisti = false;

            foreach (static::sabitHiyerarsi() as $anaKategori) {
                $ust = $mevcutlar->get($anaKategori['kod']);
                if (! $ust) {
                    $ust = static::query()->create([
                        'firma_id' => $firmaId,
                        'ad' => $anaKategori['ad'],
                        'kod' => $anaKategori['kod'],
                        'sira' => $anaKategori['sira'],
                        'sistem_mi' => true,
                        'secilir_mi' => false,
                        'aktif_mi' => true,
                    ]);
                    $mevcutlar->put($ust->kod, $ust);
                    $degisti = true;
                }

                if (
                    ! $ust->sistem_mi
                    || $ust->ad !== $anaKategori['ad']
                    || (int) $ust->sira !== $anaKategori['sira']
                    || $ust->secilir_mi
                ) {
                    $ust->forceFill([
                        'ad' => $anaKategori['ad'],
                        'sira' => $anaKategori['sira'],
                        'sistem_mi' => true,
                        'secilir_mi' => false,
                    ])->saveQuietly();
                    $degisti = true;
                }

                foreach ($anaKategori['alt_turler'] as $altTur) {
                    $alt = $mevcutlar->get($altTur['kod']);
                    if (! $alt) {
                        $alt = static::query()->create([
                            'firma_id' => $firmaId,
                            'ust_kategori_id' => $ust->id,
                            'ad' => $altTur['ad'],
                            'kod' => $altTur['kod'],
                            'sira' => $altTur['sira'],
                            'sistem_mi' => true,
                            'secilir_mi' => true,
                            'aktif_mi' => true,
                        ]);
                        $mevcutlar->put($alt->kod, $alt);
                        $degisti = true;
                    }

                    if (
                        (int) $alt->ust_kategori_id !== (int) $ust->id
                        || $alt->ad !== $altTur['ad']
                        || (int) $alt->sira !== $altTur['sira']
                        || ! $alt->sistem_mi
                        || ! $alt->secilir_mi
                    ) {
                        $alt->forceFill([
                            'ust_kategori_id' => $ust->id,
                            'ad' => $altTur['ad'],
                            'sira' => $altTur['sira'],
                            'sistem_mi' => true,
                            'secilir_mi' => true,
                        ])->saveQuietly();
                        $degisti = true;
                    }
                }
            }

            if ($degisti) {
                Cache::forget(static::secenekCacheAnahtari($firmaId));
                Cache::forget(static::anaKategoriCacheAnahtari($firmaId));
            }
                });

                return true;
            },
        );
    }
}
