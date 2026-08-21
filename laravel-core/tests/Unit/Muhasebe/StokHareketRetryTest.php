<?php

namespace Tests\Unit\Muhasebe;

use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Muhasebe\Servisler\StokMaliyetHesaplamaServisi;
use App\Services\FirmaAyarDeposu;
use App\Services\SistemOlayServisi;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StokHareketRetryTest extends TestCase
{
    public function test_deadlock_retry_calisir(): void
    {
        Cache::flush();

        /** @var object{dene(callable):mixed} $servis */
        $servis = new class(app(MuhasebeFirmaErisimDenetleyicisi::class), app(StokMaliyetHesaplamaServisi::class), app(SistemOlayServisi::class), app(FirmaAyarDeposu::class)) extends StokHareketServisi
        {
            public function dene(callable $callback): mixed
            {
                return $this->retryableTransaction($callback, 3);
            }
        };

        $attempt = 0;
        $sonuc = $servis->dene(function () use (&$attempt) {
            $attempt++;
            if ($attempt < 3) {
                $pdo = new \PDOException('deadlock');
                $pdo->errorInfo = ['40001', 1213, 'deadlock found when trying to get lock'];
                throw new QueryException('mysql', 'update stok_kartlari set ...', [], $pdo);
            }

            return 'ok';
        });

        $this->assertSame('ok', $sonuc);
        $this->assertSame(3, $attempt);

        // Metrik + işlem sonu özeti log (stok.deadlock.retry_ozet) üretilir; ölçüm cache ile doğrulanır.
        $this->assertSame(2, (int) Cache::get('muhasebe:metrics:deadlock_retry_count', 0));
    }
}
