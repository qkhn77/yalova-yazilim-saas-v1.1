<?php

namespace App\Models;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Muhasebe\Cari;
use App\Models\Personel\Personel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\SekreterKayitKuraliServisi;

class SekreterGorevi extends \Illuminate\Database\Eloquent\Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'sekreter_gorevleri';

    protected $fillable = [
        'firma_id', 'olusturan_kullanici_id', 'atanan_kullanici_id', 'atanan_personel_id', 'cari_id',
        'baslik', 'aciklama', 'tarih', 'saat', 'durum', 'oncelik', 'hatirlatma_tipi', 'tekrar_tipi',
    ];

    protected function casts(): array
    {
        return ['tarih' => 'date', 'saat' => 'datetime:H:i', 'gecikti_mi' => 'boolean'];
    }

    protected $appends = ['gecikti_mi'];

    protected static function booted(): void
    {
        static::saving(function (self $kayit): void {
            app(SekreterKayitKuraliServisi::class)->kontrolEt($kayit);
        });
    }

    public function getGeciktiMiAttribute(): bool
    {
        return $this->durum === 'bekliyor' && $this->tarih?->isBefore(today());
    }

    public function olusturanKullanici(): BelongsTo { return $this->belongsTo(User::class, 'olusturan_kullanici_id'); }
    public function atananKullanici(): BelongsTo { return $this->belongsTo(User::class, 'atanan_kullanici_id'); }
    public function atananPersonel(): BelongsTo { return $this->belongsTo(Personel::class, 'atanan_personel_id'); }
    public function cari(): BelongsTo { return $this->belongsTo(Cari::class, 'cari_id'); }
    public function hatirlatmalar(): MorphMany { return $this->morphMany(SekreterHatirlatmasi::class, 'hatirlanabilir'); }
}
