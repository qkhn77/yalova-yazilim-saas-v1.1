<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Concerns\HasParaBirimiSnapshot;
use App\Models\Firma;
use App\Models\User;
use App\Muhasebe\Enumlar\CekDurumu;
use App\Muhasebe\Enumlar\CekTuru;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cek extends Model
{
    use HasFirmaTenantScope;
    use HasParaBirimiSnapshot;

    protected $table = 'cekler';

    protected $fillable = [
        'firma_id',
        'turu',
        'durum',
        'cek_no',
        'banka_adi',
        'sube_adi',
        'tutar',
        'para_birimi',
        'kur',
        'baz_para_birimi',
        'baz_tutar',
        'keside_tarihi',
        'vade_tarihi',
        'sorumlu_kullanici_id',
        'olusturan_kullanici_id',
        'aciklama',
        'on_gorsel_yolu',
        'arka_gorsel_yolu',
    ];

    protected function casts(): array
    {
        return [
            'turu' => CekTuru::class,
            'durum' => CekDurumu::class,
            'tutar' => 'decimal:2',
            'kur' => 'decimal:8',
            'baz_tutar' => 'decimal:2',
            'keside_tarihi' => 'date',
            'vade_tarihi' => 'date',
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

    public function hareketleri(): HasMany
    {
        return $this->hasMany(CekHareketi::class, 'cek_id');
    }

    public function girisHareketi(): HasOne
    {
        return $this->hasOne(CekHareketi::class, 'cek_id')->where('islem_turu', 'giris');
    }

    public function cikisHareketi(): HasOne
    {
        return $this->hasOne(CekHareketi::class, 'cek_id')->where('islem_turu', 'cikis');
    }
}
