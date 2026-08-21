<?php

namespace App\Observers;

use App\Models\Muhasebe\StokKarti;
use App\Services\AuditTrailServisi;

class StokKartiObserver
{
    public function __construct(
        private readonly AuditTrailServisi $auditTrailServisi,
    ) {}

    public function updated(StokKarti $stokKarti): void
    {
        $this->auditTrailServisi->modelDegisimiKaydet(
            'stok_karti.guncelle',
            $stokKarti,
            $stokKarti->getOriginal(),
            $stokKarti->getAttributes(),
            ['ad', 'kod', 'kategori_id', 'durum', 'satis_fiyati', 'stok_takip', 'minimum_stok']
        );
    }
}
