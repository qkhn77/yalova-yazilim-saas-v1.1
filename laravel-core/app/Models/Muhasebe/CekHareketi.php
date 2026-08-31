<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Concerns\HasParaBirimiSnapshot;
use App\Models\Firma;
use App\Models\User;
use App\Muhasebe\Enumlar\CekHareketDurumu;
use App\Muhasebe\Enumlar\CekIslemTuru;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CekHareketi extends Model
{
    use HasFirmaTenantScope;
    use HasParaBirimiSnapshot;

    protected function paraBirimiSnapshotTarihAlani(): string
    {
        return 'islem_tarihi';
    }

    protected $table = 'cek_hareketleri';

    protected $fillable = [
        'firma_id',
        'cek_id',
        'islem_turu',
        'cari_id',
        'finans_hareket_id',
        'islem_yapan_kullanici_id',
        'islem_tarihi',
        'tutar',
        'para_birimi',
        'kur',
        'baz_para_birimi',
        'baz_tutar',
        'idempotency_key',
        'durum',
        'iptal_edilen_hareket_id',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'islem_turu' => CekIslemTuru::class,
            'durum' => CekHareketDurumu::class,
            'islem_tarihi' => 'datetime',
            'tutar' => 'decimal:2',
            'kur' => 'decimal:8',
            'baz_tutar' => 'decimal:2',
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

    public function cek(): BelongsTo
    {
        return $this->belongsTo(Cek::class, 'cek_id');
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function finansHareketi(): BelongsTo
    {
        return $this->belongsTo(FinansHareketi::class, 'finans_hareket_id');
    }

    public function islemYapanKullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'islem_yapan_kullanici_id');
    }

    public function iptalEdilenHareket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'iptal_edilen_hareket_id');
    }
}
