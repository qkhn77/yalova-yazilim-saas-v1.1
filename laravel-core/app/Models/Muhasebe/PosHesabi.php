<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\PosTipi;
use App\Muhasebe\Enumlar\SaglayiciTipi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PosHesabi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (PosHesabi $pos): void {
            $s = trim((string) $pos->saglayici_adi);
            if ($s !== '') {
                $pos->pos_saglayici = $s;
            } else {
                $b = trim((string) $pos->banka_adi);
                if ($b !== '') {
                    $pos->pos_saglayici = $b;
                }
            }
        });
    }

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            if ($this->varsayilan_mi) {
                static::query()
                    ->withoutGlobalScopes()
                    ->where('firma_id', $this->firma_id)
                    ->lockForUpdate()
                    ->get();
            }

            $sonuc = parent::save($options);

            if ($sonuc && $this->varsayilan_mi) {
                static::query()
                    ->withoutGlobalScopes()
                    ->where('firma_id', $this->firma_id)
                    ->whereKeyNot($this->getKey())
                    ->update(['varsayilan_mi' => false]);
            }

            return $sonuc;
        });
    }

    protected $table = 'pos_hesaplari';

    protected $fillable = [
        'firma_id',
        'kod',
        'ad',
        'pos_tipi',
        'saglayici_tipi',
        'banka_hesabi_id',
        'banka_adi',
        'saglayici_adi',
        'terminal_no',
        'uye_isyeri_no',
        'magaza_kodu',
        'sanal_pos_no',
        'pos_saglayici',
        'para_birimi',
        'komisyon_orani',
        'sabit_komisyon_tutari',
        'bloke_gun_sayisi',
        'valor_gun_sayisi',
        'erken_odeme_destegi_var_mi',
        'taksit_destegi_var_mi',
        'maksimum_taksit_sayisi',
        'tek_cekim_destegi_var_mi',
        'varsayilan_mi',
        'aciklama',
        'durum',
    ];

    protected function casts(): array
    {
        return [
            'pos_tipi' => PosTipi::class,
            'saglayici_tipi' => SaglayiciTipi::class,
            'durum' => HesapDurumu::class,
            'erken_odeme_destegi_var_mi' => 'boolean',
            'taksit_destegi_var_mi' => 'boolean',
            'tek_cekim_destegi_var_mi' => 'boolean',
            'varsayilan_mi' => 'boolean',
            'komisyon_orani' => 'decimal:4',
            'sabit_komisyon_tutari' => 'decimal:2',
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

    public function bankaHesabi(): BelongsTo
    {
        return $this->belongsTo(BankaHesabi::class, 'banka_hesabi_id');
    }

    public function posHareketleri(): HasMany
    {
        return $this->hasMany(PosHareketi::class, 'pos_hesap_id');
    }

    /**
     * Liste ekranında banka veya sağlayıcı adı (hangisi doluysa).
     */
    public function bankaVeyaSaglayiciGorunenAdi(): string
    {
        $banka = trim((string) $this->banka_adi);
        if ($banka !== '') {
            return $banka;
        }

        $sag = trim((string) $this->saglayici_adi);
        if ($sag !== '') {
            return $sag;
        }

        $eski = trim((string) $this->pos_saglayici);

        return $eski !== '' ? $eski : '—';
    }
}
