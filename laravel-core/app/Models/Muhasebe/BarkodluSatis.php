<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarkodluSatis extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'muhasebe_barkodlu_satislar';

    protected $fillable = [
        'firma_id',
        'satis_no',
        'satis_tarihi',
        'cari_id',
        'odeme_tipi',
        'para_birimi',
        'ara_toplam',
        'iskonto_toplami',
        'kdv_toplami',
        'genel_toplam',
        'durum',
        'iptal_tarihi',
        'iptal_nedeni',
        'iptal_eden_id',
        'not',
        'olusturan_id',
    ];

    protected function casts(): array
    {
        return [
            'satis_tarihi' => 'datetime',
            'ara_toplam' => 'decimal:2',
            'iskonto_toplami' => 'decimal:2',
            'kdv_toplami' => 'decimal:2',
            'genel_toplam' => 'decimal:2',
            'iptal_tarihi' => 'datetime',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function kalemler(): HasMany
    {
        return $this->hasMany(BarkodluSatisKalemi::class, 'satis_id');
    }

    public function iadeler(): HasMany
    {
        return $this->hasMany(BarkodluSatisIade::class, 'satis_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'olusturan_id');
    }

    public function iptalEden(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iptal_eden_id');
    }

    public function finansHareketleri(): HasMany
    {
        return $this->hasMany(FinansHareketi::class, 'referans_id')
            ->where('referans_turu', 'barkodlu_satis');
    }

    public function alacakPlanlari(): HasMany
    {
        return $this->hasMany(AlacakPlani::class, 'kaynak_id')
            ->where('kaynak_turu', 'barkodlu_satis');
    }
}
