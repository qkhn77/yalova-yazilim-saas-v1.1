<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\StokBarkodu;
use App\Models\Muhasebe\StokKarti;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\StokBarkodServisi;
use App\Support\Barcode\Ean13SvgUretici;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StokBarkodOlusturmaTest extends TestCase
{
    use RefreshDatabase;

    private function firmaOlustur(string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => 'Firma '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function stokOlustur(Firma $firma, string $kod, ?string $barkod = null): StokKarti
    {
        return StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => $kod,
            'ad' => 'Stok '.$kod,
            'slug' => 'stok-'.$kod,
            'barkod' => $barkod,
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'para_birimi' => 'TRY',
            'stok_takip' => true,
            'stok_miktari' => 1,
            'satis_fiyati' => 10,
        ]);
    }

    public function test_servis_eksik_barkodu_uretib_senkronize_eder(): void
    {
        $firma = $this->firmaOlustur('BRK-A');
        $stok = $this->stokOlustur($firma, 'STK-1');

        $barkod = app(StokBarkodServisi::class)->barkodOlusturVeyaSenkronizeEt($stok);

        $this->assertMatchesRegularExpression('/^\d{13}$/', $barkod);
        $this->assertNotNull(Ean13SvgUretici::svgOlustur($barkod));
        $this->assertSame($barkod, $stok->fresh()->barkod);
        $this->assertDatabaseHas('stok_barkodlari', [
            'firma_id' => $firma->id,
            'stok_id' => $stok->id,
            'barkod' => $barkod,
            'varsayilan_mi' => true,
            'aktif' => true,
        ]);
    }

    public function test_komut_sadece_eksik_barkodlari_olusturur_mevcudu_korur(): void
    {
        $firma = $this->firmaOlustur('BRK-B');
        $eksik = $this->stokOlustur($firma, 'STK-2');
        $mevcut = $this->stokOlustur($firma, 'STK-3', '2901234567894');

        $this->artisan('stok:barkod-olustur --firma_id='.$firma->id)
            ->assertSuccessful();

        $eksik->refresh();
        $mevcut->refresh();

        $this->assertMatchesRegularExpression('/^\d{13}$/', (string) $eksik->barkod);
        $this->assertSame('2901234567894', $mevcut->barkod);

        $this->assertDatabaseHas('stok_barkodlari', [
            'firma_id' => $firma->id,
            'stok_id' => $eksik->id,
            'barkod' => $eksik->barkod,
            'varsayilan_mi' => true,
        ]);

        $this->assertDatabaseHas('stok_barkodlari', [
            'firma_id' => $firma->id,
            'stok_id' => $mevcut->id,
            'barkod' => $mevcut->barkod,
            'varsayilan_mi' => true,
        ]);

        $sayac = StokBarkodu::tenantScopeOlmadan(
            fn () => StokBarkodu::query()->where('firma_id', $firma->id)->count()
        );

        $this->assertSame(2, $sayac);
    }
}
