<?php

namespace Tests\Feature\TeknikServis;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Services\TenantContextService;
use App\TeknikServis\Enumlar\ServisTipi;
use App\TeknikServis\Filament\TeknikServisDurumKodlari;
use App\TeknikServis\Filament\TeknikServisListePreset;
use App\TeknikServis\Filament\TeknikServisListePresetleri;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeknikServisListePresetleriTest extends TestCase
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

    public function test_durum_presetleri_indeks_dostu_servis_durumu_id_filtresi_kullanir(): void
    {
        $firma = $this->firmaOlustur('ts-a');
        $digerFirma = $this->firmaOlustur('ts-b');
        $kullanici = $this->kullaniciOlustur('teknik-a@example.test');

        $this->actingAs($kullanici);
        app(TenantContextService::class)->firmaAyarla($firma);

        $yeniDurum = $this->durumOlustur($firma, 'Yeni Kayıt', TeknikServisDurumKodlari::YENI);
        $teslimDurum = $this->durumOlustur($firma, 'Teslim Edilen', TeknikServisDurumKodlari::TESLIM_EDILDI, [
            'is_teslim_edildi' => true,
        ]);
        $digerFirmaYeniDurum = $this->durumOlustur($digerFirma, 'Yeni Kayıt', TeknikServisDurumKodlari::YENI);

        $cari = $this->cariOlustur($firma, 'CARI-A');
        $digerCari = $this->cariOlustur($digerFirma, 'CARI-B');

        $this->servisOlustur($firma, $cari, $yeniDurum, $kullanici, 'TS-001');
        $this->servisOlustur($firma, $cari, $teslimDurum, $kullanici, 'TS-002');
        $this->servisOlustur($digerFirma, $digerCari, $digerFirmaYeniDurum, $kullanici, 'TS-003');

        DB::enableQueryLog();
        DB::flushQueryLog();

        $sorgu = TeknikServisListePresetleri::uygula(
            TeknikServisKaydi::query()->where('firma_id', $firma->id),
            TeknikServisListePreset::Yeni
        );
        $sql = strtolower($sorgu->toSql());

        $this->assertStringContainsString('servis_durumu_id', $sql);
        $this->assertStringNotContainsString('exists', $sql);
        $this->assertSame(1, $sorgu->count());
        $this->assertSame(1, TeknikServisListePresetleri::uygula(
            TeknikServisKaydi::query()->where('firma_id', $firma->id),
            TeknikServisListePreset::Yeni
        )->count());

        $durumIdSorgulari = array_filter(DB::getQueryLog(), static function (array $sorgu): bool {
            $sql = strtolower((string) ($sorgu['query'] ?? ''));

            return str_contains($sql, 'teknik_servis_tanim_servis_durumlari')
                && str_contains($sql, 'select')
                && str_contains($sql, 'id');
        });

        $this->assertCount(1, $durumIdSorgulari);
    }

    public function test_tamamlanan_dis_servis_preseti_bayrak_kosulunu_korur(): void
    {
        $firma = $this->firmaOlustur('ts-dis');
        $kullanici = $this->kullaniciOlustur('teknik-dis@example.test');

        $this->actingAs($kullanici);
        app(TenantContextService::class)->firmaAyarla($firma);

        $teslimDurum = $this->durumOlustur($firma, 'Özel Teslim', 'ozel_teslim', [
            'is_teslim_edildi' => true,
        ]);
        $cari = $this->cariOlustur($firma, 'CARI-DIS');

        $this->servisOlustur($firma, $cari, $teslimDurum, $kullanici, 'TS-DIS-001', ServisTipi::DisServis);
        $this->servisOlustur($firma, $cari, $teslimDurum, $kullanici, 'TS-DIS-002', ServisTipi::ArizaliCihaz);

        $sorgu = TeknikServisListePresetleri::uygula(
            TeknikServisKaydi::query()->where('firma_id', $firma->id),
            TeknikServisListePreset::TamamlananDisServis
        );
        $sql = strtolower($sorgu->toSql());

        $this->assertStringContainsString('servis_durumu_id', $sql);
        $this->assertStringContainsString('servis_tipi', $sql);
        $this->assertStringNotContainsString('exists', $sql);
        $this->assertSame(1, $sorgu->count());
    }

    public function test_birden_fazla_preset_tek_durum_tanim_sorgusu_ile_cozulur(): void
    {
        $firma = $this->firmaOlustur('ts-coklu');
        $kullanici = $this->kullaniciOlustur('teknik-coklu@example.test');

        $this->actingAs($kullanici);
        app(TenantContextService::class)->firmaAyarla($firma);

        $yeniDurum = $this->durumOlustur($firma, 'Yeni Kayıt', TeknikServisDurumKodlari::YENI);
        $tezgahtaDurum = $this->durumOlustur($firma, 'Tezgahta', TeknikServisDurumKodlari::TEZGAHTA);
        $teslimDurum = $this->durumOlustur($firma, 'Teslim Edilen', TeknikServisDurumKodlari::TESLIM_EDILDI, [
            'is_teslim_edildi' => true,
        ]);
        $iptalDurum = $this->durumOlustur($firma, 'İptal', TeknikServisDurumKodlari::IPTAL, [
            'is_iptal' => true,
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertSame([$yeniDurum->id], TeknikServisListePresetleri::durumIdleriPresetIcin(TeknikServisListePreset::Yeni));
        $this->assertContains($tezgahtaDurum->id, TeknikServisListePresetleri::durumIdleriPresetIcin(TeknikServisListePreset::Acik));
        $this->assertSame([$teslimDurum->id], TeknikServisListePresetleri::durumIdleriPresetIcin(TeknikServisListePreset::TeslimEdilen));
        $this->assertSame([$iptalDurum->id], TeknikServisListePresetleri::durumIdleriPresetIcin(TeknikServisListePreset::Iptal));

        $durumTanimSorgulari = array_filter(DB::getQueryLog(), static function (array $sorgu): bool {
            $sql = strtolower((string) ($sorgu['query'] ?? ''));

            return str_contains($sql, 'teknik_servis_tanim_servis_durumlari')
                && str_contains($sql, 'select')
                && str_contains($sql, 'id');
        });

        $this->assertCount(1, $durumTanimSorgulari);
    }

    private function firmaOlustur(string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => strtoupper($kod),
            'firma_kodu' => $kod,
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function kullaniciOlustur(string $email): User
    {
        return User::query()->create([
            'name' => $email,
            'email' => $email,
            'password' => 'password',
            'super_admin_mi' => false,
        ]);
    }

    private function cariOlustur(Firma $firma, string $kod): Cari
    {
        return Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => $kod,
            'ad' => $kod,
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
    }

    /**
     * @param  array<string, mixed>  $ekVeri
     */
    private function durumOlustur(Firma $firma, string $ad, string $kod, array $ekVeri = []): TeknikServisDurumTanimi
    {
        return TeknikServisDurumTanimi::query()->create(array_merge([
            'firma_id' => $firma->id,
            'ad' => $ad,
            'kod' => $kod,
            'aktif' => true,
            'siralama' => 1,
        ], $ekVeri));
    }

    private function servisOlustur(
        Firma $firma,
        Cari $cari,
        TeknikServisDurumTanimi $durum,
        User $kullanici,
        string $fisNo,
        ServisTipi $servisTipi = ServisTipi::ArizaliCihaz,
    ): TeknikServisKaydi {
        return TeknikServisKaydi::query()->create([
            'firma_id' => $firma->id,
            'servis_tipi' => $servisTipi->value,
            'oncelik' => 'normal',
            'servis_kanali' => 'magaza',
            'cari_id' => $cari->id,
            'musteri_sikayeti' => 'Test arıza kaydı',
            'kabul_tarihi' => now(),
            'fis_no' => $fisNo,
            'servis_durumu_id' => $durum->id,
            'toplam_tutar' => 0,
            'odenen_tutar' => 0,
            'odeme_durumu' => 'odenmedi',
            'olusturan_id' => $kullanici->id,
        ]);
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
            $table->rememberToken();
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
            $table->string('durum')->default(CariDurumu::Aktif->value);
            $table->char('para_birimi', 3)->default('TRY');
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
            $table->unsignedBigInteger('cari_id');
            $table->text('musteri_sikayeti');
            $table->dateTime('kabul_tarihi');
            $table->string('fis_no')->unique();
            $table->unsignedBigInteger('servis_durumu_id');
            $table->decimal('toplam_tutar', 18, 2)->default(0);
            $table->decimal('odenen_tutar', 18, 2)->default(0);
            $table->string('odeme_durumu')->default('odenmedi');
            $table->unsignedBigInteger('olusturan_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'servis_durumu_id', 'deleted_at', 'kabul_tarihi'], 'ts_test_firma_durum_sil_kabul_idx');
            $table->index(['firma_id', 'servis_tipi', 'deleted_at', 'kabul_tarihi'], 'ts_test_firma_tip_sil_kabul_idx');
        });
    }

    private function testSemasiniTemizle(): void
    {
        Schema::dropIfExists('teknik_servis_kayitlari');
        Schema::dropIfExists('teknik_servis_tanim_servis_durumlari');
        Schema::dropIfExists('cariler');
        Schema::dropIfExists('firmalar');
        Schema::dropIfExists('users');
    }
}
