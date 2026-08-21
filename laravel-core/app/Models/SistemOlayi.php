<?php

namespace App\Models;

use App\Models\Concerns\HasFirmaTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SistemOlayi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'sistem_olaylari';

    protected $fillable = [
        'tip',
        'seviye',
        'mesaj',
        'context',
        'firma_id',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }
}
