<?php

namespace App\Models;

use App\Traits\HasFirma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FirmaAboneligi extends Model
{
    use HasFirma;
    use SoftDeletes;

    protected $table = 'firma_abonelikleri';

    protected $fillable = [
        'firma_id',
        'plan_id',
        'durum',
        'baslangic_tarihi',
        'bitis_tarihi',
        'otomatik_yenileme',
    ];

    protected $casts = [
        'baslangic_tarihi' => 'date',
        'bitis_tarihi' => 'date',
        'otomatik_yenileme' => 'boolean',
    ];

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}

