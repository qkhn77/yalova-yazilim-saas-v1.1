<?php

namespace App\Http\Middleware;

use App\Services\Front\FrontTercihServisi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FrontTercihMiddleware
{
    public function __construct(
        private readonly FrontTercihServisi $tercihServisi,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->tercihServisi->aktifDil();
        app()->setLocale($locale);

        return $next($request);
    }
}

