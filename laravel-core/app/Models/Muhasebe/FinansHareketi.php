<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Ecommerce\Siparis;
use App\Models\Firma;
use App\Models\Proje\IsletmeProjesi;
use App\Models\TeknikServis\TeknikServisMuhasebeBaglantisi;
use App\Models\TeknikServis\TeknikServisTahsilati;
use App\Models\User;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinansHareketi extends Model
{
    use HasFirmaTenantScope;

    protected static function booted(): void
    {
        static::deleting(function (self $finans): void {
            // Kiracı scope'u olmadan kontrol: PHPUnit / konsol bağlamında yanlışlıkla
            // "kapama yok" sanılıp silmeyi önlemek için finans_hareket_id üzerinden bakılır.
            if (FaturaFinansKapama::query()->withoutGlobalScopes()
                ->where('finans_hareket_id', $finans->getKey())
                ->exists()) {
                throw new \RuntimeException('Faturaya bağlı finans hareketi silinemez; önce ters kayıt akışı kullanılmalıdır.');
            }
        });
    }

    protected $table = 'finans_hareketleri';

    protected $fillable = [
        'firma_id',
        'isletme_proje_id',
        'islem_yapan_kullanici_id',
        'islem_kaynagi',
        'audit_ip',
        'tur',
        'tarih',
        'vade_tarihi',
        'tutar',
        'baz_tutar',
        'brut_tutar',
        'pos_komisyon_tutari',
        'pos_komisyon_orani_yuzde',
        'para_birimi',
        'baz_para_birimi',
        'kur',
        'cari_id',
        'aciklama',
        'ek_aciklama',
        'referans_turu',
        'referans_id',
        'durum',
        'iptal_edilen_hareket_id',
        'duzeltme_kaynagi_id',
        'kullanilan_tutar',
        'avans_tutar',
    ];

    protected function casts(): array
    {
        return [
            'tur' => FinansHareketTuru::class,
            'durum' => FinansHareketDurumu::class,
            'tarih' => 'datetime',
            'vade_tarihi' => 'date',
            'tutar' => 'decimal:2',
            'baz_tutar' => 'decimal:2',
            'brut_tutar' => 'decimal:2',
            'pos_komisyon_tutari' => 'decimal:2',
            'pos_komisyon_orani_yuzde' => 'decimal:4',
            'kullanilan_tutar' => 'decimal:2',
            'avans_tutar' => 'decimal:2',
            'kur' => 'decimal:8',
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

    public function islemYapanKullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'islem_yapan_kullanici_id');
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function kasaHareketleri(): HasMany
    {
        return $this->hasMany(KasaHareketi::class, 'finans_hareket_id');
    }

    public function bankaHareketleri(): HasMany
    {
        return $this->hasMany(BankaHareketi::class, 'finans_hareket_id');
    }

    public function posHareketleri(): HasMany
    {
        return $this->hasMany(PosHareketi::class, 'finans_hareket_id');
    }

    public function iptalEdilenHareket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'iptal_edilen_hareket_id');
    }

    public function duzeltmeKaynagi(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duzeltme_kaynagi_id');
    }

    public function faturaKapatmalari(): HasMany
    {
        return $this->hasMany(FaturaFinansKapama::class, 'finans_hareket_id');
    }

    public function referansFaturasi(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'referans_id');
    }

    public function teknikServisTahsilatlari(): HasMany
    {
        return $this->hasMany(TeknikServisTahsilati::class, 'finans_hareketi_id');
    }

    public function iptalTeknikServisTahsilatlari(): HasMany
    {
        return $this->hasMany(TeknikServisTahsilati::class, 'iptal_finans_hareketi_id');
    }

    public function teknikServisMuhasebeBaglantilari(): HasMany
    {
        return $this->hasMany(TeknikServisMuhasebeBaglantisi::class, 'finans_hareketi_id');
    }

    public function getModulEtiketiAttribute(): string
    {
        if ($modul = $this->teknikServisModulu()) {
            return $modul;
        }

        $referansTuru = strtolower(trim((string) ($this->referans_turu ?? '')));

        if (in_array($referansTuru, ['barkodlu_satis', 'barkodlu_satis_iade'], true)) {
            return 'Barkodlu Satış';
        }

        if ($referansTuru === Siparis::REFERANS_TURU_FINANS) {
            return 'E-Ticaret';
        }

        if (in_array($referansTuru, ['personel_avans', 'personel_maas', 'personel_maas_odeme'], true)) {
            return 'Personel Takip';
        }

        if ($referansTuru === 'cek') {
            return 'Çek';
        }

        if ($referansTuru === 'senet') {
            return 'Senet';
        }

        if (in_array($referansTuru, ['restoran_adisyon', 'restoran_adisyon_iade'], true)) {
            return 'Restoran';
        }

        if ($modul = $this->faturaModulu()) {
            return $modul;
        }

        if ($modul = $this->tersKayitModulu()) {
            return $modul;
        }

        return 'Muhasebe';
    }

    private function teknikServisModulu(): ?string
    {
        $referansTuru = strtolower(trim((string) ($this->referans_turu ?? '')));

        if ($referansTuru === 'teknik_servis') {
            return 'Teknik Servis';
        }

        if ($this->boolAlanindanVeyaIliskiden('teknik_servis_tahsilat_kaynagi', 'teknikServisTahsilatlari')) {
            return 'Teknik Servis';
        }

        if ($this->boolAlanindanVeyaIliskiden('teknik_servis_iptal_tahsilat_kaynagi', 'iptalTeknikServisTahsilatlari')) {
            return 'Teknik Servis';
        }

        if ($this->boolAlanindanVeyaIliskiden('teknik_servis_baglanti_kaynagi', 'teknikServisMuhasebeBaglantilari')) {
            return 'Teknik Servis';
        }

        return null;
    }

    private function faturaModulu(): ?string
    {
        $referansTuru = strtolower(trim((string) ($this->referans_turu ?? '')));

        if ($referansTuru !== 'fatura' || (int) ($this->referans_id ?? 0) < 1) {
            return null;
        }

        $fatura = $this->relationLoaded('referansFaturasi')
            ? $this->referansFaturasi
            : $this->referansFaturasi()->select(['id', 'kaynak_tipi'])->first();

        return match (strtolower(trim((string) ($fatura?->kaynak_tipi ?? '')))) {
            'teknik_servis' => 'Teknik Servis',
            'ecommerce_siparis' => 'E-Ticaret',
            default => 'Fatura',
        };
    }

    private function tersKayitModulu(): ?string
    {
        $referansTuru = strtolower(trim((string) ($this->referans_turu ?? '')));

        if ($referansTuru !== 'finans_hareketi' && (int) ($this->iptal_edilen_hareket_id ?? 0) < 1) {
            return null;
        }

        $orijinalHareket = $this->relationLoaded('iptalEdilenHareket')
            ? $this->iptalEdilenHareket
            : $this->iptalEdilenHareket()
                ->with(['referansFaturasi:id,kaynak_tipi'])
                ->first();

        return $orijinalHareket?->modul_etiketi;
    }

    private function boolAlanindanVeyaIliskiden(string $alan, string $iliski): bool
    {
        if (array_key_exists($alan, $this->attributes)) {
            return (bool) $this->getAttribute($alan);
        }

        if ($this->relationLoaded($iliski)) {
            return $this->{$iliski}->isNotEmpty();
        }

        return $this->{$iliski}()->exists();
    }
}
