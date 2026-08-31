<?php

declare(strict_types=1);

/**
 * Read-only Phase 4.6 rehearsal snapshot helper.
 *
 * Connection is supplied through REHEARSAL_DB_* environment variables so the
 * helper cannot accidentally use the application or production connection.
 */

$root = dirname(__DIR__, 2);
$database = getenv('REHEARSAL_DB_DATABASE') ?: '';
$host = getenv('REHEARSAL_DB_HOST') ?: '127.0.0.1';
$port = getenv('REHEARSAL_DB_PORT') ?: '3307';
$user = getenv('REHEARSAL_DB_USERNAME') ?: 'root';
$password = getenv('REHEARSAL_DB_PASSWORD') ?: '';
$label = $argv[1] ?? '';

if ($database === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $database) || ! preg_match('/^[A-Za-z0-9_]+$/', $label)) {
    fwrite(STDERR, "Usage: rehearsal_snapshot.php <label> with REHEARSAL_DB_DATABASE set\n");
    exit(2);
}

$quote = static function (string $identifier): string {
    if (! preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new RuntimeException("Unsafe identifier: {$identifier}");
    }

    return '`'.$identifier.'`';
};

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
);

$one = static function (PDO $pdo, string $sql, array $parameters = []): mixed {
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchColumn();
};

$hasTable = static function (PDO $pdo, string $database, string $table) use ($one): bool {
    return (int) $one($pdo, 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$database, $table]) === 1;
};

$hasColumn = static function (PDO $pdo, string $database, string $table, string $column) use ($one): bool {
    return (int) $one($pdo, 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$database, $table, $column]) === 1;
};

$tablesStatement = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
$tablesStatement->execute([$database]);
$tables = array_map(static fn (array $row): string => $row['TABLE_NAME'], $tablesStatement->fetchAll());

$rowCounts = [];
foreach ($tables as $table) {
    $rowCounts[$table] = (int) $one($pdo, 'SELECT COUNT(*) FROM '.$quote($table));
}

$migrationRows = $hasTable($pdo, $database, 'migrations') ? $rowCounts['migrations'] : 0;
$maxBatch = $hasTable($pdo, $database, 'migrations') ? (int) $one($pdo, 'SELECT COALESCE(MAX(batch), 0) FROM migrations') : 0;
$migrationNames = [];
if ($hasTable($pdo, $database, 'migrations')) {
    $statement = $pdo->query('SELECT migration FROM migrations ORDER BY migration');
    $migrationNames = array_map(static fn (array $row): string => $row['migration'], $statement->fetchAll());
}

$repositoryMigrationNames = [];
foreach (glob($root.'/laravel-core/database/migrations/*.php') ?: [] as $path) {
    $repositoryMigrationNames[] = pathinfo($path, PATHINFO_FILENAME);
}
sort($repositoryMigrationNames);
$historicalExtras = array_values(array_diff($migrationNames, $repositoryMigrationNames));

