<?php

declare(strict_types=1);

namespace Database\Migrations\Support;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Migration-only support for canonical performance indexes.
 *
 * Functional equivalence is based on ordered columns, uniqueness and prefix
 * metadata; index names are deliberately not part of the equivalence test.
 */
final class RequiredIndexRepairSupport
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    /** @return 'mysql'|'mariadb' */
    public function driver(): string
    {
        $driver = strtolower($this->connection->getDriverName());

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Required index repair supports mysql/mariadb only; received [{$driver}].");
        }

        return $driver;
    }

    /**
     * @param array{table:string,columns:list<string>,unique:bool,name:string} $definition
     * @return 'NO-OP'|'ADDED'
     */
    public function ensureCanonicalIndex(array $definition): string
    {
        $this->driver();
        $normalized = $this->normalizeDefinition($definition);
        $this->assertTableAndColumns($normalized);

        $equivalent = $this->findEquivalentIndexes($normalized);

        if (count($equivalent) > 1) {
            throw new RuntimeException(sprintf(
                'Required index precondition failed: multiple equivalent indexes exist on [%s]: %s.',
                $normalized['table'],
                implode(', ', array_column($equivalent, 'name')),
            ));
        }

        if ($equivalent !== []) {
            return 'NO-OP';
        }

        $columns = implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $normalized['columns']));
        $this->connection->statement(sprintf(
            'ALTER TABLE %s ADD INDEX %s (%s)',
            $this->quoteIdentifier($normalized['table']),
            $this->quoteIdentifier($normalized['name']),
            $columns,
        ));

        $after = $this->findEquivalentIndexes($normalized);

        if (count($after) !== 1) {
            throw new RuntimeException(sprintf(
                'Required index postcondition failed for [%s]; equivalent count is %d.',
                $normalized['name'],
                count($after),
            ));
        }

        return 'ADDED';
    }

    /** @return list<array{name:string,columns:list<string>,unique:bool,prefixes:list<?int>}> */
    public function findEquivalentIndexes(array $definition): array
    {
        $normalized = $this->normalizeDefinition($definition);
        $rows = $this->connection->select(
            'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$this->connection->getDatabaseName(), $normalized['table']],
        );
        $grouped = [];

        foreach ($rows as $row) {
            $name = (string) $row->INDEX_NAME;
            $grouped[$name] ??= [
                'name' => $name,
                'columns' => [],
                'unique' => (int) $row->NON_UNIQUE === 0,
                'prefixes' => [],
            ];
            $grouped[$name]['columns'][] = strtolower((string) $row->COLUMN_NAME);
            $grouped[$name]['prefixes'][] = $row->SUB_PART === null ? null : (int) $row->SUB_PART;
        }

        return array_values(array_filter($grouped, static function (array $index) use ($normalized): bool {
            if ($index['unique'] !== $normalized['unique'] || $index['columns'] !== $normalized['columns']) {
                return false;
            }

            return count(array_filter($index['prefixes'], static fn (?int $prefix): bool => $prefix !== null)) === 0;
        }));
    }

    /** @return array{table:string,columns:list<string>,unique:bool,name:string} */
    private function normalizeDefinition(array $definition): array
    {
        $table = $this->identifier((string) ($definition['table'] ?? ''));
        $columns = $definition['columns'] ?? [];
        if (is_string($columns)) {
            $columns = explode(',', $columns);
        }
        if (! is_array($columns) || $columns === []) {
            throw new RuntimeException('Required index columns must be a non-empty ordered list.');
        }
        $columns = array_values(array_map(fn (mixed $column): string => $this->identifier((string) $column), $columns));
        $name = $this->identifier((string) ($definition['name'] ?? ''));
        if (strlen($name) > 64) {
            throw new RuntimeException("Required index name exceeds 64 characters: [{$name}].");
        }

        return [
            'table' => $table,
            'columns' => $columns,
            'unique' => (bool) ($definition['unique'] ?? false),
            'name' => $name,
        ];
    }

    /** @param array{table:string,columns:list<string>,unique:bool,name:string} $definition */
    private function assertTableAndColumns(array $definition): void
    {
        $schema = $this->connection->getDatabaseName();
        $table = $this->connection->selectOne(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$schema, $definition['table']],
        );
        if (! $table) {
            throw new RuntimeException("Required index precondition failed: table [{$definition['table']}] does not exist.");
        }

        $placeholders = implode(',', array_fill(0, count($definition['columns']), '?'));
        $rows = $this->connection->select(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN ({$placeholders})",
            array_merge([$schema, $definition['table']], $definition['columns']),
        );
        $existing = array_map(static fn (object $row): string => strtolower((string) $row->COLUMN_NAME), $rows);
        foreach ($definition['columns'] as $column) {
            if (! in_array($column, $existing, true)) {
                throw new RuntimeException("Required index precondition failed: column [{$definition['table']}.{$column}] does not exist.");
            }
        }
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
}
