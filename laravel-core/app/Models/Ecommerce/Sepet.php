<?php

namespace App\Models\Ecommerce;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sepet extends Model
{
    protected $table = 'sepetler';

    protected $fillable = [
        'kullanici_id',
        'oturum_id',
        'son_aktif_at',
    ];

    protected function casts(): array
    {
        return [
            'son_aktif_at' => 'datetime',
        ];
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }

    public function kalemler(): HasMany
    {
        return $this->hasMany(SepetKalemi::class, 'sepet_id');
    }
}
