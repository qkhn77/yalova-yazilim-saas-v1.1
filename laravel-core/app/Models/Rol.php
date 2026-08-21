<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rol extends Model
{
    use SoftDeletes;

    protected $table = 'roller';

    protected $fillable = [
        'ad',
        'kod',
        'aciklama',
        'sistem_rolu_mu',
    ];

    protected $casts = [
        'sistem_rolu_mu' => 'boolean',
    ];

    public function yetkiler(): BelongsToMany
    {
        return $this->belongsToMany(Yetki::class, 'rol_yetkileri', 'rol_id', 'yetki_id')
            ->withTimestamps();
    }

    public function firmaKullanicilari(): HasMany
    {
        return $this->hasMany(FirmaKullanici::class, 'rol_id');
    }
}

