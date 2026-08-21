<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokOlcusu;
use App\Models\Yetki;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\StokOlcuBakiyeServisi;
use Database\Seeders\MuhasebeOlcuBirimleriSeeder;
use Database\Seeders\SaasPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class StokOlcuFaz21AtomiklikTest extends TestCase
{
    use RefreshDatabase;

    public function test_olcu_hatasi_kart_ve_olcu_kaydini_geri_alir(): void
    {
        [$firma, $depo, $stok] = $this->olculuStok();
        try {
            DB::transaction(function () use ($firma, $stok): void {
                app(StokOlcuBakiyeServisi::class)->olcuOlustur($firma->id, $stok, [
                    'kod' => 'ILK', 'ad' => 'İlk ölçü', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200',
                ]);
                throw new InvalidArgumentException('kontrollü ölçü hatası');
            });
        } catch (InvalidArgumentException) {
        }

        self::assertSame(0, StokOlcusu::query()->where('stok_id', $stok->id)->count());
        self::assertDatabaseHas('stok_kartlari', ['id' => $stok->id]);
    }

    public function test_ikinci_olcu_hatasi_ilk_olcuyu_da_geri_alir(): void
    {
        [$firma, $depo, $stok] = $this->olculuStok();
        try {
            DB::transaction(function () use ($firma, $stok): void {
                app(StokOlcuBakiyeServisi::class)->olcuOlustur($firma->id, $stok, [
                    'kod' => 'ILK', 'ad' => 'İlk ölçü', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200',
                ]);
                app(StokOlcuBakiyeServisi::class)->olcuOlustur($firma->id, $stok, [
                    'kod' => 'ILK', 'ad' => 'Mükerrer ölçü', 'olcu_birimi' => 'cm', 'en' => '100', 'boy' => '100',
                ]);
            });
        } catch (\Throwable) {
        }

        self::assertSame(0, StokOlcusu::query()->where('stok_id', $stok->id)->count());
    }

    public function test_tenant_uyumsuz_olcu_transactioni_yazilamaz(): void
    {
        [$firma, $depo, $stok] = $this->olculuStok();
        $digerFirma = Firma::create(['ad' => 'Diğer', 'kisa_ad' => 'DGR', 'firma_kodu' => 'DGR-'.uniqid(), 'durum' => Firma::DURUM_AKTIF, 'onaylandi_mi' => true]);
        $this->expectException(InvalidArgumentException::class);
        app(StokOlcuBakiyeServisi::class)->olcuOlustur($digerFirma->id, $stok, ['kod' => 'TENANT', 'ad' => 'Uyumsuz', 'olcu_birimi' => 'cm', 'en' => '10', 'boy' => '10']);
    }

    public function test_olcu_bakiye_giris_tek_transactionda_tutarlidir(): void
    {
        [$firma, $depo, $stok] = $this->olculuStok();
        $servis = app(StokOlcuBakiyeServisi::class);
        $olcu = $servis->olcuOlustur($firma->id, $stok, ['kod' => '200X200', 'ad' => '200 × 200', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200']);
        $bakiye = $servis->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu, $depo);
        DB::transaction(fn () => $servis->giris($bakiye, adet: '3'));
        self::assertSame('12.00000000', $bakiye->refresh()->ana_miktar);
        self::assertSame('3.00000000', $bakiye->adet_esdegeri);
    }

    public function test_olcu_izinleri_idempotent_seed_edilir(): void
    {
        $this->seed(SaasPermissionsSeeder::class);
        $this->seed(SaasPermissionsSeeder::class);

        self::assertSame(1, Yetki::query()->where('kod', 'stok_olcu.goruntule')->count());
        self::assertSame(1, Yetki::query()->where('kod', 'stok_olcu.olustur')->count());
        self::assertSame(1, Yetki::query()->where('kod', 'stok_olcu.guncelle')->count());
    }

    private function olculuStok(): array
    {
        $firma = Firma::create(['ad' => 'Atomiklik', 'kisa_ad' => 'ATM', 'firma_kodu' => 'ATM-'.uniqid(), 'durum' => Firma::DURUM_AKTIF, 'onaylandi_mi' => true]);
        $depo = Depo::create(['firma_id' => $firma->id, 'kod' => 'D1', 'ad' => 'Depo', 'aktif_mi' => true]);
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $stok = StokKarti::create([
            'firma_id' => $firma->id, 'kod' => 'ATM-'.uniqid(), 'ad' => 'Ölçülü stok', 'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value, 'stok_takip' => true, 'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_BASIT,
            'depo_id' => $depo->id, 'para_birimi' => 'TRY', 'olculu_takip_turu' => 'alan',
            'ana_birim_id' => Birim::withoutGlobalScopes()->where('kod', 'MTK')->value('id'),
            'ikincil_birim_id' => Birim::withoutGlobalScopes()->where('kod', 'AD')->value('id'),
        ]);
        return [$firma, $depo, $stok];
    }
}
