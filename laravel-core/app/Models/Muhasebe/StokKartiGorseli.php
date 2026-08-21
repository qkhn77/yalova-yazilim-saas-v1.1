<?php

namespace App\Models\Muhasebe;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokKartiGorseli extends Model
{
    protected $table = 'stok_karti_gorselleri';

    protected $fillable = [
        'stok_karti_id',
        'dosya_yolu',
        'alt_metin',
        'sira',
        'kapak_mi',
        'aktif_mi',
    ];

    protected function casts(): array
    {
        return [
            'sira' => 'integer',
            'kapak_mi' => 'boolean',
            'aktif_mi' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $image): void {
            if ($image->stok_karti_id && $image->kapak_mi) {
                static::query()
                    ->where('stok_karti_id', $image->stok_karti_id)
                    ->when($image->exists, fn ($query) => $query->whereKeyNot($image->getKey()))
                    ->update(['kapak_mi' => false]);
            }
        });

        $normalize = static function (self $image): void {
            self::normalizeCoverForProduct((int) $image->stok_karti_id);
        };

        static::saved($normalize);
        static::deleted($normalize);
    }

    public function stokKarti(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_karti_id');
    }

    public function getUrlAttribute(): ?string
    {
        $path = trim((string) $this->dosya_yolu);

        if ($path === '') {
            return null;
        }

        return asset('storage/'.ltrim(str_replace('\\', '/', $path), '/'));
    }

    public static function normalizeCoverForProduct(int $stokKartiId): void
    {
        if ($stokKartiId < 1) {
            return;
        }

        $images = static::query()
            ->where('stok_karti_id', $stokKartiId)
            ->where('aktif_mi', true)
            ->orderByDesc('kapak_mi')
            ->orderBy('sira')
            ->orderBy('id')
            ->get();

        if ($images->isEmpty()) {
            return;
        }

        $coverId = optional($images->firstWhere('kapak_mi', true))->getKey() ?: $images->first()->getKey();

        static::query()
            ->where('stok_karti_id', $stokKartiId)
            ->update(['kapak_mi' => false]);

        static::query()
            ->whereKey($coverId)
            ->update(['kapak_mi' => true]);
    }
}
