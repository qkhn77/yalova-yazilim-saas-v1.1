<?php

declare(strict_types=1);

/**
 * Filament ve ilgili view/lang dosyalarındaki bozuk Türkçe karakterleri
 * güvenli, tekrar uygulanabilir bir şekilde onarmaya yardımcı olur.
 *
 * Kullanım:
 *   php tools/fix_turkce_mojibake.php
 *   php tools/fix_turkce_mojibake.php --apply
 *   php tools/fix_turkce_mojibake.php --apply app/Filament resources/views/filament lang/vendor
 */

const DEFAULT_TARGETS = [
    'app/Filament',
    'resources/views/filament',
    'lang/vendor',
];

function main(array $argv): int
{
    $apply = in_array('--apply', $argv, true);
    $targets = array_values(array_filter(
        array_slice($argv, 1),
        static fn (string $arg): bool => $arg !== '--apply'
    ));

    if ($targets === []) {
        $targets = DEFAULT_TARGETS;
    }

    $map = buildReplacementMap();
    $stats = [
        'scanned' => 0,
        'changed' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    foreach (collectFiles($targets) as $path) {
        $stats['scanned']++;
        $contents = @file_get_contents($path);
        if (! is_string($contents) || $contents === '') {
            $stats['errors']++;
            echo "[ERR] {$path}".PHP_EOL;
            continue;
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $stats['skipped']++;
            echo "[SKIP:non-utf8] {$path}".PHP_EOL;
            continue;
        }

        [$fixed, $before, $after] = repairContents($contents, $map);
        if ($before === 0 || $after >= $before || $fixed === $contents) {
            $stats['skipped']++;
            continue;
        }

        if ($apply) {
            file_put_contents($path, $fixed);
        }

        $stats['changed']++;
        $mode = $apply ? 'FIX' : 'REPORT';
        echo "[{$mode}] {$path} | {$before} -> {$after}".PHP_EOL;
    }

    echo PHP_EOL;
    echo 'Scanned: '.$stats['scanned'].PHP_EOL;
    echo 'Changed: '.$stats['changed'].PHP_EOL;
    echo 'Skipped: '.$stats['skipped'].PHP_EOL;
    echo 'Errors: '.$stats['errors'].PHP_EOL;

    return 0;
}

/**
 * @return array<string, string>
 */
function buildReplacementMap(): array
{
    $goodChars = [
        'c39c', // Ü
        'c3bc', // ü
        'c396', // Ö
        'c3b6', // ö
        'c4b0', // İ
        'c4b1', // ı
        'c59e', // Ş
        'c59f', // ş
        'c387', // Ç
        'c3a7', // ç
        'c49e', // Ğ
        'c49f', // ğ
        'e28094', // —
        'e28093', // –
        'e2809c', // “
        'e2809d', // ”
        'e28098', // ‘
        'e28099', // ’
        'e280a6', // …
        'c2b0',   // °
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

    // Uzun eşleşmeleri önce onar.
    uksort($map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

    return $map;
}

/**
 * @param  array<string, string>  $map
 * @return array{string,int,int}
 */
function repairContents(string $contents, array $map): array
{
    $before = suspiciousCount($contents, $map);
    $fixed = $contents;

    for ($i = 0; $i < 8; $i++) {
        $next = strtr($fixed, $map);
        if ($next === $fixed) {
            break;
        }

        $fixed = $next;
    }

    $after = suspiciousCount($fixed, $map);

    return [$fixed, $before, $after];
}

/**
 * @param  array<string, string>  $map
 */
function suspiciousCount(string $contents, array $map): int
{
    $count = 0;

    foreach ($map as $bad => $_good) {
        $count += substr_count($contents, $bad);
    }

    return $count;
}

/**
 * @param  list<string>  $targets
 * @return list<string>
 */
function collectFiles(array $targets): array
{
    $paths = [];

    foreach ($targets as $target) {
        $absolute = normalizePath($target);
        if (is_file($absolute)) {
            $paths[] = $absolute;
            continue;
        }

        if (! is_dir($absolute)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $pathname = $item->getPathname();
            $extension = strtolower((string) pathinfo($pathname, PATHINFO_EXTENSION));

            if (in_array($extension, ['php', 'blade.php', 'md'], true) || str_ends_with($pathname, '.blade.php')) {
                $paths[] = $pathname;
            }
        }
    }

    sort($paths);

    return array_values(array_unique($paths));
}

function normalizePath(string $path): string
{
    if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
        return $path;
    }

    return getcwd().DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

exit(main($argv));
