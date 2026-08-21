<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolYetki extends Model
{
    protected $table = 'rol_yetkileri';

    protected $fillable = [
        'rol_id',
        'yetki_id',
    ];

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function yetki(): BelongsTo
    {
        return $this->belongsTo(Yetki::class, 'yetki_id');
    }
}

