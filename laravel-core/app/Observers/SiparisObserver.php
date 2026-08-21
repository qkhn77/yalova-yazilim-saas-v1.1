<?php

namespace App\Observers;

use App\Models\Ecommerce\Siparis;
use App\Services\AuditTrailServisi;

class SiparisObserver
{
    public function __construct(
        private readonly AuditTrailServisi $auditTrailServisi,
    ) {}

    public function updated(Siparis $siparis): void
    {
        $this->auditTrailServisi->modelDegisimiKaydet(
            'siparis.guncelle',
            $siparis,
            $siparis->getOriginal(),
            $siparis->getAttributes(),
            ['durum', 'kargo_firmasi', 'takip_no', 'teslim_tarihi', 'iptal_nedeni']
        );
    }
}
