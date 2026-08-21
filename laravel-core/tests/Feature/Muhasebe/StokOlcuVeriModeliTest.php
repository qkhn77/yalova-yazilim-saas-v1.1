<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokParcaIslemLogu;
use App\Models\Muhasebe\StokParcasi;
use App\Models\User;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Muhasebe\Servisler\StokOlcuBakiyeServisi;
use App\Muhasebe\Servisler\StokParcaDonusumServisi;
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
        self::assertSame('C62', $adet->gib_birim_kodu);
        self::assertSame('KGM', $kilo->gib_birim_kodu);
        self::assertSame(0, Birim::withoutGlobalScopes()->whereIn('kod', ['AD', 'KGM'])->count());
    }

    public function test_standart_stok_olcusuz_calisir_ve_olcu_eklenemez(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $stok = $this->stok($firma->id, $depo->id);
        self::assertSame('standart', $stok->olculu_takip_turu->value);
        $this->expectException(InvalidArgumentException::class);
        app(StokOlcuBakiyeServisi::class)->olcuOlustur($firma->id, $stok, ['kod' => 'STD', 'ad' => 'Standart']);
    }

    public function test_alan_olcusu_sunucuda_normalize_edilir_ve_bakiye_partisiz_tekildir(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $adet = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $m2 = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $stok = $this->stok($firma->id, $depo->id, ['olculu_takip_turu' => 'alan', 'olcu_yapisi' => 'coklu', 'ana_birim_id' => $m2->id, 'ikincil_birim_id' => $adet->id]);
        $servis = app(StokOlcuBakiyeServisi::class);
        $olcu = $servis->olcuOlustur($firma->id, $stok, ['kod' => '200X200', 'ad' => '200 × 200 cm', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200', 'bir_adet_ana_miktar' => '999']);
        self::assertSame('4.00000000', $olcu->bir_adet_ana_miktar);
        $b1 = $servis->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu, $depo);
        $b2 = $servis->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu, $depo);
        self::assertSame($b1->id, $b2->id);
        $bakiye = $servis->giris($b1, adet: '1');
        self::assertSame('4.00000000', $bakiye->ana_miktar);
        $bakiye = $servis->cikis($bakiye, anaMiktar: '1');
        self::assertSame('3.00000000', $bakiye->ana_miktar);
        self::assertSame('0.75000000', $bakiye->adet_esdegeri);
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

    public function test_standart_parti_stogu_fiziksel_parcalara_bolunur(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI,
            'stok_miktari' => '100',
            'guncel_birim_maliyet' => '12.50',
        ]);

        $sonuc = app(StokParcaDonusumServisi::class)->donustur($stok, [
            ['ana_miktar' => '50'], ['ana_miktar' => '50'],
        ]);

        self::assertCount(2, $sonuc['parcalar']);
        self::assertSame('0.00000000', (string) $sonuc['ana_parca']->kalan_miktar);
        self::assertSame('50.00000000', (string) $sonuc['parcalar'][0]->kalan_miktar);
        self::assertSame('12.50000000', (string) $sonuc['parcalar'][0]->birim_maliyet);
        self::assertTrue((bool) $sonuc['parcalar'][0]->parca_mi);
    }

    public function test_mevcut_ana_parcanin_yalniz_kalan_bakiyesi_parcalara_donusturulur(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI,
            'stok_miktari' => '3',
            'guncel_birim_maliyet' => '25',
        ]);
        $anaParca = StokParcasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'stok_id' => $stok->id,
            'depo_id' => $depo->id,
            'parca_kodu' => 'ANA-2026-01',
            'blok_no' => 'BLK-7',
            'ocak_tedarikci' => 'Test Ocağı',
            'renk_desen' => 'Bulutlu',
            'kalite_sinifi' => 'A',
            'birim_maliyet' => '25',
            'giren_miktar' => '4',
            'kalan_miktar' => '3',
        ]);

        $servis = app(StokParcaDonusumServisi::class);
        $oneri = $servis->partiDonusumOnerisi($anaParca, 3);
        self::assertCount(3, $oneri);
        self::assertSame('3.00000000', collect($oneri)->reduce(
            fn (string $toplam, array $satir): string => bcadd($toplam, $satir['ana_miktar'], 8),
            '0',
        ));

        $sonuc = $servis->partiyiDonustur($anaParca, $oneri);

        self::assertSame('4.00000000', (string) $sonuc['ana_parca']->giren_miktar);
        self::assertSame('0.00000000', (string) $sonuc['ana_parca']->kalan_miktar);
        self::assertSame('donusturuldu', $sonuc['ana_parca']->parca_durumu);
        self::assertCount(3, $sonuc['parcalar']);
        self::assertTrue($sonuc['parcalar']->every(fn (StokParcasi $parca): bool => $parca->ust_parca_id === $anaParca->id
            && $parca->blok_no === 'BLK-7'
            && $parca->renk_desen === 'Bulutlu'
            && $parca->kalite_sinifi === 'A'
            && (string) $parca->birim_maliyet === '25.00000000'
        ));
    }

    public function test_olculu_parti_stogu_donusumda_unassigned_bakiyeyi_parcalara_tasır(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $adet = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $m2 = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI,
            'olculu_takip_turu' => 'alan', 'olcu_yapisi' => 'coklu',
            'ana_birim_id' => $m2->id, 'ikincil_birim_id' => $adet->id,
        ]);
        $olcuServisi = app(StokOlcuBakiyeServisi::class);
        $olcu = $olcuServisi->olcuOlustur($firma->id, $stok, [
            'kod' => '200X200', 'ad' => '200 × 200 cm', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200',
        ]);
        $kaynak = $olcuServisi->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu, $depo);
        $olcuServisi->giris($kaynak, adet: '10');
        self::assertSame($olcu->id, (int) $kaynak->refresh()->stok_olcusu_id);

        $sonuc = app(StokParcaDonusumServisi::class)->donustur($stok, [
            ['ana_miktar' => '20'], ['ana_miktar' => '20'],
        ]);

        self::assertSame('0.00000000', (string) $kaynak->refresh()->ana_miktar);
        $parcaBakiyesi = $sonuc['parcalar'][0]->olcuBakiyeleri()->withoutGlobalScopes()->firstOrFail();
        self::assertSame('20.00000000', (string) $parcaBakiyesi->ana_miktar);
        self::assertSame($sonuc['parcalar'][0]->id, $parcaBakiyesi->stok_parcasi_id);
        self::assertSame(2, StokParcasi::withoutGlobalScopes()->where('stok_id', $stok->id)->where('parca_mi', true)->count());
    }

    public function test_coklu_olcu_bakiyeleri_kendi_fiziksel_parcalarına_ayri_ayri_aktarilir(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $adet = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $m2 = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI,
            'olculu_takip_turu' => 'alan', 'olcu_yapisi' => 'coklu',
            'ana_birim_id' => $m2->id, 'ikincil_birim_id' => $adet->id,
        ]);
        $bakiyeServisi = app(StokOlcuBakiyeServisi::class);
        $olcuBuyuk = $bakiyeServisi->olcuOlustur($firma->id, $stok, [
            'kod' => '200X200', 'ad' => '200 × 200 cm', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200',
        ]);
        $olcuKucuk = $bakiyeServisi->olcuOlustur($firma->id, $stok, [
            'kod' => '100X200', 'ad' => '100 × 200 cm', 'olcu_birimi' => 'cm', 'en' => '100', 'boy' => '200',
        ]);
        $buyukBakiye = $bakiyeServisi->bakiyeBulVeyaOlustur($firma->id, $stok, $olcuBuyuk, $depo);
        $kucukBakiye = $bakiyeServisi->bakiyeBulVeyaOlustur($firma->id, $stok, $olcuKucuk, $depo);
        $bakiyeServisi->giris($buyukBakiye, adet: '10');
        $bakiyeServisi->giris($kucukBakiye, adet: '10');

        $donusumServisi = app(StokParcaDonusumServisi::class);
        $oneri = $donusumServisi->donusumOnerisi($stok, 3);
        self::assertCount(3, $oneri);
        self::assertSame('40.00000000', collect($oneri)->where('stok_olcu_bakiyesi_id', $buyukBakiye->id)->reduce(
            fn (string $toplam, array $satir): string => bcadd($toplam, $satir['ana_miktar'], 8),
            '0',
        ));
        self::assertSame('20.00000000', collect($oneri)->where('stok_olcu_bakiyesi_id', $kucukBakiye->id)->reduce(
            fn (string $toplam, array $satir): string => bcadd($toplam, $satir['ana_miktar'], 8),
            '0',
        ));

        $sonuc = $donusumServisi->donustur($stok, $oneri);

        self::assertSame('0.00000000', (string) $buyukBakiye->refresh()->ana_miktar);
        self::assertSame('0.00000000', (string) $kucukBakiye->refresh()->ana_miktar);
        self::assertSame(2, $sonuc['parcalar']->filter(fn (StokParcasi $parca): bool => bccomp(
            (string) $parca->olcuBakiyeleri()->withoutGlobalScopes()->value('donusum_ana_miktari'),
            '4',
            8,
        ) === 0)->count());
        self::assertSame(1, $sonuc['parcalar']->filter(fn (StokParcasi $parca): bool => bccomp(
            (string) $parca->olcuBakiyeleri()->withoutGlobalScopes()->value('donusum_ana_miktari'),
            '2',
            8,
        ) === 0)->count());
        self::assertSame(3, $sonuc['parcalar']->map(fn (StokParcasi $parca): int => (int) $parca->olcuBakiyeleri()->withoutGlobalScopes()->value('stok_olcusu_id'))->unique()->count());
    }

    public function test_coklu_olcude_kaynak_bakiye_belirtilmeden_donusum_reddedilir(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $adet = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $m2 = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI,
            'olculu_takip_turu' => 'alan', 'olcu_yapisi' => 'coklu',
            'ana_birim_id' => $m2->id, 'ikincil_birim_id' => $adet->id,
        ]);
        $bakiyeServisi = app(StokOlcuBakiyeServisi::class);
        foreach ([['A', '100'], ['B', '200']] as [$kod, $en]) {
            $olcu = $bakiyeServisi->olcuOlustur($firma->id, $stok, [
                'kod' => $kod, 'ad' => $kod, 'olcu_birimi' => 'cm', 'en' => $en, 'boy' => '100',
            ]);
            $bakiye = $bakiyeServisi->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu, $depo);
            $bakiyeServisi->giris($bakiye, adet: '1');
        }

        $this->expectException(IsKuraliIstisnasi::class);
        $this->expectExceptionMessage('Her parça için geçerli ölçü kaynağı seçilmelidir');
        app(StokParcaDonusumServisi::class)->donustur($stok, [
            ['ana_miktar' => '1'], ['ana_miktar' => '2'],
        ]);
    }

    public function test_fiziksel_parca_bolununca_kaynak_tukenir_yeni_kodlar_uretilir_ve_loglanir(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI,
            'stok_miktari' => '1',
            'guncel_birim_maliyet' => '10',
        ]);
        $donusum = app(StokParcaDonusumServisi::class)->donustur($stok, [['ana_miktar' => '1']]);
        $kaynak = $donusum['parcalar']->first();

        $sonuc = app(StokParcaDonusumServisi::class)->parcayiBol($kaynak, [
            ['ana_miktar' => '0.5'], ['ana_miktar' => '0.5'],
        ]);

        self::assertSame('0.00000000', (string) $sonuc['kaynak']->kalan_miktar);
        self::assertCount(2, $sonuc['parcalar']);
        self::assertNotSame($sonuc['parcalar'][0]->parca_kodu, $sonuc['parcalar'][1]->parca_kodu);
        self::assertSame('bolme', StokParcaIslemLogu::withoutGlobalScopes()->where('stok_id', $stok->id)->latest('id')->value('islem_turu'));
    }

    public function test_fiziksel_parca_tek_yeni_parcaya_bolunemez(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI,
            'stok_miktari' => '1',
        ]);
        $kaynak = app(StokParcaDonusumServisi::class)
            ->donustur($stok, [['ana_miktar' => '1']])['parcalar']
            ->firstOrFail();

        $this->expectException(IsKuraliIstisnasi::class);
        $this->expectExceptionMessage('En az iki yeni stok parçası');

        app(StokParcaDonusumServisi::class)->parcayiBol($kaynak, [
            ['ana_miktar' => '1'],
        ]);
    }

    public function test_olculu_parca_farkli_fiziksel_olculerle_bolunurken_adet_esdegeri_korunur(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $adet = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $m2 = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI,
            'olculu_takip_turu' => 'alan',
            'olcu_yapisi' => 'sabit',
            'ana_birim_id' => $m2->id,
            'ikincil_birim_id' => $adet->id,
        ]);
        $bakiyeServisi = app(StokOlcuBakiyeServisi::class);
        $olcu = $bakiyeServisi->olcuOlustur($firma->id, $stok, [
            'kod' => '200X200', 'ad' => '200 × 200 cm', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200',
        ]);
        $kaynak = $bakiyeServisi->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu, $depo);
        $bakiyeServisi->giris($kaynak, adet: '1');
        $donusumServisi = app(StokParcaDonusumServisi::class);
        $fizikselKaynak = $donusumServisi->donustur($stok, $donusumServisi->donusumOnerisi($stok, 1))['parcalar']->firstOrFail();

        $oneri = $donusumServisi->bolmeOnerisi($fizikselKaynak, 2);
        self::assertSame('4.00000000', collect($oneri)->reduce(
            fn (string $toplam, array $satir): string => bcadd($toplam, $satir['ana_miktar'], 8),
            '0',
        ));
        foreach ($oneri as &$satir) {
            $satir['en'] = '100';
            $satir['boy'] = '200';
        }
        unset($satir);

        $sonuc = $donusumServisi->parcayiBol($fizikselKaynak, $oneri);

        self::assertCount(2, $sonuc['parcalar']);
        $olcuIdleri = [];
        foreach ($sonuc['parcalar'] as $parca) {
            $bakiye = $parca->olcuBakiyeleri()->withoutGlobalScopes()->firstOrFail();
            $parcaOlcusu = $bakiye->olcu()->withoutGlobalScopes()->firstOrFail();
            self::assertSame('2.00000000', (string) $bakiye->ana_miktar);
            self::assertSame('0.50000000', (string) $bakiye->adet_esdegeri);
            self::assertSame('4.00000000', (string) $bakiye->donusum_ana_miktari);
            self::assertSame('100.00000000', (string) $parcaOlcusu->en);
            self::assertSame('200.00000000', (string) $parcaOlcusu->boy);
            self::assertSame('2.0000', (string) $parca->metrekare);
            $olcuIdleri[] = (int) $bakiye->stok_olcusu_id;
        }
        self::assertCount(2, array_unique($olcuIdleri));
        self::assertNotContains((int) $olcu->id, $olcuIdleri);
    }

    public function test_kismi_kalan_parcanin_olcusu_sonradan_degistirilir_ve_gecmis_bakiye_korunur(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $adet = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $m2 = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI,
            'olculu_takip_turu' => 'alan',
            'olcu_yapisi' => 'sabit',
            'ana_birim_id' => $m2->id,
            'ikincil_birim_id' => $adet->id,
        ]);
        $bakiyeServisi = app(StokOlcuBakiyeServisi::class);
        $olcu = $bakiyeServisi->olcuOlustur($firma->id, $stok, [
            'kod' => '200X200-G', 'ad' => '200 × 200 cm', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200',
        ]);
        $toplu = $bakiyeServisi->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu, $depo);
        $bakiyeServisi->giris($toplu, adet: '1');
        $donusumServisi = app(StokParcaDonusumServisi::class);
        $parca = $donusumServisi->donustur($stok, $donusumServisi->donusumOnerisi($stok, 1))['parcalar']->firstOrFail();
        $eskiBakiye = $parca->olcuBakiyeleri()->withoutGlobalScopes()->firstOrFail();
        $bakiyeServisi->cikis($eskiBakiye, '2');
        $parca->update(['kalan_miktar' => '2', 'parca_durumu' => 'kismi']);

        $form = $donusumServisi->parcaBilgisiFormVerisi($parca->refresh());
        self::assertSame('100.00000000', (string) $form['boy']);
        $guncel = $donusumServisi->parcaBilgileriniGuncelle($parca->refresh(), array_merge($form, [
            'en' => '100',
            'boy' => '200',
            'renk_desen' => 'Bulutlu',
            'kalite_sinifi' => 'A',
        ]));

        $yeniBakiye = $guncel->olcuBakiyeleri()->withoutGlobalScopes()->where('ana_miktar', '>', 0)->firstOrFail();
        $yeniOlcu = $yeniBakiye->olcu()->withoutGlobalScopes()->firstOrFail();
        self::assertSame('0.00000000', (string) $eskiBakiye->refresh()->ana_miktar);
        self::assertSame('2.00000000', (string) $yeniBakiye->ana_miktar);
        self::assertSame('0.50000000', (string) $yeniBakiye->adet_esdegeri);
        self::assertSame('4.00000000', (string) $yeniBakiye->donusum_ana_miktari);
        self::assertSame('2.00000000', (string) $yeniOlcu->bir_adet_ana_miktar);
        self::assertSame('Bulutlu', $guncel->renk_desen);
        self::assertSame('A', $guncel->kalite_sinifi);
        self::assertSame('bilgi_guncelleme', StokParcaIslemLogu::withoutGlobalScopes()->where('stok_id', $stok->id)->latest('id')->value('islem_turu'));
    }

    public function test_parca_maliyeti_degistiginde_gecmis_satis_maliyeti_korunur_ve_islem_loglanir(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $kullanici = User::query()->create([
            'name' => 'Parça Maliyet Testi',
            'email' => 'parca-maliyet-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI,
            'stok_miktari' => '1',
            'guncel_birim_maliyet' => '10',
        ]);
        $parca = app(StokParcaDonusumServisi::class)
            ->donustur($stok, [['ana_miktar' => '1']])['parcalar']
            ->firstOrFail();

        $hareket = app(StokHareketServisi::class)->kayitOlustur($firma->id, [
            'stok_id' => $stok->id,
            'depo_id' => $depo->id,
            'islem_turu' => StokHareketIslemTuru::Satis,
            'miktar' => '0.25',
            'birim_fiyat' => '30',
            'belge_turu' => StokBelgeTuru::Fatura,
            'belge_id' => 1,
            'tarih' => now(),
            'parca_dagilimi' => [['parca_kodu' => $parca->parca_kodu, 'miktar' => '0.25']],
        ]);
        $gecmisMaliyet = (string) $hareket->parcaHareketleri()->firstOrFail()->birim_maliyet;

        $guncel = app(StokParcaDonusumServisi::class)->parcaMaliyetiniGuncelle($parca, '20');

        self::assertSame('20.00000000', (string) $guncel->birim_maliyet);
        self::assertSame('10.00000000', $gecmisMaliyet);
        self::assertSame('10.00000000', (string) $hareket->parcaHareketleri()->firstOrFail()->birim_maliyet);
        $log = StokParcaIslemLogu::withoutGlobalScopes()->where('stok_id', $stok->id)->latest('id')->firstOrFail();
        self::assertSame('maliyet_guncelleme', $log->islem_turu);
        self::assertSame('10.00000000', (string) data_get($log->veri, 'eski_birim_maliyet'));
        self::assertSame('20.00000000', (string) data_get($log->veri, 'yeni_birim_maliyet'));
    }

    public function test_hareket_gormemis_parca_donusumu_geri_alinir(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI, 'stok_miktari' => '2',
        ]);
        $donusum = app(StokParcaDonusumServisi::class)->donustur($stok, [['ana_miktar' => '1'], ['ana_miktar' => '1']]);

        $ana = app(StokParcaDonusumServisi::class)->donusumuGeriAl($donusum['ana_parca']);

        self::assertSame('geri_alindi', $ana->parca_durumu);
        self::assertSame(2, StokParcasi::withoutGlobalScopes()->where('ust_parca_id', $ana->id)->where('parca_durumu', 'geri_alindi')->count());
        $geriAlmaLogu = StokParcaIslemLogu::withoutGlobalScopes()->where('stok_id', $stok->id)->latest('id')->firstOrFail();
        self::assertSame('geri_alma', $geriAlmaLogu->islem_turu);
        self::assertSame('2.00000000', (string) data_get($geriAlmaLogu->veri, 'toplam_ana_miktar'));
    }

    public function test_olculu_fiziksel_parcalar_birlestirilir_maliyet_agirlikli_hesaplanir_ve_geri_alinir(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $adet = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $m2 = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI,
            'olculu_takip_turu' => 'alan', 'olcu_yapisi' => 'sabit',
            'ana_birim_id' => $m2->id, 'ikincil_birim_id' => $adet->id,
        ]);
        $bakiyeServisi = app(StokOlcuBakiyeServisi::class);
        $olcu = $bakiyeServisi->olcuOlustur($firma->id, $stok, [
            'kod' => '200X200-BIR', 'ad' => '200 × 200 cm', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200',
        ]);
        $toplu = $bakiyeServisi->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu, $depo);
        $bakiyeServisi->giris($toplu, adet: '2');
        $servis = app(StokParcaDonusumServisi::class);
        $parcalar = $servis->donustur($stok, $servis->donusumOnerisi($stok, 2))['parcalar'];
        $parcalar[0]->update(['birim_maliyet' => '10']);
        $parcalar[1]->update(['birim_maliyet' => '20']);

        $oneri = $servis->birlesmeOnerisi($parcalar->pluck('id')->all());
        self::assertSame('8.00000000', (string) $oneri['ana_miktar']);
        self::assertSame('400.00000000', (string) $oneri['boy']);
        $sonuc = $servis->parcalariBirlestir($parcalar->pluck('id')->all(), $oneri);
        $birlesik = $sonuc['parca'];

        self::assertSame('8.00000000', (string) $birlesik->kalan_miktar);
        self::assertSame('15.00000000', (string) $birlesik->birim_maliyet);
        self::assertStringContainsString('-MRG-', (string) $birlesik->parca_kodu);
        self::assertSame('1.00000000', (string) $birlesik->olcuBakiyeleri()->withoutGlobalScopes()->firstOrFail()->adet_esdegeri);
        self::assertSame(2, StokParcasi::withoutGlobalScopes()->whereIn('id', $parcalar->pluck('id'))->where('parca_durumu', 'birlestirildi')->count());
        $log = StokParcaIslemLogu::withoutGlobalScopes()->where('islem_turu', 'birlestirme')->latest('id')->firstOrFail();

        $servis->birlesmeyiGeriAl($log);

        self::assertSame('geri_alindi', $birlesik->refresh()->parca_durumu);
        self::assertSame('0.00000000', (string) $birlesik->kalan_miktar);
        self::assertSame(2, StokParcasi::withoutGlobalScopes()->whereIn('id', $parcalar->pluck('id'))->where('parca_durumu', 'aktif')->count());
        self::assertEquals(8.0, (float) StokParcasi::withoutGlobalScopes()->whereIn('id', $parcalar->pluck('id'))->sum('kalan_miktar'));
        self::assertTrue((bool) data_get($log->refresh()->veri, 'geri_alindi'));
        $geriAlmaLogu = StokParcaIslemLogu::withoutGlobalScopes()->where('islem_turu', 'birlestirme_geri_alma')->latest('id')->firstOrFail();
        self::assertSame('8.00000000', (string) data_get($geriAlmaLogu->veri, 'toplam_ana_miktar'));
        self::assertSame(2, data_get($geriAlmaLogu->veri, 'kaynak_parca_sayisi'));
    }

    public function test_farkli_depolardaki_fiziksel_parcalar_birlestirilemez(): void
    {
        [$firma, $depo] = $this->temelKayitlar();
        $stok = $this->stok($firma->id, $depo->id, [
            'stok_takip_tipi' => StokKarti::STOK_TAKIP_TIPI_PARTI, 'stok_miktari' => '2',
        ]);
        $parcalar = app(StokParcaDonusumServisi::class)->donustur($stok, [['ana_miktar' => '1'], ['ana_miktar' => '1']])['parcalar'];
        $digerDepo = Depo::create(['firma_id' => $firma->id, 'kod' => 'D2', 'ad' => 'Diğer depo', 'aktif_mi' => true]);
        $parcalar[1]->update(['depo_id' => $digerDepo->id]);

        $this->expectException(IsKuraliIstisnasi::class);
        $this->expectExceptionMessage('aynı firma, stok kartı, depo ve ana parti');
        app(StokParcaDonusumServisi::class)->parcalariBirlestir($parcalar->pluck('id')->all(), []);
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
