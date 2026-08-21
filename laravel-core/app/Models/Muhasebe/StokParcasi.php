<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StokParcasi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'stok_parcalari';

    protected $fillable = [
        'firma_id', 'stok_id', 'depo_id', 'ust_parca_id', 'parca_kodu', 'barkod',
        'parca_mi', 'parca_durumu', 'blok_no', 'ocak_tedarikci', 'kalite_sinifi',
        'renk_desen', 'kalinlik_cm', 'metrekare', 'plaka_no', 'parca_no',
        'uretim_tarihi', 'son_kullanma_tarihi', 'birim_maliyet', 'giren_miktar', 'kalan_miktar',
    ];

    protected function casts(): array
    {
        return [
            'uretim_tarihi' => 'date',
            'son_kullanma_tarihi' => 'date',
            'birim_maliyet' => 'decimal:8',
            'giren_miktar' => 'decimal:8',
            'kalan_miktar' => 'decimal:8',
            'depo_id' => 'integer',
            'ust_parca_id' => 'integer',
            'parca_mi' => 'boolean',
            'kalinlik_cm' => 'decimal:3',
            'metrekare' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $parca): void {
            if ($parca->exists && (bool) $parca->getOriginal('parca_mi') && $parca->isDirty([
                'firma_id', 'stok_id', 'ust_parca_id', 'parca_kodu', 'barkod', 'plaka_no', 'parca_mi',
            ])) {
                throw new IsKuraliIstisnasi('Fiziksel stok parçasının firma, stok, üst parça, kod, barkod ve plaka kimliği değiştirilemez.');
            }

            $parcaKodu = trim((string) $parca->parca_kodu);
            if ($parcaKodu === '') {
                throw new IsKuraliIstisnasi('Parça kodu boş bırakılamaz.');
            }
            $parca->parca_kodu = $parcaKodu;

            if ($parca->isDirty(['firma_id', 'stok_id', 'depo_id', 'ust_parca_id'])) {
                if (! StokKarti::withoutGlobalScopes()->whereKey((int) $parca->stok_id)->where('firma_id', (int) $parca->firma_id)->exists()) {
                    throw new IsKuraliIstisnasi('Parça, seçilen firmaya ait olmayan bir stok kartına bağlanamaz.');
                }

                if ($parca->depo_id !== null && ! Depo::withoutGlobalScopes()->whereKey((int) $parca->depo_id)->where('firma_id', (int) $parca->firma_id)->exists()) {
                    throw new IsKuraliIstisnasi('Parça, seçilen firmaya ait olmayan bir depoya bağlanamaz.');
                }

                if ($parca->ust_parca_id !== null && ! self::withoutGlobalScopes()->whereKey((int) $parca->ust_parca_id)->where('firma_id', (int) $parca->firma_id)->where('stok_id', (int) $parca->stok_id)->exists()) {
                    throw new IsKuraliIstisnasi('Fiziksel stok parçası yalnızca aynı firma ve stok kartındaki üst parçaya bağlanabilir.');
                }
            }
        });

        static::deleting(function (self $parca): void {
            if ($parca->parcalar()->withoutGlobalScopes()->exists()) {
                throw new IsKuraliIstisnasi('Alt stok parçaları bulunan üst parça silinemez.');
            }
            if ($parca->parca_mi || $parca->hareketler()->withoutGlobalScopes()->exists()) {
                throw new IsKuraliIstisnasi('Hareket veya fiziksel parça geçmişi olan stok parçası silinemez.');
            }
        });
    }

    /** @param Builder<static> $query */
    public function scopeFirma(Builder $query, int $firmaId): Builder
    {
        return $query->where('firma_id', $firmaId);
    }

    public function firma(): BelongsTo { return $this->belongsTo(Firma::class, 'firma_id'); }
    public function stokKarti(): BelongsTo { return $this->belongsTo(StokKarti::class, 'stok_id'); }
    public function depo(): BelongsTo { return $this->belongsTo(Depo::class, 'depo_id'); }
    public function ustParca(): BelongsTo { return $this->belongsTo(self::class, 'ust_parca_id'); }
    public function parcalar(): HasMany { return $this->hasMany(self::class, 'ust_parca_id'); }
    public function hareketler(): HasMany { return $this->hasMany(StokHareketiParcasi::class, 'stok_parcasi_id'); }
    public function olcuBakiyeleri(): HasMany { return $this->hasMany(StokOlcuBakiyesi::class, 'stok_parcasi_id'); }
}
