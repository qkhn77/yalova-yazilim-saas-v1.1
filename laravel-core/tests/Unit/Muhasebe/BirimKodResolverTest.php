<?php

namespace Tests\Unit\Muhasebe;

use App\Muhasebe\Servisler\BirimKodResolver;
use PHPUnit\Framework\TestCase;

class BirimKodResolverTest extends TestCase
{
    /** @dataProvider adetKodlari */
    public function test_adet_kodlari_canonical_adye_cozulur(string $kod): void
    {
        self::assertSame('AD', BirimKodResolver::normalize($kod));
        self::assertSame(['AD', 'ADET'], BirimKodResolver::acceptedCodes($kod));
    }

    /** @return iterable<string, array{string}> */
    public static function adetKodlari(): iterable
    {
        yield 'canonical' => ['AD'];
        yield 'legacy-uppercase' => ['ADET'];
        yield 'display-label' => ['Adet'];
        yield 'legacy-lowercase' => ['adet'];
    }

    public function test_bos_kod_cozulmez(): void
    {
        self::assertNull(BirimKodResolver::normalize('  '));
        self::assertSame([], BirimKodResolver::acceptedCodes(null));
    }
}
