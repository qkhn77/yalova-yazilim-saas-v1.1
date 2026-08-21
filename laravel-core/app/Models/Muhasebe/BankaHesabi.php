<?php

namespace App\Models\Muhasebe;

use App\Models\Firma;
use App\Muhasebe\Enumlar\HesapDurumu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankaHesabi extends Model
{
    use SoftDeletes;

    protected $table = 'banka_hesaplari';

    protected $fillable = [
        'firma_id',
        'kod',
        'ad',
        'hesap_sahibi_unvan',
        'banka_adi',
        'sube',
        'hesap_no',
        'iban',
        'para_birimi',
        'aciklama',
        'durum',
    ];

    protected function casts(): array
    {
        return [
            'durum' => HesapDurumu::class,
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

    public function bankaHareketleri(): HasMany
    {
        return $this->hasMany(BankaHareketi::class, 'banka_hesap_id');
    }
}
