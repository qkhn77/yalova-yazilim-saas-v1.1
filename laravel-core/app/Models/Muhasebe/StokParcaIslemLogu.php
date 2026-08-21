<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\User;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokParcaIslemLogu extends Model
{
    use HasFirmaTenantScope;

    protected $table = 'stok_parca_islem_loglari';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['veri' => 'array', 'kullanici_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $log): void {
            throw new IsKuraliIstisnasi('Stok parçası işlem geçmişi kayıtları silinemez.');
        });
    }

    public function stokKarti(): BelongsTo
    {
        return $this->belongsTo(StokKarti::class, 'stok_id');
    }

    public function kaynakParca(): BelongsTo
    {
        return $this->belongsTo(StokParcasi::class, 'kaynak_parca_id');
    }

    public function anaParca(): BelongsTo
    {
        return $this->belongsTo(StokParcasi::class, 'ana_parca_id');
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }
}
