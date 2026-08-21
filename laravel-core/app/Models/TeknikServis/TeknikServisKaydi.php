<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\AlacakPlani;
use App\Models\Muhasebe\Cari;
use App\Models\User;
use App\TeknikServis\Enumlar\MusteriOnayDurumu;
use App\TeknikServis\Enumlar\OdemeDurumu;
use App\TeknikServis\Enumlar\Oncelik;
use App\TeknikServis\Enumlar\ServisKanali;
use App\TeknikServis\Enumlar\ServisTipi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class TeknikServisKaydi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'teknik_servis_kayitlari';

    protected $fillable = [
        'firma_id',
        'servis_tipi',
        'oncelik',
        'servis_kanali',
        'cari_id',
        'kayitli_cihaz_id',
        'cihaz_id',
        'marka_id',
        'ariza_id',
        'model_no',
        'seri_no',
        'musteri_ad_soyad',
        'musteri_tel',
        'km_bilgisi',
        'musteri_sikayeti',
        'ic_servis_notu',
        'musteriye_gorunen_not',
        'yapilan_islemler',
        'kabul_tarihi',
        'fis_no',
        'garanti_baslangic_tarihi',
        'garanti_bitis_tarihi',
        'bakim_tarihi',
        'bakim_periyot_ay',
        'teklif_tutari',
        'teklif_tarihi',
        'musteri_onay_durumu',
        'onay_notu',
        'teslim_tarihi',
        'teslim_eden_kullanici_id',
        'teslim_alan_ad_soyad',
        'teslim_alan_tel',
        'teslim_notu',
        'cihaz_gorseller',
        'iptal_nedeni',
        'iade_nedeni',
        'servis_durumu_id',
        'toplam_tutar',
        'odenen_tutar',
        'odeme_durumu',
        'tahsilat_kanali',
        'tahsilat_kasa_hesap_id',
        'tahsilat_banka_hesap_id',
        'tahsilat_pos_hesap_id',
        'tahsilat_para_birimi',
        'tahsilat_hedef_para_birimi',
        'tahsilat_doviz_kuru_turu',
        'tahsilat_doviz_kuru',
        'tahsilat_tutari',
        'tahsilat_hedef_tutar',
        'tahsilat_tarihi',
        'tahsilat_aciklama',
        'olusturan_id',
        'guncelleyen_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $kayit): void {
            // Eski/izole kurulumlarda kayıtlı cihaz migrasyonu henüz yoksa
            // temel servis kaydı yine de oluşturulabilmelidir.
            if (! Schema::hasColumn('teknik_servis_kayitlari', 'kayitli_cihaz_id')
                || ! Schema::hasTable('teknik_servis_kayitli_cihazlar')) {
                return;
            }

            $kimlikAlanlari = ['cari_id', 'cihaz_id', 'marka_id', 'model_no', 'seri_no'];
            $kimlikDegisti = ! $kayit->exists || collect($kimlikAlanlari)->contains(fn (string $alan): bool => $kayit->isDirty($alan));

            if (! $kimlikDegisti || (int) $kayit->firma_id < 1 || (int) $kayit->cari_id < 1) {
                return;
            }

            $degerler = [
                'firma_id' => (int) $kayit->firma_id,
                'cari_id' => (int) $kayit->cari_id,
                'cihaz_id' => $kayit->cihaz_id ?: null,
                'marka_id' => $kayit->marka_id ?: null,
                'model_no' => trim((string) ($kayit->model_no ?? '')) ?: null,
                'seri_no' => trim((string) ($kayit->seri_no ?? '')) ?: null,
            ];

            if (! $degerler['cihaz_id'] && ! $degerler['marka_id'] && ! $degerler['model_no'] && ! $degerler['seri_no']) {
                $kayit->kayitli_cihaz_id = null;

                return;
            }

            $cihaz = TeknikServisKayitliCihazi::query()->firstOrCreate(
                $degerler,
                [
                    'aktif_mi' => true,
                    'olusturan_id' => Auth::id(),
                    'guncelleyen_id' => Auth::id(),
                ]
            );

            $kayit->kayitli_cihaz_id = $cihaz->getKey();
        });

        static::saved(function (self $kayit): void {
            if (! $kayit->kayitli_cihaz_id) {
                return;
            }

            $alanlar = collect([
                'garanti_baslangic_tarihi' => $kayit->garanti_baslangic_tarihi,
                'garanti_bitis_tarihi' => $kayit->garanti_bitis_tarihi,
                'son_bakim_tarihi' => $kayit->bakim_tarihi,
                'bakim_periyot_ay' => $kayit->bakim_periyot_ay,
            ])->filter(fn ($deger): bool => filled($deger))->all();

            if ($alanlar === []) {
                return;
            }

            TeknikServisKayitliCihazi::query()
                ->whereKey($kayit->kayitli_cihaz_id)
                ->update($alanlar + ['guncelleyen_id' => Auth::id(), 'updated_at' => now()]);
        });
    }

    protected function casts(): array
    {
        return [
            'servis_tipi' => ServisTipi::class,
            'oncelik' => Oncelik::class,
            'servis_kanali' => ServisKanali::class,
            'musteri_onay_durumu' => MusteriOnayDurumu::class,
            'odeme_durumu' => OdemeDurumu::class,
            'kabul_tarihi' => 'datetime',
            'garanti_baslangic_tarihi' => 'date',
            'garanti_bitis_tarihi' => 'date',
            'bakim_tarihi' => 'date',
            'bakim_periyot_ay' => 'integer',
            'teklif_tarihi' => 'datetime',
            'teslim_tarihi' => 'datetime',
            'teklif_tutari' => 'decimal:2',
            'toplam_tutar' => 'decimal:2',
            'odenen_tutar' => 'decimal:2',
            'tahsilat_tutari' => 'decimal:2',
            'tahsilat_doviz_kuru' => 'decimal:8',
            'tahsilat_hedef_tutar' => 'decimal:2',
            'tahsilat_tarihi' => 'datetime',
            'km_bilgisi' => 'integer',
            'cihaz_gorseller' => 'array',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function cihaz(): BelongsTo
    {
        return $this->belongsTo(TeknikServisCihazTanimi::class, 'cihaz_id');
    }

    public function kayitliCihaz(): BelongsTo
    {
        return $this->belongsTo(TeknikServisKayitliCihazi::class, 'kayitli_cihaz_id');
    }

    public function marka(): BelongsTo
    {
        return $this->belongsTo(TeknikServisMarkaTanimi::class, 'marka_id');
    }

    public function ariza(): BelongsTo
    {
        return $this->belongsTo(TeknikServisArizaTanimi::class, 'ariza_id');
    }

    public function arizalar(): BelongsToMany
    {
        return $this->belongsToMany(
            TeknikServisArizaTanimi::class,
            'teknik_servis_ariza_kayitlari',
            'teknik_servis_kaydi_id',
            'ariza_id'
        )->withPivot(['firma_id', 'created_at', 'updated_at']);
    }

    public function servisDurumu(): BelongsTo
    {
        return $this->belongsTo(TeknikServisDurumTanimi::class, 'servis_durumu_id');
    }

    /**
     * @deprecated {@see servisDurumu()} kullanın.
     */
    public function durum(): BelongsTo
    {
        return $this->servisDurumu();
    }

    public function teslimEden(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teslim_eden_kullanici_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }

    public function guncelleyen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guncelleyen_id');
    }

    public function kalemler(): HasMany
    {
        return $this->hasMany(TeknikServisKalem::class, 'teknik_servis_kaydi_id');
    }

    public function dokumanlar(): HasMany
    {
        return $this->hasMany(TeknikServisDokuman::class, 'teknik_servis_kaydi_id');
    }

    public function durumGecmisleri(): HasMany
    {
        return $this->hasMany(TeknikServisDurumGecmisi::class, 'teknik_servis_kaydi_id');
    }

    public function islemLoglari(): HasMany
    {
        return $this->hasMany(TeknikServisIslemLogu::class, 'teknik_servis_kaydi_id');
    }

    public function hatirlatmalar(): HasMany
    {
        return $this->hasMany(TeknikServisHatirlatma::class, 'teknik_servis_kaydi_id');
    }

    public function gorevAtamalari(): HasMany
    {
        return $this->hasMany(TeknikServisGorevAtamasi::class, 'teknik_servis_kaydi_id');
    }

    public function muhasebeBaglantilari(): HasMany
    {
        return $this->hasMany(TeknikServisMuhasebeBaglantisi::class, 'teknik_servis_kaydi_id');
    }

    public function tahsilatlar(): HasMany
    {
        return $this->hasMany(TeknikServisTahsilati::class, 'teknik_servis_kaydi_id');
    }

    public function alacakPlanlari(): HasMany
    {
        return $this->hasMany(AlacakPlani::class, 'kaynak_id')
            ->where('kaynak_turu', 'teknik_servis');
    }

    public function mesajLoglari(): HasMany
    {
        return $this->hasMany(TeknikServisMesajLogu::class, 'teknik_servis_kaydi_id');
    }

    public function aksesuarKayitlari(): HasMany
    {
        return $this->hasMany(TeknikServisAksesuarKaydi::class, 'teknik_servis_kaydi_id');
    }

    public function aksesuarlar(): BelongsToMany
    {
        return $this->belongsToMany(
            TeknikServisAksesuarTanimi::class,
            'teknik_servis_aksesuar_kayitlari',
            'teknik_servis_kaydi_id',
            'aksesuar_id'
        )->using(TeknikServisAksesuarKaydi::class)
            ->withPivot(['firma_id', 'adet', 'not', 'created_at', 'updated_at']);
    }
}
