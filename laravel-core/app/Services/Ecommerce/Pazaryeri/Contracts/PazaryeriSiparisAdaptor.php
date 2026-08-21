<?php

namespace App\Services\Ecommerce\Pazaryeri\Contracts;

use App\Models\Ecommerce\EcommercePazaryeriEntegrasyon;

interface PazaryeriSiparisAdaptor
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function siparisleriGetir(EcommercePazaryeriEntegrasyon $entegrasyon): array;
}

