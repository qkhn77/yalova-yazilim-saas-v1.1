<?php

namespace Tests\Unit;

use App\Support\FrontIcerikCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FrontIcerikCacheTest extends TestCase
{
    public function test_icerik_cache_surumu_temizlenince_degisir(): void
    {
        Cache::forget('front:icerik:blog:surum');

        $this->assertSame('1', FrontIcerikCache::surum('blog'));

        FrontIcerikCache::temizle('blog');

        $this->assertNotSame('1', FrontIcerikCache::surum('blog'));
    }
}
