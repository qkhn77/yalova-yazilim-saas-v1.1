<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $table = 'planlar';

    protected $fillable = [
        'ad',
        'kod',
        'ucret',
        'sure_gun',
        'aktif_mi',
    ];

    protected $casts = [
        'ucret' => 'decimal:2',
        'sure_gun' => 'integer',
        'aktif_mi' => 'boolean',
    ];

    public function planModulleri(): HasMany
    {
        return $this->hasMany(PlanModulu::class, 'plan_id');
    }

    public function firmaAbonelikleri(): HasMany
    {
        return $this->hasMany(FirmaAboneligi::class, 'plan_id');
    }
}

