<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Concerns\HasParaBirimiSnapshot;
use App\Models\Firma;
use App\Models\User;
use App\Muhasebe\Enumlar\SenetDurumu;
use App\Muhasebe\Enumlar\SenetTuru;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Senet extends Model
{
    use HasFirmaTenantScope;
    use HasParaBirimiSnapshot;

    protected $table = 'senetler';

    protected $fillable = [
        'firma_id',
        'turu',
        'durum',
        'senet_no',
        'duzenleme_yeri',
        'odeme_yeri',
        'avalist_adi',
        'tutar',
        'para_birimi',
        'kur',
        'baz_para_birimi',
        'baz_tutar',
        'duzenleme_tarihi',
        'vade_tarihi',
        'sorumlu_kullanici_id',
        'olusturan_kullanici_id',
        'kapatma_kullanici_id',
        'odeme_finans_hareket_id',
        'kapanma_tarihi',
        'kapanma_sekli',
        'kapatma_aciklama',
        'on_gorsel_yolu',
        'arka_gorsel_yolu',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'turu' => SenetTuru::class,
            'durum' => SenetDurumu::class,
            'tutar' => 'decimal:2',
            'kur' => 'decimal:8',
            'baz_tutar' => 'decimal:2',
            'duzenleme_tarihi' => 'date',
            'vade_tarihi' => 'date',
            'kapanma_tarihi' => 'datetime',
        ];
    }

    /** @param Builder<static> $sorgu */
    public function scopeFirma(Builder $sorgu, int $firmaId): Builder
    {
        return $sorgu->where('firma_id', $firmaId);
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function sorumluKullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorumlu_kullanici_id');
    }

    public function olusturanKullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_kullanici_id');
    }

    public function kapatmaKullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kapatma_kullanici_id');
    }

    public function odemeFinansHareketi(): BelongsTo
    {
        return $this->belongsTo(FinansHareketi::class, 'odeme_finans_hareket_id');
    }

    public function hareketleri(): HasMany
    {
        return $this->hasMany(SenetHareketi::class, 'senet_id');
    }

    public function girisHareketi(): HasOne
    {
        return $this->hasOne(SenetHareketi::class, 'senet_id')->where('islem_turu', 'giris');
    }

    public function cikisHareketi(): HasOne
    {
        return $this->hasOne(SenetHareketi::class, 'senet_id')->where('islem_turu', 'cikis');
    }

    public function odemeHareketi(): HasOne
    {
        return $this->hasOne(SenetHareketi::class, 'senet_id')->where('islem_turu', 'odeme');
    }

    public function tahsilatHareketi(): HasOne
    {
        return $this->hasOne(SenetHareketi::class, 'senet_id')->where('islem_turu', 'tahsilat');
    }
}
