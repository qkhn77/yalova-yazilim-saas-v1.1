<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AdminPerformanceProbeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->aktifMi($request)) {
            return $next($request);
        }

        $baslangic = hrtime(true);
        $sorguSayisi = 0;
        $sorguMs = 0.0;
        $enYavasSorgular = [];

        DB::listen(function (QueryExecuted $event) use (&$sorguSayisi, &$sorguMs, &$enYavasSorgular): void {
            $sorguSayisi++;
            $sorguMs += (float) $event->time;

            $enYavasSorgular[] = [
                'ms' => round((float) $event->time, 2),
                'sql' => mb_substr(preg_replace('/\s+/', ' ', $event->sql) ?: $event->sql, 0, 420),
                'bindings' => array_map(
                    static fn (mixed $binding): string => mb_substr((string) $binding, 0, 80),
                    $event->bindings
                ),
            ];

            usort($enYavasSorgular, static fn (array $sol, array $sag): int => $sag['ms'] <=> $sol['ms']);
            $enYavasSorgular = array_slice($enYavasSorgular, 0, 5);
        });

        $response = $next($request);
        $uygulamaMs = (hrtime(true) - $baslangic) / 1_000_000;

        $response->headers->set('X-Admin-Perf-App-Ms', (string) round($uygulamaMs, 2));
        $response->headers->set('X-Admin-Perf-Queries', (string) $sorguSayisi);
        $response->headers->set('X-Admin-Perf-Query-Ms', (string) round($sorguMs, 2));
        $response->headers->set(
            'X-Admin-Perf-Slowest',
            base64_encode(json_encode($enYavasSorgular, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]')
        );
        $response->headers->set(
            'Server-Timing',
            sprintf('app;dur=%.2f, db;dur=%.2f', $uygulamaMs, $sorguMs)
        );

        return $response;
    }

    private function aktifMi(Request $request): bool
    {
        return app()->environment(['local', 'testing'])
            && $request->headers->get('X-Admin-Performance-Probe') === '1';
    }
}
