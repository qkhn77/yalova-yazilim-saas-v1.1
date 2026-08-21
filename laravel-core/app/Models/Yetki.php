<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Yetki extends Model
{
    use SoftDeletes;

    protected $table = 'yetkiler';

    protected $fillable = [
        'ad',
        'kod',
        'modul_kodu',
        'eylem',
    ];

    public function roller(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_yetkileri', 'yetki_id', 'rol_id')
            ->withTimestamps();
    }

    public function kullaniciYetkileri(): HasMany
    {
        return $this->hasMany(KullaniciYetki::class, 'yetki_id');
    }
}

