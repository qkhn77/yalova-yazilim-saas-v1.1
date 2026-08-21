<?php

namespace Tests\Unit;

use App\Models\TeknikServis\TeknikServisKaydi;
use App\Services\TenantContextService;
use App\TeknikServis\Filament\TeknikServisTahsilatFormu;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class TeknikServisTahsilatFormuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->semayiKur();
        $this->cacheleriTemizle();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('pos_hesaplari');
        Schema::dropIfExists('banka_hesaplari');
        Schema::dropIfExists('kasa_hesaplari');

        parent::tearDown();
    }

    public function test_hesap_secenekleri_ayni_render_icinde_tekrar_sorgulanmaz(): void
    {
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => 1]);

        DB::table('kasa_hesaplari')->insert([
            ['firma_id' => 1, 'ad' => 'Merkez Kasa', 'para_birimi' => 'TRY', 'deleted_at' => null],
            ['firma_id' => 2, 'ad' => 'Diger Kasa', 'para_birimi' => 'TRY', 'deleted_at' => null],
        ]);
        DB::table('banka_hesaplari')->insert([
            ['firma_id' => 1, 'ad' => 'Ana Banka', 'para_birimi' => 'USD', 'deleted_at' => null],
        ]);
        DB::table('pos_hesaplari')->insert([
            ['firma_id' => 1, 'ad' => 'Magaza POS', 'para_birimi' => 'EUR', 'deleted_at' => null],
        ]);

        $record = new TeknikServisKaydi(['firma_id' => 1]);
        $secenekMetodu = $this->privateStaticMethod('hesapSecenekleri');

        DB::enableQueryLog();
        DB::flushQueryLog();

        $ilkKasa = $secenekMetodu->invoke(null, $record, 'kasa');
        $ikinciKasa = $secenekMetodu->invoke(null, $record, 'kasa');
        $ilkBanka = $secenekMetodu->invoke(null, $record, 'banka');
        $ikinciBanka = $secenekMetodu->invoke(null, $record, 'banka');
        $ilkPos = $secenekMetodu->invoke(null, $record, 'pos');
        $ikinciPos = $secenekMetodu->invoke(null, $record, 'pos');

        $this->assertSame($ilkKasa, $ikinciKasa);
        $this->assertSame($ilkBanka, $ikinciBanka);
        $this->assertSame($ilkPos, $ikinciPos);
        $this->assertSame([1 => 'Merkez Kasa (TRY)'], $ilkKasa);
        $this->assertSame([1 => 'Ana Banka (USD)'], $ilkBanka);
        $this->assertSame([1 => 'Magaza POS (EUR)'], $ilkPos);

        $hesapSorgulari = array_filter(DB::getQueryLog(), static function (array $sorgu): bool {
            $sql = strtolower((string) ($sorgu['query'] ?? ''));

            return str_starts_with($sql, 'select')
                && (
                    str_contains($sql, 'kasa_hesaplari')
                    || str_contains($sql, 'banka_hesaplari')
                    || str_contains($sql, 'pos_hesaplari')
                );
        });

        $this->assertCount(3, $hesapSorgulari);

        $paraBirimiMetodu = $this->privateStaticMethod('hesapParaBirimi');
        DB::flushQueryLog();

        $this->assertSame('TRY', $paraBirimiMetodu->invoke(null, 'kasa', 1));
        $this->assertSame('USD', $paraBirimiMetodu->invoke(null, 'banka', 1));
        $this->assertSame('EUR', $paraBirimiMetodu->invoke(null, 'pos', 1));
        $this->assertSame([], DB::getQueryLog());
    }

    private function semayiKur(): void
    {
        Schema::create('kasa_hesaplari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->string('ad');
            $table->char('para_birimi', 3)->default('TRY');
            $table->softDeletes();
        });

        Schema::create('banka_hesaplari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->string('ad');
            $table->char('para_birimi', 3)->default('TRY');
            $table->softDeletes();
        });

        Schema::create('pos_hesaplari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->string('ad');
            $table->char('para_birimi', 3)->default('TRY');
            $table->softDeletes();
        });
    }

    private function cacheleriTemizle(): void
    {
        $sinif = new ReflectionClass(TeknikServisTahsilatFormu::class);

        foreach (['hesapSecenekCache', 'hesapParaBirimiCache'] as $ozellikAdi) {
            $ozellik = $sinif->getProperty($ozellikAdi);
            $ozellik->setAccessible(true);
            $ozellik->setValue(null, []);
        }
    }

    private function privateStaticMethod(string $ad): ReflectionMethod
    {
        $method = new ReflectionMethod(TeknikServisTahsilatFormu::class, $ad);
        $method->setAccessible(true);

        return $method;
    }
}
