<?php

namespace App\Services;

use App\Models\SistemOlayi;
use Illuminate\Support\Facades\Log;
use Throwable;

class SistemOlayServisi
{
    /**
     * @param  array<string,mixed>  $context
     */
    public function olayKaydet(string $tip, string $seviye, string $mesaj, array $context = []): void
    {
        $seviye = strtolower($seviye);
        if (! in_array($seviye, ['info', 'warning', 'error', 'critical'], true)) {
            $seviye = 'info';
        }

        $firmaId = isset($context['firma_id']) && is_numeric((string) $context['firma_id'])
            ? (int) $context['firma_id']
            : null;

        $kayit = [
            'tip' => $tip,
            'seviye' => $seviye,
            'mesaj' => mb_substr($mesaj, 0, 255),
            'context' => $context !== [] ? $context : null,
            'firma_id' => $firmaId,
        ];

        try {
            SistemOlayi::query()->withoutGlobalScopes()->create($kayit);
        } catch (Throwable) {
            // Olay kaydı işletim akışını kesmemeli.
        }

        // Üst katman log: mevcut loglar korunur, bu kayıt ek telemetri sağlar.
        Log::channel('stack')->{$seviye}('sistem.olay.'.$tip, $context);
    }
}
