<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeklifKalemi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'teklif_kalemleri';

    private static int $toplamGuncellemeAskida = 0;

    protected $fillable = [
        'firma_id',
        'teklif_id',
        'stok_id',
        'kalem_tipi',
        'hizmet_mi',
        'aciklama',
        'birim',
        'miktar',
        'birim_fiyat',
        'indirim_orani',
        'kdv_orani',
        'net_tutar',
        'kdv_tutari',
        'toplam',
        'para_birimi',
        'kaynak_para_birimi',
        'kaynak_birim_fiyat',
        'ozel_fiyat_mi',
        'fiyat_uyari',
        'kaynak_verisi',
    ];

    protected function casts(): array
    {
        return [
            'hizmet_mi' => 'boolean',
            'miktar' => 'decimal:8',
            'birim_fiyat' => 'decimal:2',
            'kaynak_birim_fiyat' => 'decimal:8',
            'indirim_orani' => 'decimal:2',
            'kdv_orani' => 'decimal:2',
            'net_tutar' => 'decimal:2',
            'kdv_tutari' => 'decimal:2',
            'toplam' => 'decimal:2',
            'ozel_fiyat_mi' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $kalem): void {
            if ((int) ($kalem->teklif_id ?? 0) < 1) {
                return;
            }

            $teklif = Teklif::tenantScopeOlmadan(fn () => Teklif::query()->find($kalem->teklif_id));

            if ($teklif) {
                $kalem->firma_id = (int) $teklif->firma_id;
            }
        });

        static::saved(fn (self $kalem): null => static::teklifToplamlariniGuncelle($kalem));
        static::deleted(fn (self $kalem): null => static::teklifToplamlariniGuncelle($kalem));
    }

    public function teklif(): BelongsTo
    {
        return $this->belongsTo(Teklif::class, 'teklif_id');
    }

    public function stokKarti(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_id');
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $islem
     * @return TReturn
     */
    public static function toplamGuncellemeleriniAskidaTut(callable $islem): mixed
    {
        self::$toplamGuncellemeAskida++;

        try {
            return $islem();
        } finally {
            self::$toplamGuncellemeAskida--;
        }
    }

    private static function teklifToplamlariniGuncelle(self $kalem): null
    {
        if (self::$toplamGuncellemeAskida > 0) {
            return null;
        }

        $teklif = Teklif::tenantScopeOlmadan(fn () => $kalem->teklif()->first());
        $teklif?->toplamlariniKalemlerdenGuncelle();

        return null;
    }
}
