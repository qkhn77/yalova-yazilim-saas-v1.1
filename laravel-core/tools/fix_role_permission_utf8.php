<?php

declare(strict_types=1);

use App\Models\Rol;
use App\Models\Yetki;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$map = buildReplacementMap();
$targets = [
    [
        'table' => 'roller',
        'model' => Rol::class,
        'columns' => ['ad', 'aciklama'],
    ],
    [
        'table' => 'yetkiler',
        'model' => Yetki::class,
        'columns' => ['ad'],
    ],
];

$updatedRows = 0;

foreach ($targets as $target) {
    $modelClass = $target['model'];
    $columns = $target['columns'];
    $table = $target['table'];

    $modelClass::query()
        ->select(array_merge(['id'], $columns))
        ->chunkById(100, function ($records) use ($columns, $map, $table, &$updatedRows): void {
            foreach ($records as $record) {
                $dirty = false;

                foreach ($columns as $column) {
                    $value = $record->{$column};
                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    [$fixed, $before, $after] = repairValue($value, $map);
                    if ($fixed === $value || $before === 0 || $after >= $before) {
                        continue;
                    }

                    $record->{$column} = $fixed;
                    $dirty = true;
                }

                if (! $dirty) {
                    continue;
                }

                $record->save();
                $updatedRows++;
                echo "[FIX] {$table}#{$record->id}".PHP_EOL;
            }
        });
}

echo PHP_EOL.'Updated rows: '.$updatedRows.PHP_EOL;

/**
 * @return array<string, string>
 */
function buildReplacementMap(): array
{
    $goodChars = [
        'c39c', 'c3bc', 'c396', 'c3b6', 'c4b0', 'c4b1',
        'c59e', 'c59f', 'c387', 'c3a7', 'c49e', 'c49f',
        'e28094', 'e28093', 'e2809c', 'e2809d', 'e28098', 'e28099', 'e280a6',
    ];

    $sourceEncodings = ['Windows-1252', 'ISO-8859-9'];
    $map = [];

    foreach ($goodChars as $goodHex) {
        $good = hex2bin($goodHex);
        if (! is_string($good) || $good === '') {
            continue;
        }

        $variants = [$good];

        for ($round = 0; $round < 4; $round++) {
            $current = $variants;
            foreach ($current as $variant) {
                foreach ($sourceEncodings as $encoding) {
                    $bad = @mb_convert_encoding($variant, 'UTF-8', $encoding);
                    if (! is_string($bad) || $bad === '') {
                        continue;
                    }

                    if (! in_array($bad, $variants, true)) {
                        $variants[] = $bad;
                    }
                }
            }
        }

        foreach ($variants as $variant) {
            if ($variant !== $good) {
                $map[$variant] = $good;
            }
        }
    }

    uksort($map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

    return $map;
}

/**
 * @param  array<string, string>  $map
 * @return array{string,int,int}
 */
function repairValue(string $value, array $map): array
{
    $before = suspiciousCount($value, $map);
    $fixed = $value;

    for ($i = 0; $i < 8; $i++) {
        $next = strtr($fixed, $map);
        if ($next === $fixed) {
            break;
        }

        $fixed = $next;
    }

    return [$fixed, $before, suspiciousCount($fixed, $map)];
}

/**
 * @param  array<string, string>  $map
 */
function suspiciousCount(string $value, array $map): int
{
    $count = 0;

    foreach ($map as $bad => $_good) {
        $count += substr_count($value, $bad);
    }

    return $count;
}
