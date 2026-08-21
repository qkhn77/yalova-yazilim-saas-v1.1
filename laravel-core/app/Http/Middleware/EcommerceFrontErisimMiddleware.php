<?php

namespace App\Http\Middleware;

use App\Services\EcommerceModulKuralServisi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EcommerceFrontErisimMiddleware
{
    public function __construct(
        private readonly EcommerceModulKuralServisi $kuralServisi,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $firmaId = $this->kuralServisi->firmaIdBelirle($request);
        if (! $this->kuralServisi->erisimAcikMi($firmaId)) {
            $this->kuralServisi->engelliErisimiKaydet($request, $firmaId);
            abort(404);
        }

        return $next($request);
    }
}
