<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HizliSatisFavorisi extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'hizli_satis_favorileri';

    protected $fillable = [
        'firma_id',
        'kullanici_id',
        'stok_karti_id',
    ];

    public function stokKarti(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_karti_id');
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }
}
