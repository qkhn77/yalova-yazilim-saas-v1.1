<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasMuhasebeTanimKaydi;
use App\Models\Concerns\TanimGorunurFirmaIle;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StokKategorisi extends Model
{
    use HasMuhasebeTanimKaydi;
    use SoftDeletes;
    use TanimGorunurFirmaIle;

    protected $table = 'stok_kategorileri';

    protected $fillable = [
        'firma_id',
        'parent_id',
        'kod',
        'ad',
        'slug',
        'aciklama',
        'aktif_mi',
        'is_sabit',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $slug = trim((string) ($model->slug ?? ''));
            if ($slug === '') {
                $slug = Str::slug((string) ($model->ad ?? ''));
            }
            if ($slug === '') {
                $slug = Str::slug((string) ($model->kod ?? ''));
            }
            if ($slug === '') {
                $slug = 'kategori';
            }

            $model->slug = static::benzersizSlugUret(
                slug: $slug,
                firmaKapsami: (int) ($model->tanim_firma_kapsami ?? ($model->firma_id ?? 0)),
                haricId: $model->exists ? (int) $model->getKey() : null
            );
        });
    }

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
            'is_sabit' => 'boolean',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function stokKartlari(): HasMany
    {
        return $this->hasMany(StokKarti::class, 'kategori_id');
    }

    private static function benzersizSlugUret(string $slug, int $firmaKapsami, ?int $haricId = null): string
    {
        $aday = $slug;
        $i = 1;

        while (static::slugVarMi($aday, $firmaKapsami, $haricId)) {
            $aday = $slug.'-'.$i;
            $i++;
        }

        return $aday;
    }

    private static function slugVarMi(string $slug, int $firmaKapsami, ?int $haricId): bool
    {
        return static::tenantScopeOlmadan(function () use ($slug, $firmaKapsami, $haricId): bool {
            $q = static::query()
                ->where('tanim_firma_kapsami', $firmaKapsami)
                ->where('slug', $slug);

            if ($haricId !== null) {
                $q->whereKeyNot($haricId);
            }

            return $q->exists();
        });
    }
}
