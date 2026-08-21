<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasMuhasebeTanimKaydi;
use App\Models\Concerns\TanimGorunurFirmaIle;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VergiOrani extends Model
{
    use HasMuhasebeTanimKaydi;
    use SoftDeletes;
    use TanimGorunurFirmaIle;

    protected $table = 'muhasebe_vergi_oranlari';

    protected $fillable = [
        'firma_id',
        'kod',
        'ad',
        'oran',
        'aktif_mi',
        'is_sabit',
    ];

    protected function casts(): array
    {
        return [
            'oran' => 'decimal:4',
            'aktif_mi' => 'boolean',
            'is_sabit' => 'boolean',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }
}
