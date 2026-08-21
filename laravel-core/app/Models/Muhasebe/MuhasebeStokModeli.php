<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasMuhasebeTanimKaydi;
use App\Models\Concerns\TanimGorunurFirmaIle;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MuhasebeStokModeli extends Model
{
    use HasMuhasebeTanimKaydi;
    use SoftDeletes;
    use TanimGorunurFirmaIle;

    protected $table = 'muhasebe_modeller';

    protected $fillable = [
        'firma_id',
        'marka_id',
        'kod',
        'ad',
        'aktif_mi',
        'is_sabit',
    ];

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
            'is_sabit' => 'boolean',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function marka(): BelongsTo
    {
        return $this->belongsTo(MuhasebeMarka::class, 'marka_id');
    }
}
