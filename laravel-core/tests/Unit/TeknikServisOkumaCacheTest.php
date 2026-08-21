<?php

namespace Tests\Unit;

use App\Services\TenantContextService;
use App\TeknikServis\Servisler\TeknikServisOkumaCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TeknikServisOkumaCacheTest extends TestCase
{
    public function test_okuma_cache_istek_ve_kalici_cache_kullanir_ve_temizlenince_yeniler(): void
    {
        Cache::flush();
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => 1]);

        $cache = app(TeknikServisOkumaCache::class);
        $sayac = 0;

        $ilk = $cache->remember('dashboard:test', function () use (&$sayac): array {
            $sayac++;

            return ['deger' => $sayac];
        });
        $ikinci = $cache->remember('dashboard:test', function () use (&$sayac): array {
            $sayac++;

            return ['deger' => $sayac];
        });

        $this->assertSame(['deger' => 1], $ilk);
        $this->assertSame($ilk, $ikinci);
        $this->assertSame(1, $sayac);

        $cache->temizle();

        $ucuncu = $cache->remember('dashboard:test', function () use (&$sayac): array {
            $sayac++;

            return ['deger' => $sayac];
        });

        $this->assertSame(['deger' => 2], $ucuncu);
        $this->assertSame(2, $sayac);
    }
}
