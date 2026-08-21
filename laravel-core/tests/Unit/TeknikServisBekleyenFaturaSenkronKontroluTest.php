<?php

namespace Tests\Unit;

use App\Models\Muhasebe\Fatura;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\TeknikServis\Servisler\TeknikServisBekleyenFaturaSenkronKontrolu;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeknikServisBekleyenFaturaSenkronKontroluTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('teknik_servis_tahsilatlari');
        Schema::dropIfExists('fatura_kalemleri');
        Schema::dropIfExists('faturalar');

        parent::tearDown();
    }

    public function test_fatura_ve_kalemler_ayniysa_senkron_gereksiz_sayilir(): void
    {
        DB::table('faturalar')->insert($this->faturaSatiri());
        DB::table('fatura_kalemleri')->insert($this->kalemSatiri());

        $fatura = Fatura::query()->withoutGlobalScopes()->firstOrFail();
        $kontrol = app(TeknikServisBekleyenFaturaSenkronKontrolu::class);

        $this->assertTrue($kontrol->faturaVeKalemlerAyniMi(
            $fatura,
            $this->beklenenFaturaAlanlari(),
            [$this->beklenenKalem()],
            1,
            'TRY'
        ));

        DB::table('fatura_kalemleri')->where('id', 1)->update(['miktar' => '2.0000']);

        $this->assertFalse($kontrol->faturaVeKalemlerAyniMi(
            $fatura,
            $this->beklenenFaturaAlanlari(),
            [$this->beklenenKalem()],
            1,
            'TRY'
        ));
    }

    public function test_tahsilat_fatura_baglantisi_eksikse_senkron_atlanmaz(): void
    {
        DB::table('teknik_servis_tahsilatlari')->insert([
            'id' => 1,
            'firma_id' => 1,
            'teknik_servis_kaydi_id' => 10,
            'satis_faturasi_id' => null,
        ]);

        $kontrol = app(TeknikServisBekleyenFaturaSenkronKontrolu::class);

        $this->assertTrue($kontrol->tahsilatFaturaBaglantisiEksikMi(1, 10, 5));
    }

    private function semayiKur(): void
    {
        Schema::create('faturalar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('cari_id');
            $table->string('tur');
            $table->string('durum');
            $table->dateTime('tarih')->nullable();
            $table->char('para_birimi', 3)->default('TRY');
            $table->decimal('ara_toplam', 18, 2)->default(0);
            $table->decimal('toplam_indirim', 18, 2)->default(0);
            $table->decimal('kdv_toplam', 18, 2)->default(0);
            $table->decimal('genel_toplam', 18, 2)->default(0);
            $table->decimal('odenecek_tutar', 18, 2)->default(0);
            $table->decimal('odendi_tutari', 18, 2)->default(0);
            $table->decimal('acik_tutar', 18, 2)->default(0);
            $table->string('odeme_durumu')->nullable();
            $table->text('aciklama')->nullable();
            $table->string('kaynak_tipi')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fatura_kalemleri', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('fatura_id');
            $table->unsignedInteger('satir_no');
            $table->string('kalem_tipi');
            $table->unsignedBigInteger('stok_id')->nullable();
            $table->string('birim')->nullable();
            $table->boolean('hizmet_mi')->default(false);
            $table->text('aciklama')->nullable();
            $table->decimal('miktar', 18, 4)->default(0);
            $table->decimal('birim_fiyat', 18, 2)->default(0);
            $table->decimal('baz_birim_fiyat', 18, 2)->default(0);
            $table->decimal('indirim_orani', 18, 4)->default(0);
            $table->decimal('kdv_orani', 18, 2)->default(0);
            $table->decimal('satir_indirim_tutari', 18, 2)->default(0);
            $table->decimal('indirim_tutari', 18, 2)->default(0);
            $table->decimal('baz_indirim_tutari', 18, 2)->default(0);
            $table->decimal('net_tutar', 18, 2)->default(0);
            $table->decimal('baz_net_tutar', 18, 2)->default(0);
            $table->decimal('kdv_tutari', 18, 2)->default(0);
            $table->decimal('baz_kdv_tutari', 18, 2)->default(0);
            $table->decimal('satir_toplami', 18, 2)->default(0);
            $table->decimal('baz_satir_toplami', 18, 2)->default(0);
            $table->decimal('satir_genel_toplam', 18, 2)->default(0);
            $table->decimal('baz_satir_genel_toplam', 18, 2)->default(0);
            $table->decimal('toplam', 18, 2)->default(0);
            $table->char('para_birimi', 3)->default('TRY');
            $table->char('baz_para_birimi', 3)->default('TRY');
            $table->timestamps();
        });

        Schema::create('teknik_servis_tahsilatlari', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('teknik_servis_kaydi_id');
            $table->unsignedBigInteger('satis_faturasi_id')->nullable();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function faturaSatiri(): array
    {
        return array_merge($this->beklenenFaturaAlanlari(), [
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function beklenenFaturaAlanlari(): array
    {
        return [
            'firma_id' => 1,
            'cari_id' => 7,
            'tur' => FaturaTuru::BekleyenFatura->value,
            'durum' => FaturaDurumu::Beklemede->value,
            'tarih' => Carbon::parse('2026-05-30 10:00:00'),
            'para_birimi' => 'TRY',
            'ara_toplam' => '100.00',
            'toplam_indirim' => '0.00',
            'kdv_toplam' => '20.00',
            'genel_toplam' => '120.00',
            'odenecek_tutar' => '120.00',
            'odendi_tutari' => '0.00',
            'acik_tutar' => '120.00',
            'odeme_durumu' => 'beklemede',
            'aciklama' => 'Teknik servis kaydı #10 için otomatik bekleyen fatura.',
            'kaynak_tipi' => 'teknik_servis',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kalemSatiri(): array
    {
        return array_merge($this->beklenenKalem(), [
            'id' => 1,
            'fatura_id' => 1,
            'firma_id' => 1,
            'para_birimi' => 'TRY',
            'baz_para_birimi' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function beklenenKalem(): array
    {
        return [
            'satir_no' => 1,
            'kalem_tipi' => 'stok',
            'stok_id' => 3,
            'birim' => 'AD',
            'hizmet_mi' => false,
            'aciklama' => 'Test stok',
            'miktar' => '1.0000',
            'birim_fiyat' => '100.00',
            'baz_birim_fiyat' => '100.00',
            'indirim_orani' => '0.0000',
            'kdv_orani' => '20.00',
            'satir_indirim_tutari' => '0.00',
            'indirim_tutari' => '0.00',
            'baz_indirim_tutari' => '0.00',
            'net_tutar' => '100.00',
            'baz_net_tutar' => '100.00',
            'kdv_tutari' => '20.00',
            'baz_kdv_tutari' => '20.00',
            'satir_toplami' => '100.00',
            'baz_satir_toplami' => '100.00',
            'satir_genel_toplam' => '120.00',
            'baz_satir_genel_toplam' => '120.00',
            'toplam' => '120.00',
        ];
    }
}
