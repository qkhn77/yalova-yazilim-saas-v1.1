<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokKarti;
use App\Models\User;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Muhasebe\Servisler\StokOlcuBakiyeServisi;
use App\Services\TenantContextService;
use Database\Seeders\MuhasebeOlcuBirimleriSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StokOlcuVeriModeliTest extends TestCase
{
    use RefreshDatabase;

    public function test_sistem_birimleri_idempotent_ve_sabittir(): void
    {
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        self::assertSame(5, Birim::withoutGlobalScopes()->whereIn('kod', ['AD', 'MTR', 'MTK', 'MTQ', 'KGM'])->count());
        self::assertSame(5, Birim::withoutGlobalScopes()->where('is_sabit', true)->whereNotNull('gib_birim_kodu')->whereIn('kod', ['AD', 'MTR', 'MTK', 'MTQ', 'KGM'])->count());
    }

    public function test_legacy_adet_ve_kilo_aliaslari_korunur_ve_semantik_mukerrer_uretilmez(): void
    {
        $adet = Birim::withoutGlobalScopes()->create([
            'firma_id' => null,
            'kod' => 'ADET',
            'ad' => 'Adet',
            'gib_birim_kodu' => null,
            'aktif_mi' => true,
            'is_sabit' => true,
            'varsayilan_mi' => false,
        ]);
        $kilo = Birim::withoutGlobalScopes()->create([
            'firma_id' => null,
            'kod' => 'KILO',
            'ad' => 'Kilo',
            'gib_birim_kodu' => null,
            'aktif_mi' => true,
            'is_sabit' => true,
            'varsayilan_mi' => false,
        ]);

        $this->seed(MuhasebeOlcuBirimleriSeeder::class);

        $adet->refresh();
        $kilo->refresh();
        self::assertSame($adet->id, Birim::withoutGlobalScopes()->where('kod', 'ADET')->value('id'));
        self::assertSame($kilo->id, Birim::withoutGlobalScopes()->where('kod', 'KILO')->value('id'));
        self::assertNull($adet->gib_birim_kodu);
        self::assertNull($kilo->gib_birim_kodu);
        self::assertSame('C62', Birim::withoutGlobalScopes()->where('kod', 'AD')->value('gib_birim_kodu'));
        self::assertSame('KGM', Birim::withoutGlobalScopes()->where('kod', 'KGM')->value('gib_birim_kodu'));
        self::assertSame(1, Birim::withoutGlobalScopes()->where('kod', 'AD')->count());
        self::assertSame(1, Birim::withoutGlobalScopes()->where('kod', 'KGM')->count());
    }

    public function test_standart_stok_olcusuz_calisir_ve_olcu_eklenemez(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $stok = $this->stok($firma->id, $depo->id);
        self::assertSame('standart', $stok->olculu_takip_turu->value);
        $this->expectException(InvalidArgumentException::class);
        app(StokOlcuBakiyeServisi::class)->olcuOlustur($firma->id, $stok, ['kod' => 'STD', 'ad' => 'Standart']);
    }



    public function test_bakiyesi_olan_stokta_olcu_yapisi_degistirilemez(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $adet = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $m2 = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $stok = $this->stok($firma->id, $depo->id, [
            'olculu_takip_turu' => 'alan',
            'olcu_yapisi' => 'coklu',
            'ana_birim_id' => $m2->id,
            'ikincil_birim_id' => $adet->id,
        ]);
        $servis = app(StokOlcuBakiyeServisi::class);
        $olcu = $servis->olcuOlustur($firma->id, $stok, [
            'kod' => '200X200', 'ad' => '200 × 200 cm', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200',
        ]);
        $bakiye = $servis->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu, $depo);
        $servis->giris($bakiye, adet: '1');

        $stok->olcu_yapisi = 'sabit';
        $this->expectException(InvalidArgumentException::class);
        $stok->save();
    }

    public function test_olculu_stok_ile_seri_takibi_birlikte_reddedilir(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $this->expectException(InvalidArgumentException::class);
        $this->stok($firma->id, $depo->id, ['stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_SERI, 'olculu_takip_turu' => 'uzunluk', 'ana_birim_id' => Birim::withoutGlobalScopes()->where('kod', 'MTR')->value('id'), 'ikincil_birim_id' => Birim::withoutGlobalScopes()->where('kod', 'AD')->value('id')]);
    }



























    private function temelKayitlar(): array
    {
        $firma = Firma::create(['ad' => 'Ölçü Test', 'kisa_ad' => 'OT', 'firma_kodu' => 'OT-'.uniqid(), 'durum' => Firma::DURUM_AKTIF, 'onaylandi_mi' => true]);
        $depo = Depo::create(['firma_id' => $firma->id, 'kod' => 'D1', 'ad' => 'Depo', 'aktif_mi' => true]);

        return [$firma, $depo];
    }

    private function stok(int $firmaId, int $depoId, array $ek = []): StokKarti
    {
        return StokKarti::create(array_merge(['firma_id' => $firmaId, 'kod' => 'S-'.uniqid(), 'ad' => 'Stok', 'tur' => StokKartiTuru::TicariMal->value, 'durum' => HesapDurumu::Aktif->value, 'stok_takip' => true, 'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_BASIT, 'depo_id' => $depoId, 'para_birimi' => 'TRY'], $ek));
    }
}
