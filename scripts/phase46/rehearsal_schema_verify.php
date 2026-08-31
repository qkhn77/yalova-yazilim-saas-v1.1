<?php

declare(strict_types=1);

/** Read-only canonical FK/index verification for the Phase 4.6 rehearsal DB. */
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
$normalize = static fn (string $action): string => strtoupper(trim($action)) === 'NO ACTION' ? 'RESTRICT' : strtoupper(trim($action));
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$one = static function (PDO $pdo, string $sql, array $parameters = []): mixed {
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchColumn();
};

$planHandle = fopen($root.'/output/phase45/fk-repair-plan.csv', 'rb');
$header = fgetcsv($planHandle);
$plan = [];
while (($row = fgetcsv($planHandle)) !== false) {
    $plan[] = array_combine($header, $row);
}
fclose($planHandle);

$metadataStatement = $pdo->prepare(<<<'SQL'
SELECT tc.CONSTRAINT_NAME, kcu.TABLE_NAME AS CHILD_TABLE, kcu.COLUMN_NAME AS CHILD_COLUMN,
       kcu.REFERENCED_TABLE_NAME AS PARENT_TABLE, kcu.REFERENCED_COLUMN_NAME AS PARENT_COLUMN,
       kcu.ORDINAL_POSITION, COALESCE(rc.DELETE_RULE, 'RESTRICT') AS DELETE_RULE,
       COALESCE(rc.UPDATE_RULE, 'RESTRICT') AS UPDATE_RULE
FROM information_schema.TABLE_CONSTRAINTS tc
INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
  ON kcu.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
 AND kcu.TABLE_NAME = tc.TABLE_NAME
 AND kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
  ON rc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
 AND rc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
WHERE tc.CONSTRAINT_SCHEMA = ? AND tc.TABLE_NAME = ?
  AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY' AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY tc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
SQL);
$byChild = [];
foreach (array_values(array_unique(array_column($plan, 'child'))) as $childTable) {
    $metadataStatement->execute([$database, $childTable]);
    foreach ($metadataStatement->fetchAll() as $row) {
        $name = $row['CONSTRAINT_NAME'];
        $byChild[$childTable][$name] ??= [
            'constraint_name' => $name,
            'child_table' => $row['CHILD_TABLE'],
            'child_columns' => [],
            'parent_table' => $row['PARENT_TABLE'],
            'parent_columns' => [],
            'on_delete' => $normalize($row['DELETE_RULE']),
            'on_update' => $normalize($row['UPDATE_RULE']),
        ];
        $byChild[$childTable][$name]['child_columns'][] = $row['CHILD_COLUMN'];
        $byChild[$childTable][$name]['parent_columns'][] = $row['PARENT_COLUMN'];
    }
}

$present = 0;
$wrongAction = 0;
$missing = [];
$duplicates = [];
$actualCanonicalActions = ['CASCADE' => 0, 'RESTRICT' => 0, 'SET NULL' => 0];
foreach ($plan as $item) {
    $childColumns = array_map('trim', explode(',', $item['child_columns']));
    $parentColumns = array_map('trim', explode(',', $item['parent_columns']));
    $matches = [];
    $wrong = [];
    foreach ($byChild[$item['child']] ?? [] as $constraint) {
        if ($constraint['parent_table'] === $item['parent'] && $constraint['child_columns'] === $childColumns && $constraint['parent_columns'] === $parentColumns) {
            if ($constraint['on_delete'] === $normalize($item['canonical_on_delete']) && $constraint['on_update'] === $normalize($item['canonical_on_update'])) {
                $matches[] = $constraint;
            } else {
                $wrong[] = $constraint;
            }
        }
    }
    if (count($matches) === 1) {
        $present++;
        $actualCanonicalActions[$normalize($item['canonical_on_delete'])]++;
    } elseif (count($matches) > 1) {
        $duplicates[$item['fk_id']] = count($matches);
    } elseif ($wrong !== []) {
        $wrongAction++;
    } else {
        $missing[] = $item['fk_id'];
    }
}

$physicalFk = (int) $one($pdo, "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'", [$database]);
$indexStatement = $pdo->prepare('SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX');
$indexStatement->execute([$database, 'teknik_servis_kayitli_cihazlar']);
$indexes = [];
foreach ($indexStatement->fetchAll() as $row) {
    $indexes[$row['INDEX_NAME']] ??= ['unique' => (int) $row['NON_UNIQUE'] === 0, 'columns' => [], 'prefixes' => []];
    $indexes[$row['INDEX_NAME']]['columns'][] = $row['COLUMN_NAME'];
    $indexes[$row['INDEX_NAME']]['prefixes'][] = $row['SUB_PART'] === null ? null : (int) $row['SUB_PART'];
}
$targetSignature = ['unique' => false, 'columns' => ['firma_id', 'cihaz_id', 'marka_id', 'model_no'], 'prefixes' => [null, null, null, null]];
$equivalentIndexes = array_filter($indexes, static fn (array $index): bool => $index === $targetSignature);
$indexDuplicate = max(0, count($equivalentIndexes) - 1);
$explain = [];
$row = $pdo->query('SELECT firma_id, cihaz_id, marka_id, model_no FROM teknik_servis_kayitli_cihazlar WHERE firma_id IS NOT NULL AND cihaz_id IS NOT NULL AND marka_id IS NOT NULL AND model_no IS NOT NULL LIMIT 1')->fetch();
if ($row !== false) {
    $statement = $pdo->prepare('EXPLAIN SELECT id FROM teknik_servis_kayitli_cihazlar WHERE firma_id = ? AND cihaz_id = ? AND marka_id = ? AND model_no = ?');
    $statement->execute([$row['firma_id'], $row['cihaz_id'], $row['marka_id'], $row['model_no']]);
    $explain = $statement->fetchAll();
}

$result = [
    'database' => $database,
    'plan_rows' => count($plan),
    'canonical_present' => $present,
    'wrong_action' => $wrongAction,
    'missing' => $missing,
    'semantic_duplicates' => $duplicates,
    'canonical_actions' => $actualCanonicalActions,
    'physical_fk' => $physicalFk,
    'required_index_equivalent' => count($equivalentIndexes),
    'required_index_duplicate' => $indexDuplicate,
    'indexes_on_device_table' => $indexes,
    'explain_lookup' => $explain,
    'status' => ($present === 170 && $wrongAction === 0 && $missing === [] && $duplicates === [] && $physicalFk === 452 && count($equivalentIndexes) === 1 && $indexDuplicate === 0) ? 'PASS' : 'FAIL',
];
$outputDirectory = $root.'/output/phase46';
if (! is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0777, true);
}
file_put_contents($outputDirectory.'/post-schema-verification.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
