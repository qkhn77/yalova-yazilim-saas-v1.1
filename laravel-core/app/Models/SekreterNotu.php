<?php

namespace App\Models;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Muhasebe\Cari;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\SekreterKayitKuraliServisi;

class SekreterNotu extends \Illuminate\Database\Eloquent\Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'sekreter_notlari';
    protected $fillable = ['firma_id', 'kullanici_id', 'cari_id', 'baslik', 'icerik', 'etiket', 'sabit_mi'];

    protected $casts = ['sabit_mi' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $kayit): void {
            app(SekreterKayitKuraliServisi::class)->kontrolEt($kayit);
        });
    }

    public function kullanici(): BelongsTo { return $this->belongsTo(User::class, 'kullanici_id'); }
    public function cari(): BelongsTo { return $this->belongsTo(Cari::class, 'cari_id'); }
    public function hatirlatmalar(): MorphMany { return $this->morphMany(SekreterHatirlatmasi::class, 'hatirlanabilir'); }
}
