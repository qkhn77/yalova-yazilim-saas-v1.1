<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokKarti;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\StokOlcuBakiyeServisi;
use Database\Seeders\MuhasebeOlcuBirimleriSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaturaOlcuKartlariTest extends TestCase
{
    use RefreshDatabase;

    public function test_sabit_ve_coklu_olculer_fatura_kartlari_olarak_baslatilir(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'Ölçü Kart Test Firması',
            'kisa_ad' => 'ÖKT',
            'firma_kodu' => 'OKT-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
        $depo = Depo::withoutGlobalScopes()->firstOrCreate([
            'firma_id' => $firma->id,
            'kod' => 'MERKEZ',
        ], [
            'ad' => 'Merkez Depo',
            'aktif_mi' => true,
        ]);
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $anaBirim = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $adetBirimi = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $servis = app(StokOlcuBakiyeServisi::class);

        $sabit = $this->olculuStok($firma->id, $depo->id, $anaBirim->id, $adetBirimi->id, 'sabit', 'SABIT');
        $sabitOlcuId = FaturaKaynagi::faturaIcinOlcuOlustur($firma->id, $sabit->id, [
            'kod' => 'SABIT-200X200',
            'olcu_birimi' => 'cm',
            'en' => '200',
            'boy' => '200',
        ]);
        $sabitOlcu = $sabit->olculer()->withoutGlobalScopes()->findOrFail($sabitOlcuId);
        $sabitBakiye = $servis->bakiyeBulVeyaOlustur($firma->id, $sabit, $sabitOlcu, $depo);
        $servis->giris($sabitBakiye, adet: '2');

        $sabitKartlar = FaturaKaynagi::olcuKartlariniBaslat($firma->id, $sabit->id, $depo->id);
        self::assertCount(1, $sabitKartlar);
        self::assertSame($sabitOlcuId, $sabitKartlar[0]['stok_olcusu_id']);
        self::assertSame($sabitBakiye->id, $sabitKartlar[0]['stok_olcu_bakiyesi_id']);
        self::assertFalse($sabitKartlar[0]['faturada_kullan']);

        $coklu = $this->olculuStok($firma->id, $depo->id, $anaBirim->id, $adetBirimi->id, 'coklu', 'COKLU');
        $ilk = $servis->olcuOlustur($firma->id, $coklu, [
            'kod' => 'COKLU-100X100', 'ad' => '100 x 100', 'olcu_birimi' => 'cm', 'en' => '100', 'boy' => '100',
        ]);
        $ikinci = $servis->olcuOlustur($firma->id, $coklu, [
            'kod' => 'COKLU-200X200', 'ad' => '200 x 200', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200',
        ]);
        $servis->giris($servis->bakiyeBulVeyaOlustur($firma->id, $coklu, $ilk, $depo), adet: '3');
        $servis->giris($servis->bakiyeBulVeyaOlustur($firma->id, $coklu, $ikinci, $depo), adet: '4');

        $cokluKartlar = FaturaKaynagi::olcuKartlariniBaslat($firma->id, $coklu->id, $depo->id);
        self::assertSame([$ilk->id, $ikinci->id], array_column($cokluKartlar, 'stok_olcusu_id'));
        self::assertSame([$adetBirimi->id, $adetBirimi->id], array_column($cokluKartlar, 'islem_birimi_id'));

        $secili = FaturaKaynagi::seciliOlcuDagilimlariniAyikla([
            $cokluKartlar[0],
            array_replace($cokluKartlar[1], ['girilen_miktar' => '2']),
        ]);
        self::assertCount(1, $secili);
        self::assertSame($ikinci->id, $secili[0]['stok_olcusu_id']);
        self::assertArrayNotHasKey('faturada_kullan', $secili[0]);
    }

    private function olculuStok(int $firmaId, int $depoId, int $anaBirimId, int $adetBirimId, string $olcuYapisi, string $kod): StokKarti
    {
        return StokKarti::query()->create([
            'firma_id' => $firmaId,
            'kod' => $kod.'-'.uniqid(),
            'ad' => $kod.' ölçülü stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'birim' => 'AD',
            'para_birimi' => 'TRY',
            'stok_takip' => true,
            'stok_miktari' => 0,
            'depo_id' => $depoId,
            'olculu_takip_turu' => 'alan',
            'olcu_yapisi' => $olcuYapisi,
            'ana_birim_id' => $anaBirimId,
            'ikincil_birim_id' => $adetBirimId,
        ]);
    }
}
