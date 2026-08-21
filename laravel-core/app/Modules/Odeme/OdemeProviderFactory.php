<?php

namespace App\Modules\Odeme;

use App\Models\Ecommerce\Odeme;
use App\Modules\Odeme\Contracts\OdemeProviderInterface;
use App\Modules\Odeme\Servisler\IyzicoOdemeServisi;
use App\Modules\Odeme\Servisler\PaytrOdemeServisi;
use InvalidArgumentException;

class OdemeProviderFactory
{
    public function make(string $provider): OdemeProviderInterface
    {
        return match ($provider) {
            Odeme::PROVIDER_PAYTR => app(PaytrOdemeServisi::class),
            Odeme::PROVIDER_IYZICO => app(IyzicoOdemeServisi::class),
            default => throw new InvalidArgumentException('Bilinmeyen ödeme providerı: '.$provider),
        };
    }
}
