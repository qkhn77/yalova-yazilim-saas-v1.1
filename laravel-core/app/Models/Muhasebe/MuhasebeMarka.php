<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasMuhasebeTanimKaydi;
use App\Models\Concerns\TanimGorunurFirmaIle;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MuhasebeMarka extends Model
{
    use HasMuhasebeTanimKaydi;
    use SoftDeletes;
    use TanimGorunurFirmaIle;

    protected $table = 'muhasebe_markalar';

    protected $fillable = [
        'firma_id',
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

    public function modeller(): HasMany
    {
        return $this->hasMany(MuhasebeStokModeli::class, 'marka_id');
    }
}
