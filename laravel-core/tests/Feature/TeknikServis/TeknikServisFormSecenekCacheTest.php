<?php

namespace Tests\Feature\TeknikServis;

use App\Filament\Clusters\TeknikServis\Concerns\TeknikServisKayitFormSchema;
use App\Models\TeknikServis\TeknikServisCihazTanimi;
use App\Models\TeknikServis\TeknikServisMesajSablonu;
use App\Services\TenantContextService;
use App\TeknikServis\Servisler\TeknikServisFormSecenekCache;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class TeknikServisFormSecenekCacheTest extends TestCase
{
    /** @var array<int, string> */
    private array $sorgular = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Cache::flush();
        app(TeknikServisFormSecenekCache::class)->istekCacheTemizle();

        $this->testSemasiniKur();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('teknik_servis_tanim_cihazlar');
        Schema::dropIfExists('teknik_servis_tanim_markalar');
        Schema::dropIfExists('teknik_servis_tanim_aksesuarlar');
        Schema::dropIfExists('teknik_servis_tanim_arizalar');
        Schema::dropIfExists('teknik_servis_tanim_servis_durumlari');
        Schema::dropIfExists('teknik_servis_mesaj_sablonlari');
        Cache::flush();

        parent::tearDown();
    }

    public function test_tanim_secenekleri_kalici_cache_ile_okunur_ve_model_degisiminde_temizlenir(): void
    {
        TeknikServisCihazTanimi::query()->create([
            'firma_id' => null,
            'ad' => 'Kamera',
            'kod' => 'kamera',
            'aktif' => true,
            'siralama' => 1,
            'varsayilan_mi' => false,
        ]);

        $method = new ReflectionMethod(TeknikServisKayitFormSchema::class, 'cihazSecenekleri');
        $method->setAccessible(true);

        DB::listen(function (QueryExecuted $query): void {
            if (str_contains(strtolower($query->sql), 'teknik_servis_tanim_cihazlar')) {
                $this->sorgular[] = $query->sql;
            }
        });

        $this->sorgular = [];
        $ilkOkuma = $method->invoke(null);

        $this->assertSame(['Kamera'], array_values($ilkOkuma));
        $this->assertCount(1, $this->sorgular);

        $this->sorgular = [];
        $ikinciOkuma = $method->invoke(null);

        $this->assertSame($ilkOkuma, $ikinciOkuma);
        $this->assertCount(0, $this->sorgular);

        TeknikServisCihazTanimi::query()->create([
            'firma_id' => null,
            'ad' => 'NVR',
            'kod' => 'nvr',
            'aktif' => true,
            'siralama' => 2,
            'varsayilan_mi' => false,
        ]);

        $this->sorgular = [];
        $ucuncuOkuma = $method->invoke(null);

        $this->assertContains('NVR', array_values($ucuncuOkuma));
        $this->assertCount(1, $this->sorgular);
    }

    public function test_whatsapp_sablonlari_kalici_cache_ile_okunur_ve_model_degisiminde_temizlenir(): void
    {
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => 1]);

        TeknikServisMesajSablonu::query()->create([
            'firma_id' => 1,
            'kanal' => 'whatsapp',
            'kod' => 'teslim_bekleyen_mesaji',
            'ad' => 'Teslim bekleyen',
            'mesaj' => 'Hazir',
            'aktif' => true,
            'siralama' => 1,
        ]);

        $method = new ReflectionMethod(TeknikServisKayitFormSchema::class, 'whatsappSablonSecenekleri');
        $method->setAccessible(true);

        $sorgular = [];
        DB::listen(function (QueryExecuted $query) use (&$sorgular): void {
            if (str_contains(strtolower($query->sql), 'teknik_servis_mesaj_sablonlari')) {
                $sorgular[] = $query->sql;
            }
        });

        $ilkOkuma = $method->invoke(null);

        $this->assertSame(['teslim_bekleyen_mesaji' => 'Teslim bekleyen'], $ilkOkuma);
        $this->assertCount(1, $sorgular);

        $sorgular = [];
        $ikinciOkuma = $method->invoke(null);

        $this->assertSame($ilkOkuma, $ikinciOkuma);
        $this->assertCount(0, $sorgular);

        TeknikServisMesajSablonu::query()->create([
            'firma_id' => 1,
            'kanal' => 'whatsapp',
            'kod' => 'ikinci_sablon',
            'ad' => 'Ikinci sablon',
            'mesaj' => 'Merhaba',
            'aktif' => true,
            'siralama' => 2,
        ]);

        $sorgular = [];
        $ucuncuOkuma = $method->invoke(null);

        $this->assertSame('Ikinci sablon', $ucuncuOkuma['ikinci_sablon'] ?? null);
        $this->assertCount(1, $sorgular);
    }

    private function testSemasiniKur(): void
    {
        Schema::dropIfExists('teknik_servis_tanim_cihazlar');
        Schema::dropIfExists('teknik_servis_tanim_markalar');
        Schema::dropIfExists('teknik_servis_tanim_aksesuarlar');
        Schema::dropIfExists('teknik_servis_tanim_arizalar');
        Schema::dropIfExists('teknik_servis_tanim_servis_durumlari');
        Schema::dropIfExists('teknik_servis_mesaj_sablonlari');

        $this->tanimTablosuOlustur('teknik_servis_tanim_servis_durumlari', false);
        $this->tanimTablosuOlustur('teknik_servis_tanim_cihazlar');
        $this->tanimTablosuOlustur('teknik_servis_tanim_markalar');
        $this->tanimTablosuOlustur('teknik_servis_tanim_aksesuarlar');
        $this->tanimTablosuOlustur('teknik_servis_tanim_arizalar');
        $this->mesajSablonlariTablosuOlustur();
    }

    private function tanimTablosuOlustur(string $tablo, bool $softDeletes = true): void
    {
        Schema::create($tablo, function (Blueprint $table) use ($softDeletes): void {
            $table->id();
            $table->unsignedBigInteger('firma_id')->nullable();
            $table->string('ad');
            $table->string('kod')->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('siralama')->default(0);
            $table->boolean('varsayilan_mi')->default(false);
            $table->timestamps();
            if ($softDeletes) {
                $table->softDeletes();
            }
        });
    }

    private function mesajSablonlariTablosuOlustur(): void
    {
        Schema::create('teknik_servis_mesaj_sablonlari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id')->nullable();
            $table->string('kanal');
            $table->string('kod');
            $table->string('ad');
            $table->text('mesaj')->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('siralama')->default(0);
            $table->timestamps();
        });
    }
}
