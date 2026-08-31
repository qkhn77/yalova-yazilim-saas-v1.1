<?php

declare(strict_types=1);

namespace Database\Migrations\Support;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Migration-only semantic FK repair support.
 *
 * This file is intentionally outside App\\ so it cannot become a business
 * runtime dependency. Corrective migrations include it explicitly.
 */
final class CanonicalForeignKeyRepairSupport
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function driver(): string
    {
        $driver = strtolower($this->connection->getDriverName());

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Canonical FK repair supports mysql/mariadb only; received [{$driver}].");
        }

        return $driver;
    }

    /**
     * @return array{child_table:string,child_columns:list<string>,parent_table:string,parent_columns:list<string>}
     */
    public function relationshipSignature(array $definition): array
    {
        $childColumns = $this->orderedColumns($definition['child_columns'] ?? []);
        $parentColumns = $this->orderedColumns($definition['parent_columns'] ?? []);

        if ($childColumns === [] || count($childColumns) !== count($parentColumns)) {
            throw new RuntimeException('Canonical FK relationship must have equally sized ordered child and parent columns.');
        }

        return [
            'child_table' => $this->identifier((string) ($definition['child_table'] ?? $definition['child'] ?? '')),
            'child_columns' => $childColumns,
            'parent_table' => $this->identifier((string) ($definition['parent_table'] ?? $definition['parent'] ?? '')),
            'parent_columns' => $parentColumns,
        ];
    }

    public function deterministicConstraintName(array $definition): string
    {
        $relationship = $this->relationshipSignature($definition);
        $child = $relationship['child_table'];
        $identity = implode('|', [
            $child,
            implode(',', $relationship['child_columns']),
            $relationship['parent_table'],
            implode(',', $relationship['parent_columns']),
        ]);

        $prefix = substr($child, 0, 40);
        $name = 'fk_'.$prefix.'_'.substr(hash('sha256', $identity), 0, 16);

        if (strlen($name) > 64 || ! preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new RuntimeException("Generated FK constraint name is invalid or longer than 64 characters: [{$name}].");
        }

        return $name;
    }

    /**
     * @return list<array{constraint_name:string,child_table:string,child_columns:list<string>,parent_table:string,parent_columns:list<string>,on_delete:string,on_update:string}>
     */
    public function findRelationshipConstraints(array $definition): array
    {
        $relationship = $this->relationshipSignature($definition);
        $rows = $this->metadataRows($relationship['child_table']);
        $matches = [];

        foreach ($rows as $row) {
            if (
                $row['parent_table'] === $relationship['parent_table']
                && $row['child_columns'] === $relationship['child_columns']
                && $row['parent_columns'] === $relationship['parent_columns']
            ) {
                $matches[] = $row;
            }
        }

        return $matches;
    }

    public function canonicalEquivalentExists(array $definition): bool
    {
        $constraints = $this->findRelationshipConstraints($definition);
        $onDelete = $this->normalizeAction((string) ($definition['canonical_on_delete'] ?? $definition['on_delete'] ?? ''));
        $onUpdate = $this->normalizeAction((string) ($definition['canonical_on_update'] ?? $definition['on_update'] ?? ''));

        return count(array_filter(
            $constraints,
            static fn (array $constraint): bool => $constraint['on_delete'] === $onDelete && $constraint['on_update'] === $onUpdate,
        )) === 1;
    }

    /**
     * @return list<array{constraint_name:string,child_table:string,child_columns:list<string>,parent_table:string,parent_columns:list<string>,on_delete:string,on_update:string}>
     */
    public function differentActionEquivalent(array $definition): array
    {
        $constraints = $this->findRelationshipConstraints($definition);
        $onDelete = $this->normalizeAction((string) ($definition['canonical_on_delete'] ?? $definition['on_delete'] ?? ''));
        $onUpdate = $this->normalizeAction((string) ($definition['canonical_on_update'] ?? $definition['on_update'] ?? ''));

        return array_values(array_filter(
            $constraints,
            static fn (array $constraint): bool => $constraint['on_delete'] !== $onDelete || $constraint['on_update'] !== $onUpdate,
        ));
    }

    /**
     * @param list<array{constraint_name:string,child_table:string,child_columns:list<string>,parent_table:string,parent_columns:list<string>,on_delete:string,on_update:string}>|null $constraints
     */
    public function assertRelationshipStateSafe(array $definition, ?array $constraints = null): void
    {
        $this->driver();
        $relationship = $this->relationshipSignature($definition);
        $constraints ??= $this->findRelationshipConstraints($definition);

        if (count($constraints) > 1) {
            $names = implode(', ', array_map(static fn (array $constraint): string => $constraint['constraint_name'], $constraints));
            throw new RuntimeException(sprintf(
                'Multiple FK constraints for relationship %s(%s) -> %s(%s): %s',
                $relationship['child_table'],
                implode(',', $relationship['child_columns']),
                $relationship['parent_table'],
                implode(',', $relationship['parent_columns']),
                $names,
            ));
        }

        $this->assertTablesAndColumns($relationship);

        $onDelete = $this->normalizeAction((string) ($definition['canonical_on_delete'] ?? $definition['on_delete'] ?? ''));
        $onUpdate = $this->normalizeAction((string) ($definition['canonical_on_update'] ?? $definition['on_update'] ?? ''));

        if (! in_array($onDelete, ['CASCADE', 'RESTRICT', 'SET NULL'], true) || ! in_array($onUpdate, ['CASCADE', 'RESTRICT'], true)) {
            throw new RuntimeException("Unsupported canonical FK action for {$relationship['child_table']}.");
        }

        if ($onDelete === 'SET NULL' && ! $this->columnsNullable($relationship['child_table'], $relationship['child_columns'])) {
            throw new RuntimeException(sprintf(
                'SET NULL requires nullable child columns: %s(%s).',
                $relationship['child_table'],
                implode(',', $relationship['child_columns']),
            ));
        }

        $orphans = $this->orphanCount($relationship);

        if ($orphans > 0) {
            throw new RuntimeException(sprintf(
                'Canonical FK precondition failed: %d orphan rows in %s(%s) -> %s(%s).',
                $orphans,
                $relationship['child_table'],
                implode(',', $relationship['child_columns']),
                $relationship['parent_table'],
                implode(',', $relationship['parent_columns']),
            ));
        }
    }

    /**
     * @return 'NO-OP'|'ADDED'|'REPLACED'
     */
    public function ensureCanonicalForeignKey(array $definition): string
    {
        $constraints = $this->findRelationshipConstraints($definition);

        // Every precondition, including ambiguity and orphan checks, runs
        // before any DROP or ADD DDL.
        $this->assertRelationshipStateSafe($definition, $constraints);

        if ($this->canonicalEquivalentExists($definition)) {
            return 'NO-OP';
        }

        $wrongAction = $this->differentActionEquivalent($definition);
        $result = 'ADDED';

        if ($wrongAction !== []) {
            if (count($wrongAction) !== 1) {
                throw new RuntimeException('Unexpected multiple wrong-action FK constraints after precondition validation.');
            }

            $this->dropConstraint($definition['child_table'] ?? $definition['child'], $wrongAction[0]['constraint_name']);
            $result = 'REPLACED';
        }

        $this->addConstraint($definition, $this->deterministicConstraintName($definition));

        if (! $this->canonicalEquivalentExists($definition)) {
            throw new RuntimeException(sprintf(
                'Post-DDL canonical FK verification failed for [%s].',
                $this->deterministicConstraintName($definition),
            ));
        }

        return $result;
    }

    public function normalizeAction(string $action): string
    {
        $normalized = strtoupper(trim($action));

        return match ($normalized) {
            'NO ACTION', 'NO_ACTION' => 'RESTRICT',
            'RESTRICT', 'CASCADE', 'SET NULL' => $normalized,
            default => throw new RuntimeException("Unsupported referential action [{$action}]."),
        };
    }

    private function metadataRows(string $childTable): array
    {
        $driver = $this->driver();
        $schema = $this->connection->getDatabaseName();

        $sql = <<<'SQL'
SELECT
    tc.CONSTRAINT_NAME,
    kcu.TABLE_NAME AS CHILD_TABLE,
    kcu.COLUMN_NAME AS CHILD_COLUMN,
    kcu.REFERENCED_TABLE_NAME AS PARENT_TABLE,
    kcu.REFERENCED_COLUMN_NAME AS PARENT_COLUMN,
    kcu.ORDINAL_POSITION,
    COALESCE(rc.DELETE_RULE, 'RESTRICT') AS DELETE_RULE,
    COALESCE(rc.UPDATE_RULE, 'RESTRICT') AS UPDATE_RULE
FROM information_schema.TABLE_CONSTRAINTS tc
INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
    ON kcu.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
   AND kcu.TABLE_NAME = tc.TABLE_NAME
   AND kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
    ON rc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
   AND rc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
WHERE tc.CONSTRAINT_SCHEMA = ?
  AND tc.TABLE_NAME = ?
  AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY'
  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY tc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
SQL;

        if ($driver === 'mariadb' || $driver === 'mysql') {
            $rawRows = $this->connection->select($sql, [$schema, $childTable]);
            $grouped = [];

            foreach ($rawRows as $rawRow) {
                $name = (string) $rawRow->CONSTRAINT_NAME;
                $grouped[$name] ??= [
                    'constraint_name' => $name,
                    'child_table' => $this->identifier((string) $rawRow->CHILD_TABLE),
                    'child_columns' => [],
                    'parent_table' => $this->identifier((string) $rawRow->PARENT_TABLE),
                    'parent_columns' => [],
                    'on_delete' => $this->normalizeAction((string) $rawRow->DELETE_RULE),
                    'on_update' => $this->normalizeAction((string) $rawRow->UPDATE_RULE),
                ];
                $grouped[$name]['child_columns'][] = $this->identifier((string) $rawRow->CHILD_COLUMN);
                $grouped[$name]['parent_columns'][] = $this->identifier((string) $rawRow->PARENT_COLUMN);
            }

            return array_values($grouped);
        }

        throw new RuntimeException('Unsupported metadata driver.');
    }

    private function assertTablesAndColumns(array $relationship): void
    {
        $schema = $this->connection->getDatabaseName();
        $tables = $this->connection->select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?)',
            [$schema, $relationship['child_table'], $relationship['parent_table']],
        );
        $existingTables = array_map(static fn (object $row): string => (string) $row->TABLE_NAME, $tables);

        foreach ([$relationship['child_table'], $relationship['parent_table']] as $table) {
            if (! in_array($table, $existingTables, true)) {
                throw new RuntimeException("Canonical FK precondition failed: table [{$table}] does not exist.");
            }
        }

        $columns = $this->connection->select(
            'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND ((TABLE_NAME = ? AND COLUMN_NAME IN ('.implode(',', array_fill(0, count($relationship['child_columns']), '?')).')) OR (TABLE_NAME = ? AND COLUMN_NAME IN ('.implode(',', array_fill(0, count($relationship['parent_columns']), '?')).')))',
            array_merge([$schema, $relationship['child_table']], $relationship['child_columns'], [$relationship['parent_table']], $relationship['parent_columns']),
        );

        $byKey = [];
        foreach ($columns as $column) {
            $byKey[$column->TABLE_NAME.'.'.$column->COLUMN_NAME] = $column;
        }

        foreach ($relationship['child_columns'] as $index => $childColumn) {
            $parentColumn = $relationship['parent_columns'][$index];
            $child = $byKey[$relationship['child_table'].'.'.$childColumn] ?? null;
            $parent = $byKey[$relationship['parent_table'].'.'.$parentColumn] ?? null;

            if (! $child || ! $parent) {
                throw new RuntimeException("Canonical FK precondition failed: missing column {$childColumn} -> {$parentColumn}.");
            }

            if (strtolower((string) $child->DATA_TYPE) !== strtolower((string) $parent->DATA_TYPE) || $this->unsigned((string) $child->COLUMN_TYPE) !== $this->unsigned((string) $parent->COLUMN_TYPE)) {
                throw new RuntimeException("Canonical FK precondition failed: incompatible column types {$childColumn} -> {$parentColumn}.");
            }
        }
    }

    private function columnsNullable(string $table, array $columns): bool
    {
        $schema = $this->connection->getDatabaseName();
        $rows = $this->connection->select(
            'SELECT COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN ('.implode(',', array_fill(0, count($columns), '?')).')',
            array_merge([$schema, $table], $columns),
        );
        $nullable = [];
        foreach ($rows as $row) {
            $nullable[(string) $row->COLUMN_NAME] = strtoupper((string) $row->IS_NULLABLE) === 'YES';
        }

        foreach ($columns as $column) {
            if (($nullable[$column] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    private function orphanCount(array $relationship): int
    {
        $join = [];
        $notNull = [];
        foreach ($relationship['child_columns'] as $index => $childColumn) {
            $child = 'c.'.$this->quoteIdentifier($childColumn);
            $parent = 'p.'.$this->quoteIdentifier($relationship['parent_columns'][$index]);
            $join[] = "{$child} = {$parent}";
            $notNull[] = "{$child} IS NOT NULL";
        }

        $parentProbe = 'p.'.$this->quoteIdentifier($relationship['parent_columns'][0]);
        $sql = 'SELECT COUNT(*) AS aggregate FROM '.$this->quoteIdentifier($relationship['child_table']).' AS c'
            .' LEFT JOIN '.$this->quoteIdentifier($relationship['parent_table']).' AS p ON '.implode(' AND ', $join)
            .' WHERE '.implode(' AND ', $notNull).' AND '.$parentProbe.' IS NULL';

        return (int) $this->connection->selectOne($sql)->aggregate;
    }

    private function dropConstraint(string $table, string $constraint): void
    {
        $this->connection->statement(sprintf(
            'ALTER TABLE %s DROP FOREIGN KEY %s',
            $this->quoteIdentifier($table),
            $this->quoteIdentifier($constraint),
        ));
    }

    private function addConstraint(array $definition, string $name): void
    {
        $relationship = $this->relationshipSignature($definition);
        $onDelete = $this->normalizeAction((string) ($definition['canonical_on_delete'] ?? $definition['on_delete'] ?? ''));
        $onUpdate = $this->normalizeAction((string) ($definition['canonical_on_update'] ?? $definition['on_update'] ?? ''));
        $childColumns = implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $relationship['child_columns']));
        $parentColumns = implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $relationship['parent_columns']));

        $sql = sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s ON UPDATE %s',
            $this->quoteIdentifier($relationship['child_table']),
            $this->quoteIdentifier($name),
            $childColumns,
            $this->quoteIdentifier($relationship['parent_table']),
            $parentColumns,
            $onDelete,
            $onUpdate,
        );

        $this->connection->statement($sql);
    }

    private function identifier(string $value): string
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new RuntimeException("Unsafe database identifier [{$value}].");
        }

        return strtolower($value);
    }

    private function quoteIdentifier(string $value): string
    {
        return '`'.$this->identifier($value).'`';
    }

    /** @param mixed $columns */
    private function orderedColumns(mixed $columns): array
    {
        if (is_string($columns)) {
            $columns = explode(',', $columns);
        }

        if (! is_array($columns)) {
            throw new RuntimeException('Canonical FK columns must be an ordered array or comma-separated string.');
        }

        return array_values(array_map(fn (mixed $column): string => $this->identifier((string) $column), $columns));
    }

    private function unsigned(string $columnType): bool
    {
        return str_contains(strtolower($columnType), 'unsigned');
    }
}
