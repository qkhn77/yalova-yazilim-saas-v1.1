<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GzipResponseMiddleware
{
    private const MIN_LENGTH = 1024;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->sikistirilebilirMi($request, $response)) {
            return $response;
        }

        $icerik = $response->getContent();
        if (! is_string($icerik) || strlen($icerik) < self::MIN_LENGTH) {
            return $response;
        }

        $probeAktifMi = $request->headers->get('X-Admin-Performance-Probe') === '1'
            && $this->performansProbeOrtamiMi();
        $baslangic = $probeAktifMi ? hrtime(true) : null;

        $sikistirilmis = gzencode($icerik, 6);
        if ($sikistirilmis === false) {
            return $response;
        }

        if ($probeAktifMi && $baslangic !== null) {
            $response->headers->set('X-Admin-Gzip-Ms', (string) round((hrtime(true) - $baslangic) / 1_000_000, 2));
            $response->headers->set('X-Admin-Gzip-Raw-Bytes', (string) strlen($icerik));
            $response->headers->set('X-Admin-Gzip-Bytes', (string) strlen($sikistirilmis));
        }

        $response->setContent($sikistirilmis);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($sikistirilmis));
        $response->headers->set('Vary', $this->varyBasligi($response));

        return $response;
    }

    private function sikistirilebilirMi(Request $request, Response $response): bool
    {
        if (! str_contains((string) $request->headers->get('Accept-Encoding'), 'gzip')) {
            return false;
        }

        if ((bool) ini_get('zlib.output_compression')) {
            return false;
        }

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        if ($response->headers->has('Content-Encoding')) {
            return false;
        }

        if (! $response->isSuccessful()) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));
        if ($contentType === '') {
            return true;
        }

        foreach ([
            'text/',
            'application/json',
            'application/javascript',
            'application/xml',
            'application/xhtml+xml',
            'image/svg+xml',
        ] as $izinliTip) {
            if (str_starts_with($contentType, $izinliTip)) {
                return true;
            }
        }

        return false;
    }

    private function varyBasligi(Response $response): string
    {
        $vary = array_filter(array_map('trim', explode(',', (string) $response->headers->get('Vary'))));

        foreach ($vary as $deger) {
            if (strcasecmp($deger, 'Accept-Encoding') === 0) {
                return implode(', ', $vary);
            }
        }

        $vary[] = 'Accept-Encoding';

        return implode(', ', $vary);
    }

    private function performansProbeOrtamiMi(): bool
    {
        try {
            return app()->bound('env') && app()->environment(['local', 'testing']);
        } catch (Throwable) {
            return false;
        }
    }
}
