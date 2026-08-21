<?php

namespace Tests\Feature\TeknikServis;

use App\TeknikServis\Servisler\TeknikServisFisNumarasiServisi;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeknikServisFisNumarasiServisiTest extends TestCase
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

        $this->testSemasiniKur();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('teknik_servis_fis_numaralari');
        Schema::dropIfExists('teknik_servis_kayitlari');

        parent::tearDown();
    }

    public function test_sonraki_aday_mevcut_fis_numaralarini_dikkate_alir(): void
    {
        DB::table('teknik_servis_kayitlari')->insert([
            ['firma_id' => 1, 'fis_no' => 'YB-SER1001'],
            ['firma_id' => 2, 'fis_no' => 'YB-SER1005'],
            ['firma_id' => 1, 'fis_no' => 'YB-SER-ESKI'],
        ]);

        DB::table('teknik_servis_fis_numaralari')->insert([
            'firma_id' => 1,
            'yil' => (int) now()->format('Y'),
            'prefix' => 'YB-SER',
            'son_sira' => 1002,
        ]);

        $this->assertSame('YB-SER1006', app(TeknikServisFisNumarasiServisi::class)->sonrakiAday(1));
    }

    public function test_benzersiz_uret_firma_sayacini_gunceller(): void
    {
        DB::table('teknik_servis_kayitlari')->insert([
            ['firma_id' => 1, 'fis_no' => 'YB-SER1003'],
            ['firma_id' => 2, 'fis_no' => 'YB-SER1008'],
        ]);

        $fisNo = app(TeknikServisFisNumarasiServisi::class)->benzersizUret(1);

        $this->assertSame('YB-SER1009', $fisNo);
        $this->assertDatabaseHas('teknik_servis_fis_numaralari', [
            'firma_id' => 1,
            'yil' => (int) now()->format('Y'),
            'prefix' => 'YB-SER',
            'son_sira' => 1009,
        ]);
    }

    private function testSemasiniKur(): void
    {
        Schema::dropIfExists('teknik_servis_fis_numaralari');
        Schema::dropIfExists('teknik_servis_kayitlari');

        Schema::create('teknik_servis_kayitlari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->string('fis_no')->unique();
            $table->softDeletes();
        });

        Schema::create('teknik_servis_fis_numaralari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedSmallInteger('yil');
            $table->string('prefix', 16);
            $table->unsignedInteger('son_sira')->default(0);
            $table->timestamps();

            $table->unique(['firma_id', 'yil', 'prefix'], 'ts_fis_test_firma_yil_prefix_unique');
        });
    }
}