$fkCount = (int) $one($pdo, "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'", [$database]);
$columnCount = (int) $one($pdo, 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?', [$database]);
$indexRows = (int) $one($pdo, 'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ?', [$database]);

$adRows = [];
if ($hasTable($pdo, $database, 'muhasebe_birimler')) {
    $statement = $pdo->prepare("SELECT id, kod, ad FROM muhasebe_birimler WHERE id = 1 OR kod IN ('AD', 'ADET') ORDER BY id, kod");
    $statement->execute();
    $adRows = $statement->fetchAll();
}

$tracking = [];
if ($hasTable($pdo, $database, 'stok_kartlari') && $hasColumn($pdo, $database, 'stok_kartlari', 'stok_takip_tipi')) {
    $statement = $pdo->query('SELECT COALESCE(stok_takip_tipi, \'<NULL>\') AS value, COUNT(*) AS count FROM stok_kartlari GROUP BY stok_takip_tipi ORDER BY value');
    foreach ($statement->fetchAll() as $row) {
        $tracking[$row['value']] = (int) $row['count'];
    }
}

$legacy = [];
foreach ([
    ['table' => 'fatura_kalemleri', 'column' => 'uretim_tarihi'],
    ['table' => 'fatura_kalemleri', 'column' => 'son_kullanma_tarihi'],
    ['table' => 'muhasebe_barkodlu_satis_iade_kalemleri', 'column' => 'seri_nolari'],
] as $item) {
    $key = $item['table'].'.'.$item['column'];
    $legacy[$key] = [
        'exists' => $hasColumn($pdo, $database, $item['table'], $item['column']),
        'non_null' => 0,
    ];
    if ($legacy[$key]['exists']) {
        $legacy[$key]['non_null'] = (int) $one($pdo, 'SELECT COUNT(*) FROM '.$quote($item['table']).' WHERE '.$quote($item['column']).' IS NOT NULL');
    }
}

$aggregates = [];
$aggregateQueries = [
    'invoice_genel_toplam' => ['faturalar', 'genel_toplam'],
    'invoice_odenecek_tutar' => ['faturalar', 'odenecek_tutar'],
    'invoice_line_miktar' => ['fatura_kalemleri', 'miktar'],
    'invoice_line_toplam' => ['fatura_kalemleri', 'toplam'],
    'stock_movement_miktar' => ['stok_hareketleri', 'miktar'],
    'stock_movement_toplam' => ['stok_hareketleri', 'toplam'],
    'finance_tutar' => ['finans_hareketleri', 'tutar'],
    'finance_kullanilan_tutar' => ['finans_hareketleri', 'kullanilan_tutar'],
];
foreach ($aggregateQueries as $key => [$table, $column]) {
    $aggregates[$key] = ($hasTable($pdo, $database, $table) && $hasColumn($pdo, $database, $table, $column))
        ? (string) $one($pdo, 'SELECT COALESCE(SUM('.$quote($column).'), 0) FROM '.$quote($table))
        : null;
}

$orphanTotal = 0;
$orphanNonZero = [];
$planPath = $root.'/output/phase45/fk-repair-plan.csv';
if (is_file($planPath) && ($handle = fopen($planPath, 'rb')) !== false) {
    $header = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        $item = array_combine($header, $row);
        $childColumns = array_map('trim', explode(',', $item['child_columns']));
        $parentColumns = array_map('trim', explode(',', $item['parent_columns']));
        $join = [];
        $notNull = [];
        foreach ($childColumns as $index => $childColumn) {
            $join[] = 'c.'.$quote($childColumn).' = p.'.$quote($parentColumns[$index]);
            $notNull[] = 'c.'.$quote($childColumn).' IS NOT NULL';
        }
        $sql = 'SELECT COUNT(*) FROM '.$quote($item['child']).' c LEFT JOIN '.$quote($item['parent']).' p ON '.implode(' AND ', $join).' WHERE '.implode(' AND ', $notNull).' AND p.'.$quote($parentColumns[0]).' IS NULL';
        $count = (int) $one($pdo, $sql);
        $orphanTotal += $count;
        if ($count > 0) {
            $orphanNonZero[$item['fk_id']] = $count;
        }
    }
    fclose($handle);
}

$snapshot = [
    'database' => $database,
    'server_port' => (int) $one($pdo, 'SELECT @@port'),
    'tables' => count($tables),
    'columns' => $columnCount,
    'foreign_keys' => $fkCount,
    'index_rows' => $indexRows,
    'migration_rows' => $migrationRows,
    'migration_max_batch' => $maxBatch,
    'repository_migrations' => count($repositoryMigrationNames),
    'pending_repository_migrations' => count(array_diff($repositoryMigrationNames, $migrationNames)),
    'historical_extra_migrations' => $historicalExtras,
    'ad_rows' => $adRows,
    'stock_tracking' => $tracking,
    'legacy_columns' => $legacy,
    'aggregates' => $aggregates,
    'orphan_total_170_plan' => $orphanTotal,
    'orphan_non_zero' => $orphanNonZero,
    'row_counts' => $rowCounts,
];

$outputDirectory = $root.'/output/phase46';
if (! is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0777, true);
}
$outputPath = $outputDirectory.'/'.$label.'-snapshot.json';
file_put_contents($outputPath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

echo json_encode([
    'output' => $outputPath,
    'tables' => $snapshot['tables'],
    'columns' => $snapshot['columns'],
    'foreign_keys' => $snapshot['foreign_keys'],
    'index_rows' => $snapshot['index_rows'],
    'migration_rows' => $snapshot['migration_rows'],
    'repository_migrations' => $snapshot['repository_migrations'],
    'pending_repository_migrations' => $snapshot['pending_repository_migrations'],
    'historical_extras' => $snapshot['historical_extra_migrations'],
    'orphan_total_170_plan' => $snapshot['orphan_total_170_plan'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
