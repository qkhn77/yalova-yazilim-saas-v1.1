<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasMuhasebeTanimKaydi;
use App\Models\Concerns\TanimGorunurFirmaIle;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Birim extends Model
{
    use HasMuhasebeTanimKaydi;
    use SoftDeletes;
    use TanimGorunurFirmaIle;

    protected $table = 'muhasebe_birimler';

    protected $fillable = [
        'firma_id',
        'kod',
        'ad',
        'gib_birim_kodu',
        'aktif_mi',
        'is_sabit',
        'varsayilan_mi',
    ];

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
            'is_sabit' => 'boolean',
            'varsayilan_mi' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $birim): void {
            if (! $birim->varsayilan_mi || ! $birim->firma_id || $birim->is_sabit) {
                $birim->varsayilan_mi = false;

                return;
            }

            $sorgu = static::query()->where('firma_id', $birim->firma_id);
            if ($birim->exists) {
                $sorgu->whereKeyNot($birim->getKey());
            }
            $sorgu->update(['varsayilan_mi' => false]);
        });
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }
}
