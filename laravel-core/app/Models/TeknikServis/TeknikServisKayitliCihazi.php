<?php

namespace App\Models\TeknikServis;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class TeknikServisKayitliCihazi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'teknik_servis_kayitli_cihazlar';

    protected $fillable = [
        'firma_id', 'cari_id', 'cihaz_id', 'marka_id', 'model_no', 'seri_no',
        'ayirt_edici_bilgi', 'notlar', 'aktif_mi', 'olusturan_id', 'guncelleyen_id',
        'garanti_baslangic_tarihi', 'garanti_bitis_tarihi', 'bakim_periyot_ay', 'son_bakim_tarihi',
    ];

    protected $casts = [
        'aktif_mi' => 'boolean',
        'garanti_baslangic_tarihi' => 'date',
        'garanti_bitis_tarihi' => 'date',
        'son_bakim_tarihi' => 'date',
    ];

    protected static function booted(): void
    {
        static::updated(function (self $cihaz): void {
            $alanlar = [
                'cari_id', 'cihaz_id', 'marka_id', 'model_no', 'seri_no',
                'ayirt_edici_bilgi', 'notlar', 'aktif_mi',
                'garanti_baslangic_tarihi', 'garanti_bitis_tarihi',
                'bakim_periyot_ay', 'son_bakim_tarihi',
            ];
            $eski = $yeni = [];
            foreach ($alanlar as $alan) {
                if (! $cihaz->wasChanged($alan)) {
                    continue;
                }
                $eski[$alan] = $cihaz->getOriginal($alan);
                $yeni[$alan] = $cihaz->getAttribute($alan);
            }
            if ($eski === []) {
                return;
            }
            TeknikServisKayitliCihazDegisikligi::query()->create([
                'firma_id' => $cihaz->firma_id,
                'kayitli_cihaz_id' => $cihaz->getKey(),
                'kullanici_id' => auth()->id(),
                'olay' => 'guncelleme',
                'eski_degerler' => $eski,
                'yeni_degerler' => $yeni,
                'ip_adresi' => request()->ip(),
            ]);
        });
    }

    public function getCihazNoAttribute(): string
    {
        return 'CIH-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function getGarantiDurumuAttribute(): string
    {
        if (! $this->garanti_bitis_tarihi) {
            return 'Tarih yok';
        }

        return $this->garanti_bitis_tarihi->isPast() ? 'Süresi doldu' : 'Devam ediyor';
    }

    public function getSonrakiBakimTarihiAttribute(): ?Carbon
    {
        if (! $this->son_bakim_tarihi || (int) $this->bakim_periyot_ay < 1) {
            return null;
        }

        return $this->son_bakim_tarihi->copy()->addMonths((int) $this->bakim_periyot_ay);
    }

    public function getBakimDurumuAttribute(): string
    {
        $tarih = $this->sonraki_bakim_tarihi;
        if (! $tarih) {
            return 'Planlanmamış';
        }
        if ($tarih->isPast()) {
            return 'Gecikti';
        }
        if ($tarih->lte(now()->addDays(30))) {
            return 'Yaklaşıyor';
        }

        return 'Planlandı';
    }

    public function firma(): BelongsTo { return $this->belongsTo(Firma::class); }
    public function cari(): BelongsTo { return $this->belongsTo(Cari::class, 'cari_id'); }
    public function cihaz(): BelongsTo { return $this->belongsTo(TeknikServisCihazTanimi::class, 'cihaz_id'); }
    public function marka(): BelongsTo { return $this->belongsTo(TeknikServisMarkaTanimi::class, 'marka_id'); }
    public function servisKayitlari(): HasMany { return $this->hasMany(TeknikServisKaydi::class, 'kayitli_cihaz_id'); }
    public function degisiklikler(): HasMany { return $this->hasMany(TeknikServisKayitliCihazDegisikligi::class, 'kayitli_cihaz_id')->latest(); }
    public function olusturan(): BelongsTo { return $this->belongsTo(User::class, 'olusturan_id'); }
    public function guncelleyen(): BelongsTo { return $this->belongsTo(User::class, 'guncelleyen_id'); }
}
