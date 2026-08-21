<?php

namespace App\Models;

use App\Traits\HasFirma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FirmaAyari extends Model
{
    use HasFirma;
    use SoftDeletes;

    /** @var array<int, string> */
    private const HASSAS_ANAHTARLAR = [
        'paytr_merchant_key',
        'paytr_merchant_salt',
        'iyzico_api_key',
        'iyzico_secret_key',
        'telegram_bot_token',
        'teknik_servis_telegram_bot_token',
        'barkodlu_satis_telegram_bot_token',
    ];

    protected $table = 'firma_ayarlari';

    protected $fillable = [
        'firma_id',
        'anahtar',
        'deger',
    ];

    protected $casts = [
        'deger' => 'array',
    ];

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_id');
    }

    /**
     * Hassas ödeme anahtarlarını model serializasyonunda maskele.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        if (in_array((string) ($this->anahtar ?? ''), self::HASSAS_ANAHTARLAR, true)) {
            $data['deger'] = ['deger' => '***'];
        }

        return $data;
    }
}
