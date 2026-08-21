<?php

namespace App\Models;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Muhasebe\Cari;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\SekreterKayitKuraliServisi;

class SekreterRandevusu extends \Illuminate\Database\Eloquent\Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'sekreter_randevulari';
    protected $fillable = ['firma_id', 'olusturan_kullanici_id', 'cari_id', 'baslik', 'baslangic_tarihi', 'baslangic_saati', 'bitis_tarihi', 'bitis_saati', 'aciklama', 'hatirlatma_tipi', 'tekrar_tipi'];

    protected function casts(): array
    {
        return ['baslangic_tarihi' => 'date', 'bitis_tarihi' => 'date', 'baslangic_saati' => 'datetime:H:i', 'bitis_saati' => 'datetime:H:i'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $kayit): void {
            app(SekreterKayitKuraliServisi::class)->kontrolEt($kayit);
        });
    }

    public function olusturanKullanici(): BelongsTo { return $this->belongsTo(User::class, 'olusturan_kullanici_id'); }
    public function cari(): BelongsTo { return $this->belongsTo(Cari::class, 'cari_id'); }
    public function hatirlatmalar(): MorphMany { return $this->morphMany(SekreterHatirlatmasi::class, 'hatirlanabilir'); }
}
