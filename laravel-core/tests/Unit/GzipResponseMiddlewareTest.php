<?php

namespace Tests\Unit;

use App\Http\Middleware\GzipResponseMiddleware;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class GzipResponseMiddlewareTest extends TestCase
{
    public function test_gzip_destegi_olan_text_cevabini_sikistirir(): void
    {
        $middleware = new GzipResponseMiddleware();
        $request = Request::create('/test', 'GET', server: ['HTTP_ACCEPT_ENCODING' => 'gzip, deflate']);
        $body = str_repeat('Yalova Kamera performans ', 100);

        $response = $middleware->handle($request, fn (): Response => new Response($body, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]));

        $this->assertSame('gzip', $response->headers->get('Content-Encoding'));
        $this->assertStringContainsString('Accept-Encoding', (string) $response->headers->get('Vary'));
        $this->assertSame($body, gzdecode((string) $response->getContent()));
        $this->assertLessThan(strlen($body), strlen((string) $response->getContent()));
    }

    public function test_gzip_destegi_yoksa_cevabi_degistirmez(): void
    {
        $middleware = new GzipResponseMiddleware();
        $request = Request::create('/test');
        $body = str_repeat('Yalova Kamera performans ', 100);

        $response = $middleware->handle($request, fn (): Response => new Response($body, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]));

        $this->assertFalse($response->headers->has('Content-Encoding'));
        $this->assertSame($body, $response->getContent());
    }
}
