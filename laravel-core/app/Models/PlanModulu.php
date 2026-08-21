<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanModulu extends Model
{
    protected $table = 'plan_modulleri';

    protected $fillable = [
        'plan_id',
        'modul_id',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function modul(): BelongsTo
    {
        return $this->belongsTo(Modul::class, 'modul_id');
    }
}

