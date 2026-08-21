<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CariHareketEslesmesi extends Model
{
    use HasFirmaTenantScope;

    public $timestamps = false;

    protected $table = 'cari_hareket_eslesmeleri';

    protected $fillable = [
        'firma_id',
        'borc_hareket_id',
        'alacak_hareket_id',
        'eslesen_tutar',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'eslesen_tutar' => 'decimal:8',
            'created_at' => 'datetime',
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

    public function borcHareket(): BelongsTo
    {
        return $this->belongsTo(CariHareketi::class, 'borc_hareket_id');
    }

    public function alacakHareket(): BelongsTo
    {
        return $this->belongsTo(CariHareketi::class, 'alacak_hareket_id');
    }
}
