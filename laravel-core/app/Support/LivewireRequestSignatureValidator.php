<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class LivewireRequestSignatureValidator
{
    /**
     * @param  array<int, string>  $ignoreQuery
     */
    public static function hasValidSignature(Request $request, bool $absolute = true, array $ignoreQuery = []): bool
    {
        if (URL::hasValidSignature($request, $absolute, $ignoreQuery)) {
            return true;
        }

        $originalRequestUri = $request->server('ORIG_REQUEST_URI');

        if (! is_string($originalRequestUri) || trim($originalRequestUri) === '') {
            return false;
        }

        $originalRequest = Request::create(
            $request->getSchemeAndHttpHost().$originalRequestUri,
            $request->getMethod()
        );

        return URL::hasValidSignature($originalRequest, $absolute, $ignoreQuery);
    }
}
