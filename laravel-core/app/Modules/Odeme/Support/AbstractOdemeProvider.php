<?php

namespace App\Modules\Odeme\Support;

abstract class AbstractOdemeProvider
{
    protected function extractSiparisIdFromProviderRef(string $providerRef): ?int
    {
        // provider_ref formatı: "{siparis_id}-{random}"
        if ($providerRef === '') {
            return null;
        }

        $parts = explode('-', $providerRef, 2);
        if (count($parts) < 1) {
            return null;
        }

        if (! is_numeric($parts[0])) {
            return null;
        }

        return (int) $parts[0];
    }
}
