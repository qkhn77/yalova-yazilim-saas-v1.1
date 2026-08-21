<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter as RateLimiterManager;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ContactFormSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_iletisim_formu_ip_limitleri_tanimlidir(): void
    {
        $route = Route::getRoutes()->getByName('contact.store');
        $this->assertNotNull($route);
        $this->assertContains('throttle:contact-submit', $route->middleware());

        $request = Request::create('/iletisim', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.10',
        ]);
            $limits = app(RateLimiterManager::class)->limiter('contact-submit')($request);

        $this->assertSame([[1, 600], [3, 86400]], array_map(
            static fn ($limit): array => [$limit->maxAttempts, $limit->decaySeconds],
            $limits,
        ));
    }
}
