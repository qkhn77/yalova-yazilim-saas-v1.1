<?php

namespace App\Models\Masraf;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\MasrafKategorisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasrafButcesi extends Model
{
    use HasFirmaTenantScope;

    public const DURUM_AKTIF = 'aktif';

    public const DURUM_KAPALI = 'kapali';

    protected $table = 'masraf_butceleri';

    protected $fillable = [
        'firma_id',
        'masraf_kategorisi_id',
        'donem_baslangic',
        'donem_bitis',
        'butce_tutari',
        'para_birimi',
        'durum',
        'notlar',
    ];

    protected function casts(): array
    {
        return [
            'donem_baslangic' => 'date',
            'donem_bitis' => 'date',
            'butce_tutari' => 'decimal:2',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(MasrafKategorisi::class, 'masraf_kategorisi_id');
    }
}
