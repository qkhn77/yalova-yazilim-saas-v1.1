<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Concerns\HasParaBirimiSnapshot;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlacakPlanTaksiti extends Model
{
    use HasFirmaTenantScope;
    use HasParaBirimiSnapshot;

    protected function paraBirimiSnapshotTarihAlani(): string
    {
        return 'vade_tarihi';
    }
    use SoftDeletes;

    protected $table = 'muhasebe_alacak_plan_taksitleri';

    protected $fillable = [
        'firma_id',
        'alacak_plan_id',
        'cari_id',
        'cari_hareket_id',
        'sira_no',
        'vade_tarihi',
        'tutar',
        'odenen_tutar',
        'kalan_tutar',
        'para_birimi',
        'kur',
        'baz_para_birimi',
        'baz_tutar',
        'son_tahsilat_tarihi',
        'durum',
    ];

    protected function casts(): array
    {
        return [
            'vade_tarihi' => 'date',
            'tutar' => 'decimal:2',
            'odenen_tutar' => 'decimal:2',
            'kalan_tutar' => 'decimal:2',
            'kur' => 'decimal:8',
            'baz_tutar' => 'decimal:2',
            'son_tahsilat_tarihi' => 'datetime',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AlacakPlani::class, 'alacak_plan_id');
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function cariHareketi(): BelongsTo
    {
        return $this->belongsTo(CariHareketi::class, 'cari_hareket_id');
    }

    public function tahsilatEslesmeleri(): HasMany
    {
        return $this->hasMany(AlacakTahsilatEslesmesi::class, 'alacak_plan_taksiti_id');
    }

    public function takipNotlari(): HasMany
    {
        return $this->hasMany(AlacakTakipNotu::class, 'alacak_plan_taksiti_id');
    }
}
