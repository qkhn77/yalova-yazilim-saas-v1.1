<?php

namespace App\Models\Muhasebe;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\Firma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CariBankaHesabi extends Model
{
    use HasFirmaTenantScope;
    use SoftDeletes;

    protected $table = 'cari_banka_hesaplari';

    protected $fillable = [
        'firma_id',
        'cari_id',
        'hesap_adi',
        'banka_adi',
        'sube_adi',
        'sube_kodu',
        'hesap_no',
        'para_birimi',
        'iban',
        'varsayilan_mi',
        'sira',
    ];

    protected function casts(): array
    {
        return [
            'varsayilan_mi' => 'boolean',
            'sira' => 'integer',
        ];
    }

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            if ($this->varsayilan_mi && $this->firma_id && $this->cari_id) {
                static::query()
                    ->withoutGlobalScopes()
                    ->where('firma_id', $this->firma_id)
                    ->where('cari_id', $this->cari_id)
                    ->lockForUpdate()
                    ->get();
            }

            $sonuc = parent::save($options);

            if ($sonuc && $this->varsayilan_mi) {
                $sorgu = static::query()
                    ->withoutGlobalScopes()
                    ->where('firma_id', $this->firma_id)
                    ->where('cari_id', $this->cari_id)
                    ->whereKeyNot($this->getKey());

                $sorgu->update(['varsayilan_mi' => false]);
            }

            return $sonuc;
        });
    }

    public function cari(): BelongsTo
    {
        return $this->belongsTo(Cari::class, 'cari_id');
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }
}
