<?php

namespace App\Observers;

use App\Models\Muhasebe\Fatura;
use App\Services\AuditTrailServisi;

class FaturaObserver
{
    public function __construct(
        private readonly AuditTrailServisi $auditTrailServisi,
    ) {}

    public function updated(Fatura $fatura): void
    {
        $this->auditTrailServisi->modelDegisimiKaydet(
            'fatura.guncelle',
            $fatura,
            $fatura->getOriginal(),
            $fatura->getAttributes(),
            ['durum', 'odeme_durumu', 'genel_toplam', 'odenecek_tutar', 'acik_tutar', 'cari_id', 'para_birimi']
        );
    }
}
