<?php

declare(strict_types=1);

// Read-only precondition audit for the 170 approved canonical FK rows.
$root = dirname(__DIR__, 2);
$database = getenv('REHEARSAL_DB_DATABASE') ?: '';
$host = getenv('REHEARSAL_DB_HOST') ?: '127.0.0.1';
$port = getenv('REHEARSAL_DB_PORT') ?: '3307';
$user = getenv('REHEARSAL_DB_USERNAME') ?: 'root';
$password = getenv('REHEARSAL_DB_PASSWORD') ?: '';

if ($database === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    fwrite(STDERR, "REHEARSAL_DB_DATABASE is required\n");
    exit(2);
}

$quote = static function (string $identifier): string {
    if (! preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new RuntimeException("Unsafe identifier: {$identifier}");
    }

    return '`'.$identifier.'`';
};
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$one = static function (PDO $pdo, string $sql, array $parameters = []): mixed {
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchColumn();
};

$plan = $root.'/output/phase45/fk-repair-plan.csv';
$handle = fopen($plan, 'rb');
$header = fgetcsv($handle);
$rows = [];
while (($row = fgetcsv($handle)) !== false) {
    $rows[] = array_combine($header, $row);
}
fclose($handle);

$missingTables = [];
$missingColumns = [];
$incompatible = [];
$orphans = [];
$checked = 0;
foreach ($rows as $item) {
    $checked++;
    $childColumns = array_map('trim', explode(',', $item['child_columns']));
    $parentColumns = array_map('trim', explode(',', $item['parent_columns']));
    $tables = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?)');
    $tables->execute([$database, $item['child'], $item['parent']]);
    $existingTables = array_column($tables->fetchAll(), 'TABLE_NAME');
    foreach ([$item['child'], $item['parent']] as $table) {
        if (! in_array($table, $existingTables, true)) {
            $missingTables[$item['fk_id']][] = $table;
        }
    }
    if (isset($missingTables[$item['fk_id']])) {
        continue;
    }

    $columnNames = array_merge($childColumns, $parentColumns);
    $placeholders = implode(',', array_fill(0, count($columnNames), '?'));
    $columns = $pdo->prepare('SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?,?) AND COLUMN_NAME IN ('.$placeholders.')');
    $columns->execute(array_merge([$database, $item['child'], $item['parent']], $columnNames));
    $byKey = [];
    foreach ($columns->fetchAll() as $column) {
        $byKey[$column['TABLE_NAME'].'.'.$column['COLUMN_NAME']] = $column;
    }
    foreach ($childColumns as $index => $childColumn) {
        $parentColumn = $parentColumns[$index];
        $child = $byKey[$item['child'].'.'.$childColumn] ?? null;
        $parent = $byKey[$item['parent'].'.'.$parentColumn] ?? null;
        if (! $child || ! $parent) {
            $missingColumns[$item['fk_id']][] = $item['child'].'.'.$childColumn.' -> '.$item['parent'].'.'.$parentColumn;
            continue;
        }
        if (strtolower($child['DATA_TYPE']) !== strtolower($parent['DATA_TYPE']) || (str_contains(strtolower($child['COLUMN_TYPE']), 'unsigned') !== str_contains(strtolower($parent['COLUMN_TYPE']), 'unsigned'))) {
            $incompatible[$item['fk_id']][] = $child['COLUMN_TYPE'].' -> '.$parent['COLUMN_TYPE'];
        }
    }
    if (isset($missingColumns[$item['fk_id']]) || isset($incompatible[$item['fk_id']])) {
        continue;
    }

    $joins = [];
    $notNull = [];
    foreach ($childColumns as $index => $childColumn) {
        $joins[] = 'c.'.$quote($childColumn).' = p.'.$quote($parentColumns[$index]);
        $notNull[] = 'c.'.$quote($childColumn).' IS NOT NULL';
    }
    $orphanSql = 'SELECT COUNT(*) FROM '.$quote($item['child']).' c LEFT JOIN '.$quote($item['parent']).' p ON '.implode(' AND ', $joins).' WHERE '.implode(' AND ', $notNull).' AND p.'.$quote($parentColumns[0]).' IS NULL';
    $orphanCount = (int) $one($pdo, $orphanSql);
    if ($orphanCount > 0) {
        $orphans[$item['fk_id']] = $orphanCount;
    }
}

$indexSql = "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'teknik_servis_kayitli_cihazlar' AND NON_UNIQUE = 0";
$requiredIndexTable = (int) $one($pdo, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'teknik_servis_kayitli_cihazlar'", [$database]) === 1;
$requiredIndexColumns = (int) $one($pdo, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'teknik_servis_kayitli_cihazlar' AND COLUMN_NAME IN ('firma_id','cihaz_id','marka_id','model_no')", [$database]) === 4;

$result = [
    'database' => $database,
    'checked' => $checked,
    'missing_tables' => $missingTables,
    'missing_columns' => $missingColumns,
    'incompatible_types' => $incompatible,
    'orphans' => $orphans,
    'orphan_total' => array_sum($orphans),
    'required_index_table_exists' => $requiredIndexTable,
    'required_index_columns_exist' => $requiredIndexColumns,
    'status' => ($checked === 170 && $missingTables === [] && $missingColumns === [] && $incompatible === [] && $orphans === [] && $requiredIndexTable && $requiredIndexColumns) ? 'PASS' : 'BLOCKED',
];
$outputDirectory = $root.'/output/phase46';
if (! is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0777, true);
}
file_put_contents($outputDirectory.'/precondition-audit.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
