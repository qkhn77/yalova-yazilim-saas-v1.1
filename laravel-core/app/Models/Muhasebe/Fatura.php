<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Proje\IsletmeProjesi;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fatura extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    /**
     * UI / doğrulama: fatura kaydedilirken bu varsayılanlar ile tutarlı kalem hesapları beklenir.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'genel_indirim_tutari' => 0,
        'kdv_dahil_fiyatlandirma_mi' => false,
    ];

    protected $table = 'faturalar';

    protected $fillable = [
        'firma_id',
        'isletme_proje_id',
        'cari_id',
        'belge_no',
        'irsaliye_no',
        'seri',
        'sira_no',
        'tur',
        'durum',
        'fatura_no',
        'odeme_durumu',
        'tarih',
        'vade_tarihi',
        'doviz_kuru',
        'ara_toplam',
        'baz_ara_toplam',
        'toplam_indirim',
        'baz_toplam_indirim',
        'kdv_toplam',
        'baz_kdv_toplam',
        'tevkifat_orani',
        'genel_toplam',
        'baz_genel_toplam',
        'odenecek_tutar',
        'baz_odenecek_tutar',
        'odendi_tutari',
        'baz_odendi_tutari',
        'acik_tutar',
        'baz_acik_tutar',
        'genel_indirim_tutari',
        'kdv_dahil_fiyatlandirma_mi',
        'bagli_fatura_id',
        'para_birimi',
        'baz_para_birimi',
        'aciklama',
        'notlar',
        'iptal_nedeni',
        'iptal_edildi_at',
        'iptal_eden_kullanici_id',
        'kaynak_tipi',
        'e_belge_tipi',
        'e_belge_uuid',
        'e_belge_durumu',
        'e_belge_saglayici',
        'e_belge_saglayici_belge_id',
        'e_belge_hash',
        'e_belge_gonderildi_at',
        'e_belge_yanit_kodu',
        'e_belge_yanit_mesaji',
        'e_belge_son_hata',
        'islem_tipi',
        'islem_no',
    ];

    protected function casts(): array
    {
        return [
            'tur' => FaturaTuru::class,
            'durum' => FaturaDurumu::class,
            'tarih' => 'datetime',
            'vade_tarihi' => 'date',
            'doviz_kuru' => 'decimal:8',
            'ara_toplam' => 'decimal:8',
            'baz_ara_toplam' => 'decimal:8',
            'toplam_indirim' => 'decimal:8',
            'baz_toplam_indirim' => 'decimal:8',
            'kdv_toplam' => 'decimal:8',
            'baz_kdv_toplam' => 'decimal:8',
            'tevkifat_orani' => 'decimal:2',
            'genel_toplam' => 'decimal:8',
            'baz_genel_toplam' => 'decimal:8',
            'odenecek_tutar' => 'decimal:8',
            'baz_odenecek_tutar' => 'decimal:8',
            'odendi_tutari' => 'decimal:8',
            'baz_odendi_tutari' => 'decimal:8',
            'acik_tutar' => 'decimal:8',
            'baz_acik_tutar' => 'decimal:8',
            'genel_indirim_tutari' => 'decimal:8',
            'kdv_dahil_fiyatlandirma_mi' => 'boolean',
            'e_belge_gonderildi_at' => 'datetime',
            'iptal_edildi_at' => 'datetime',
            'islem_no' => 'integer',
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

    public function isletmeProjesi(): BelongsTo
    {
        return $this->belongsTo(IsletmeProjesi::class, 'isletme_proje_id');
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function bagliFatura(): BelongsTo
    {
        return $this->belongsTo(self::class, 'bagli_fatura_id');
    }

    public function kalemler(): HasMany
    {
        return $this->hasMany(FaturaKalemi::class, 'fatura_id');
    }

    /**
     * Onay transaction'ında, zaten firma erişimi doğrulanmış faturanın
     * kalemlerini aktif HTTP tenant scope'undan bağımsız okur.
     */
    public function onayKalemleri(): HasMany
    {
        return $this->hasMany(FaturaKalemi::class, 'fatura_id')->withoutGlobalScopes();
    }

    public function finansKapatmalari(): HasMany
    {
        return $this->hasMany(FaturaFinansKapama::class, 'fatura_id');
    }

    public function masrafDagitimlari(): HasMany
    {
        return $this->hasMany(MasrafFaturaDagitimi::class, 'fatura_id');
    }

    /**
     * @return HasMany<CariHareketi, $this>
     */
    public function cariHareketleri(): HasMany
    {
        return $this->hasMany(CariHareketi::class, 'belge_id')
            ->where('cari_hareketleri.belge_turu', CariHareketBelgeTuru::Fatura->value)
            ->where('cari_hareketleri.firma_id', $this->firma_id);
    }

    /**
     * @return HasMany<StokHareketi, $this>
     */
    public function stokHareketleri(): HasMany
    {
        return $this->hasMany(StokHareketi::class, 'belge_id')
            ->where('stok_hareketleri.belge_turu', StokBelgeTuru::Fatura->value)
            ->where('stok_hareketleri.firma_id', $this->firma_id);
    }
}
