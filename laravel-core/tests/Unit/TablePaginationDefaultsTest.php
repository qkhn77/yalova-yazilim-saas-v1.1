<?php

namespace Tests\Unit;

use App\Support\TablePaginationDefaults;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TablePaginationDefaultsTest extends TestCase
{
    /**
     * @return array<string, array{mixed, int|string}>
     */
    public static function values(): array
    {
        return [
            '10' => [10, 10],
            '20' => [20, 20],
            'numeric string' => ['50', 50],
            '100' => [100, 100],
            '1000' => [1000, 1000],
            'all' => ['all', 'all'],
            'null fallback' => [null, 10],
            'invalid integer fallback' => [25, 10],
            'invalid string fallback' => ['invalid', 10],
        ];
    }

    #[DataProvider('values')]
    public function test_value_is_normalized(mixed $value, int|string $expected): void
    {
        self::assertSame($expected, TablePaginationDefaults::normalize($value));
    }

    public function test_options_are_the_shared_contract(): void
    {
        self::assertSame([10, 20, 50, 100, 1000, 'all'], TablePaginationDefaults::OPTIONS);
    }

}
