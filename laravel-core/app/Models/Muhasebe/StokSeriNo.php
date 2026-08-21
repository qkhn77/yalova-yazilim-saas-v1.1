<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StokSeriNo extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'stok_seri_nolari';

    protected $fillable = [
        'firma_id', 'stok_id', 'depo_id', 'seri_no', 'barkod', 'durum', 'birim_maliyet',
        'garanti_baslangic_tarihi', 'garanti_bitis_tarihi',
    ];

    protected function casts(): array
    {
        return [
            'depo_id' => 'integer',
            'birim_maliyet' => 'decimal:8',
            'garanti_baslangic_tarihi' => 'date',
            'garanti_bitis_tarihi' => 'date',
        ];
    }

    /** @param Builder<static> $query */
    public function scopeFirma(Builder $query, int $firmaId): Builder { return $query->where('firma_id', $firmaId); }
    public function firma(): BelongsTo { return $this->belongsTo(Firma::class, 'firma_id'); }
    public function stokKarti(): BelongsTo { return $this->belongsTo(StokKarti::class, 'stok_id'); }
    public function depo(): BelongsTo { return $this->belongsTo(Depo::class, 'depo_id'); }
    public function hareketler(): HasMany { return $this->hasMany(StokHareketiSeri::class, 'stok_seri_no_id'); }
}
