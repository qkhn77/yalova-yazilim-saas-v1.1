<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Modul extends Model
{
    use SoftDeletes;

    protected $table = 'moduller';

    protected $fillable = [
        'ad',
        'kod',
        'aciklama',
        'aktif_mi',
        'siralama',
    ];

    protected $casts = [
        'aktif_mi' => 'boolean',
        'siralama' => 'integer',
    ];

    public function firmaModulleri(): HasMany
    {
        return $this->hasMany(FirmaModulu::class, 'modul_id');
    }

    public function planModulleri(): HasMany
    {
        return $this->hasMany(PlanModulu::class, 'modul_id');
    }
}

