<?php

namespace Tests\Feature\TeknikServis;

use App\Livewire\TeknikServis\YapilanTahsilatlarTablosu;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Services\TenantContextService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class YapilanTahsilatlarTablosuCacheTest extends TestCase
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

        session([
            TenantContextService::SESSION_AKTIF_FIRMA_ID => 1,
            TenantContextService::SESSION_AKTIF_FIRMA_KODU => 'test',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('teknik_servis_muhasebe_baglantilari');
        Schema::dropIfExists('teknik_servis_tahsilatlari');
        Schema::dropIfExists('muhasebe_alacak_planlari');

        parent::tearDown();
    }

    public function test_tahsilat_tablosu_ozeti_ayni_render_icinde_toplam_sorgularini_tekrar_calistirmaz(): void
    {
        DB::table('teknik_servis_tahsilatlari')->insert([
            [
                'firma_id' => 1,
                'teknik_servis_kaydi_id' => 101,
                'tutar' => 75.50,
                'durum' => 'aktif',
            ],
            [
                'firma_id' => 1,
                'teknik_servis_kaydi_id' => 101,
                'tutar' => 20.00,
                'durum' => 'iptal',
            ],
        ]);

        $component = new YapilanTahsilatlarTablosu;
        $component->record = $this->servisKaydi();

        $method = new ReflectionMethod($component, 'tahsilatTablosuAciklamaMetni');
        $method->setAccessible(true);

        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $ilkOzet = (string) $method->invoke($component);
        $this->assertStringContainsString('75,50 TRY', $ilkOzet);
        $this->assertCount(4, $queries);

        $queries = [];
        $ikinciOzet = (string) $method->invoke($component);

        $this->assertSame($ilkOzet, $ikinciOzet);
        $this->assertCount(0, $queries);
    }

    private function servisKaydi(): TeknikServisKaydi
    {
        $record = new TeknikServisKaydi;
        $record->setRawAttributes([
            'id' => 101,
            'firma_id' => 1,
            'cari_id' => null,
            'toplam_tutar' => '250.00',
        ], true);
        $record->exists = true;
        $record->setRelation('cari', null);

        return $record;
    }

    private function testSemasiniKur(): void
    {
        Schema::dropIfExists('teknik_servis_muhasebe_baglantilari');
        Schema::dropIfExists('teknik_servis_tahsilatlari');

        Schema::create('teknik_servis_tahsilatlari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->decimal('tutar', 18, 2)->default(0);
            $table->string('durum')->default('aktif');
            $table->softDeletes();
        });

        Schema::create('teknik_servis_muhasebe_baglantilari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->string('islem_tipi');
            $table->unsignedBigInteger('satis_faturasi_id')->nullable();
        });

        Schema::create('muhasebe_alacak_planlari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->string('kaynak_turu');
            $table->unsignedBigInteger('kaynak_id');
            $table->string('durum')->default('aktif');
            $table->softDeletes();
        });
    }
}
