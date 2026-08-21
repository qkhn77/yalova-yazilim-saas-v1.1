<?php

namespace Tests\Unit\Muhasebe;

use App\Muhasebe\Enumlar\OlculuStokTakipTuru;
use App\Muhasebe\Servisler\StokOlcuHesaplamaServisi;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StokOlcuHesaplamaServisiTest extends TestCase
{
    private StokOlcuHesaplamaServisi $servis;

    protected function setUp(): void { parent::setUp(); $this->servis = new StokOlcuHesaplamaServisi; }

    #[DataProvider('donusumler')]
    public function test_uzunluk_birimlerini_metreye_cevirir(string $deger, string $birim, string $beklenen): void
    {
        self::assertSame($beklenen, $this->servis->metreyeCevir($deger, $birim));
    }

    public static function donusumler(): array { return [['2000', 'mm', '2.00000000'], ['200', 'cm', '2.00000000'], ['2', 'm', '2.00000000']]; }

    public function test_alan_orneklerini_kesin_hesaplar(): void
    {
        self::assertSame('4.00000000', $this->servis->birAdetAnaMiktar(OlculuStokTakipTuru::Alan, ['en' => '2', 'boy' => '2']));
        self::assertSame('0.36000000', $this->servis->birAdetAnaMiktar(OlculuStokTakipTuru::Alan, ['en' => '0.6', 'boy' => '0.6']));
        self::assertSame('3.60000000', $this->servis->adettenAnaMiktara('10', '0.36'));
    }

    public function test_hacim_ve_uzunluk_hesaplarini_yapar(): void
    {
        $faktor = $this->servis->birAdetAnaMiktar(OlculuStokTakipTuru::Hacim, ['en' => '1', 'boy' => '2', 'yukseklik' => '0.05']);
        self::assertSame('0.10000000', $faktor);
        self::assertSame('1.00000000', $this->servis->adettenAnaMiktara('10', $faktor));
        self::assertSame('0.33333333', $this->servis->anaMiktardanAdede('2', '6'));
    }

    public function test_agirlik_ve_cift_yonlu_donusumleri_yapar(): void
    {
        $faktor = $this->servis->birAdetAnaMiktar(OlculuStokTakipTuru::Agirlik, ['bir_adet_agirlik' => '25']);
        self::assertSame('0.20000000', $this->servis->anaMiktardanAdede('5', $faktor));
        self::assertSame('0.25000000', $this->servis->anaMiktardanAdede('1', '4'));
        self::assertSame('0.75000000', $this->servis->anaMiktardanAdede('3', '4'));
    }

    public function test_agirlik_birimlerini_kilograma_normalize_eder(): void
    {
        self::assertSame('0.50000000', $this->servis->kilogramaCevir('500', 'g'));
        self::assertSame('25.00000000', $this->servis->kilogramaCevir('25', 'kg'));
        self::assertSame('2000.00000000', $this->servis->kilogramaCevir('2', 'ton'));
    }

    public function test_tutarsiz_cift_miktari_reddeder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->servis->tutarliligiDogrula('4', '2', '4');
    }

    #[DataProvider('gecersizler')]
    public function test_gecersiz_olculeri_reddeder(string $deger): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->servis->metreyeCevir($deger, 'cm');
    }

    public static function gecersizler(): array { return [['0'], ['-1'], ['abc']]; }

    public function test_gecersiz_birim_ve_eksik_hacmi_reddeder(): void
    {
        try { $this->servis->metreyeCevir('1', 'inc'); self::fail(); } catch (InvalidArgumentException) {}
        $this->expectException(InvalidArgumentException::class);
        $this->servis->birAdetAnaMiktar(OlculuStokTakipTuru::Hacim, ['en' => '1', 'boy' => '2']);
    }

    public function test_yuksek_hassasiyeti_sekiz_basamakta_yuvarlar(): void
    {
        self::assertSame('0.33333333', $this->servis->anaMiktardanAdede('1', '3'));
        self::assertSame('0.66666667', $this->servis->anaMiktardanAdede('2', '3'));
    }
}
