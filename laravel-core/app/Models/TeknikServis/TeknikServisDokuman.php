<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeknikServisDokuman extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'teknik_servis_dokumanlari';

    protected $fillable = [
        'firma_id',
        'teknik_servis_kaydi_id',
        'dosya_tipi',
        'disk',
        'yol',
        'orijinal_ad',
        'mime_type',
        'boyut',
        'yukleyen_id',
    ];

    protected function casts(): array
    {
        return [
            'boyut' => 'integer',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function teknikServisKaydi(): BelongsTo
    {
        return $this->belongsTo(TeknikServisKaydi::class, 'teknik_servis_kaydi_id');
    }

    public function yukleyen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'yukleyen_id');
    }
}
