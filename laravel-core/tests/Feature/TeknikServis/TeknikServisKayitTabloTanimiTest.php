<?php

namespace Tests\Feature\TeknikServis;

use App\Filament\Clusters\TeknikServis\Concerns\TeknikServisKayitTabloTanimi;
use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\TeknikServis\TeknikServisCihazTanimi;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisMarkaTanimi;
use App\Models\User;
use App\Services\TenantContextService;
use App\TeknikServis\Enumlar\ServisTipi;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeknikServisKayitTabloTanimiTest extends TestCase
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
        $this->testSemasiniTemizle();

        parent::tearDown();
    }

    public function test_liste_sorgusu_iliskileri_tek_sql_ile_alias_olarak_getirir(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'TS Liste Firma',
            'firma_kodu' => 'ts-liste',
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
        $kullanici = User::query()->create([
            'name' => 'TS Liste Kullanici',
            'email' => 'ts-liste@example.test',
            'password' => 'password',
            'super_admin_mi' => false,
        ]);

        $this->actingAs($kullanici);
        app(TenantContextService::class)->firmaAyarla($firma);

        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'CARI-001',
            'ad' => 'Ali Test',
            'tur' => 'musteri',
            'durum' => 'aktif',
            'para_birimi' => 'TRY',
        ]);
        $cihaz = TeknikServisCihazTanimi::query()->create([
            'firma_id' => $firma->id,
            'ad' => 'Kamera',
            'kod' => 'KAMERA',
            'aktif' => true,
            'siralama' => 1,
        ]);
        $marka = TeknikServisMarkaTanimi::query()->create([
            'firma_id' => $firma->id,
            'ad' => 'Hikvision',
            'kod' => 'HIK',
            'aktif' => true,
            'siralama' => 1,
        ]);
        $durum = TeknikServisDurumTanimi::query()->create([
            'firma_id' => $firma->id,
            'ad' => 'Tezgahta',
            'kod' => 'tezgahta',
            'aktif' => true,
            'siralama' => 1,
        ]);
        $servis = TeknikServisKaydi::query()->create([
            'firma_id' => $firma->id,
            'servis_tipi' => ServisTipi::ArizaliCihaz->value,
            'oncelik' => 'normal',
            'servis_kanali' => 'magaza',
            'cari_id' => $cari->id,
            'musteri_tel' => '05321234567',
            'cihaz_id' => $cihaz->id,
            'marka_id' => $marka->id,
            'musteri_sikayeti' => 'Goruntu yok',
            'kabul_tarihi' => now(),
            'fis_no' => 'TS-JOIN-001',
            'servis_durumu_id' => $durum->id,
            'toplam_tutar' => 0,
            'odenen_tutar' => 0,
            'odeme_durumu' => 'odenmedi',
            'olusturan_id' => $kullanici->id,
        ]);

        $sorgu = TeknikServisKayitTabloTanimi::listeSorgusunuOptimizeEt(TeknikServisKaydi::query());

        $this->assertSame([], $sorgu->getEagerLoads());
        $this->assertStringContainsString('left join', strtolower($sorgu->toSql()));

        DB::enableQueryLog();
        DB::flushQueryLog();

        $kayit = $sorgu
            ->where('teknik_servis_kayitlari.id', $servis->id)
            ->firstOrFail();

        $this->assertSame('Ali Test', $kayit->getAttribute('cari_adi'));
        $this->assertSame('Kamera', $kayit->getAttribute('cihaz_adi'));
        $this->assertSame('Hikvision', $kayit->getAttribute('marka_adi'));
        $this->assertSame('Tezgahta', $kayit->getAttribute('servis_durumu_adi'));

        $selectSorgulari = array_filter(DB::getQueryLog(), static function (array $sorgu): bool {
            return str_starts_with(strtolower((string) ($sorgu['query'] ?? '')), 'select');
        });

        $this->assertCount(1, $selectSorgulari);
    }

    private function testSemasiniKur(): void
    {
        $this->testSemasiniTemizle();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('super_admin_mi')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('firmalar', function (Blueprint $table): void {
            $table->id();
            $table->string('ad');
            $table->string('firma_kodu')->unique();
            $table->string('durum')->default(Firma::DURUM_AKTIF);
            $table->boolean('onaylandi_mi')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cariler', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->string('kod');
            $table->string('ad');
            $table->string('tur');
            $table->string('durum')->default('aktif');
            $table->char('para_birimi', 3)->default('TRY');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('teknik_servis_tanim_cihazlar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id')->nullable();
            $table->string('ad');
            $table->string('kod')->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('siralama')->default(0);
            $table->boolean('varsayilan_mi')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('teknik_servis_tanim_markalar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id')->nullable();
            $table->string('ad');
            $table->string('kod')->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('siralama')->default(0);
            $table->boolean('varsayilan_mi')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('teknik_servis_tanim_servis_durumlari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id')->nullable();
            $table->string('ad');
            $table->string('kod')->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('siralama')->default(0);
            $table->boolean('varsayilan_mi')->default(false);
            $table->boolean('is_fiyat_verildi')->default(false);
            $table->boolean('is_teslim_edildi')->default(false);
            $table->boolean('is_iptal')->default(false);
            $table->boolean('is_iade')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('teknik_servis_kayitlari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->string('servis_tipi');
            $table->string('oncelik')->default('normal');
            $table->string('servis_kanali')->default('magaza');
            $table->unsignedBigInteger('cari_id')->nullable();
            $table->string('musteri_tel')->nullable();
            $table->unsignedBigInteger('cihaz_id')->nullable();
            $table->unsignedBigInteger('marka_id')->nullable();
            $table->text('musteri_sikayeti');
            $table->dateTime('kabul_tarihi');
            $table->string('fis_no')->unique();
            $table->unsignedBigInteger('servis_durumu_id')->nullable();
            $table->decimal('toplam_tutar', 18, 2)->default(0);
            $table->decimal('odenen_tutar', 18, 2)->default(0);
            $table->string('odeme_durumu')->default('odenmedi');
            $table->dateTime('teslim_tarihi')->nullable();
            $table->decimal('teklif_tutari', 18, 2)->nullable();
            $table->unsignedBigInteger('olusturan_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function testSemasiniTemizle(): void
    {
        Schema::dropIfExists('teknik_servis_kayitlari');
        Schema::dropIfExists('teknik_servis_tanim_servis_durumlari');
        Schema::dropIfExists('teknik_servis_tanim_markalar');
        Schema::dropIfExists('teknik_servis_tanim_cihazlar');
        Schema::dropIfExists('cariler');
        Schema::dropIfExists('firmalar');
        Schema::dropIfExists('users');
    }
}
