<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Concerns\HasParaBirimiSnapshot;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlacakTahsilatEslesmesi extends Model
{
    use HasFirmaTenantScope;
    use HasParaBirimiSnapshot;

    protected function paraBirimiSnapshotTarihAlani(): string
    {
        return 'tarih';
    }

    protected $table = 'muhasebe_alacak_tahsilat_eslesmeleri';

    protected $fillable = [
        'firma_id',
        'alacak_plan_id',
        'alacak_plan_taksiti_id',
        'finans_hareketi_id',
        'tutar',
        'para_birimi',
        'kur',
        'baz_para_birimi',
        'baz_tutar',
        'tarih',
    ];

    protected function casts(): array
    {
        return [
            'tutar' => 'decimal:2',
            'kur' => 'decimal:8',
            'baz_tutar' => 'decimal:2',
            'tarih' => 'datetime',
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

    public function taksit(): BelongsTo
    {
        return $this->belongsTo(AlacakPlanTaksiti::class, 'alacak_plan_taksiti_id');
    }

    public function finansHareketi(): BelongsTo
    {
        return $this->belongsTo(FinansHareketi::class, 'finans_hareketi_id');
    }
}
