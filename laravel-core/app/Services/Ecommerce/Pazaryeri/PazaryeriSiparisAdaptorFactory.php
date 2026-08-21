<?php

namespace App\Services\Ecommerce\Pazaryeri;

use App\Services\Ecommerce\Pazaryeri\Contracts\PazaryeriSiparisAdaptor;
use App\Support\EcommercePazaryeriTanimlari;
use RuntimeException;

class PazaryeriSiparisAdaptorFactory
{
    public function make(string $pazaryeriKodu): PazaryeriSiparisAdaptor
    {
        return match (trim(strtolower($pazaryeriKodu))) {
            EcommercePazaryeriTanimlari::PAZARYERI_AMAZON => app(AmazonSiparisAdaptor::class),
            EcommercePazaryeriTanimlari::PAZARYERI_EBAY => app(EbaySiparisAdaptor::class),
            EcommercePazaryeriTanimlari::PAZARYERI_TRENDYOL => app(TrendyolSiparisAdaptor::class),
            EcommercePazaryeriTanimlari::PAZARYERI_HEPSIBURADA => app(HepsiburadaSiparisAdaptor::class),
            EcommercePazaryeriTanimlari::PAZARYERI_N11 => app(N11SiparisAdaptor::class),
            default => throw new RuntimeException('Desteklenmeyen pazaryeri adaptoru: '.$pazaryeriKodu),
        };
    }
}

