<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlacakHatirlatmaLogu extends Model
{
    use HasFirmaTenantScope;

    public const DURUM_KUYRUKTA = 'kuyrukta';

    public const DURUM_GONDERILDI = 'gonderildi';

    public const DURUM_BASARISIZ = 'basarisiz';

    public const DURUM_HEDEF_YOK = 'hedef_yok';

    public const DURUM_ATLANDI = 'atlandi';

    protected $table = 'muhasebe_alacak_hatirlatma_loglari';

    protected $fillable = [
        'firma_id',
        'cari_id',
        'kanal',
        'saglayici',
        'hedef',
        'baslik',
        'mesaj',
        'durum',
        'deneme_sayisi',
        'son_deneme_at',
        'gonderildi_at',
        'hata',
        'payload',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'son_deneme_at' => 'datetime',
            'gonderildi_at' => 'datetime',
        ];
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }
}
