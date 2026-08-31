<?php

declare(strict_types=1);

namespace Tests\Unit;

use Database\Migrations\Support\CanonicalForeignKeyRepairSupport;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

require_once __DIR__.'/../../database/migrations/Support/CanonicalForeignKeyRepairSupport.php';

final class Phase45AllCanonicalManifestIntegrityTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function migrationProvider(): array
    {
        return [
            'B01' => ['B01', '2026_08_25_090000_repair_canonical_fk_core_auth_tenant.php'],
            'B02' => ['B02', '2026_08_25_091000_repair_canonical_fk_auth_permission.php'],
            'B03' => ['B03', '2026_08_25_092000_repair_canonical_fk_configuration.php'],
            'B04' => ['B04', '2026_08_25_093000_repair_canonical_fk_accounting_finance.php'],
            'B05' => ['B05', '2026_08_25_094000_repair_canonical_fk_ecommerce_order.php'],
            'B06' => ['B06', '2026_08_25_095000_repair_canonical_fk_stock_depot_reference.php'],
            'B07' => ['B07', '2026_08_25_096000_repair_canonical_fk_technical_service.php'],
            'B08' => ['B08', '2026_08_25_097000_repair_canonical_fk_other_finance.php'],
            'B09' => ['B09', '2026_08_25_098000_repair_canonical_fk_other_ecommerce_content.php'],
        ];
    }

    #[DataProvider('migrationProvider')]
    public function test_each_migration_manifest_matches_the_approved_plan(string $batchId, string $filename): void
    {
        $plan = $this->planRows();
        $expected = array_values(array_filter($plan, static fn (array $row): bool => $row['batch_id'] === $batchId));
        $actual = $this->migrationManifest($filename);

        self::assertCount(count($expected), $actual);
        self::assertSame(
            array_values(array_map(static fn (array $row): string => $row['fk_id'], $expected)),
            array_values(array_map(static fn (array $row): string => $row['fk_id'], $actual)),
            $batchId.' migration order/IDs must match fk-repair-plan.csv',
        );

        foreach ($expected as $index => $row) {
            $definition = $actual[$index];
            self::assertSame($row['child'], $definition['child_table'], $row['fk_id'].' child table');
            self::assertSame([$row['child_columns']], $definition['child_columns'], $row['fk_id'].' child columns');
            self::assertSame($row['parent'], $definition['parent_table'], $row['fk_id'].' parent table');
            self::assertSame([$row['parent_columns']], $definition['parent_columns'], $row['fk_id'].' parent columns');
            self::assertSame($row['canonical_on_delete'], $definition['canonical_on_delete'], $row['fk_id'].' delete action');
            self::assertSame($row['canonical_on_update'], $definition['canonical_on_update'], $row['fk_id'].' update action');
        }
    }

    public function test_all_nine_migrations_cover_the_exact_170_row_plan(): void
    {
        $actual = [];
        foreach (self::migrationProvider() as $item) {
            $actual = [...$actual, ...$this->migrationManifest($item[1])];
        }

        self::assertCount(170, $actual);
        self::assertCount(170, array_unique(array_map(static fn (array $row): string => $row['fk_id'], $actual)));
        $expectedIds = array_map(static fn (array $row): string => $row['fk_id'], $this->planRows());
        $actualIds = array_map(static fn (array $row): string => $row['fk_id'], $actual);
        sort($expectedIds);
        sort($actualIds);
        self::assertSame($expectedIds, $actualIds);
    }

    public function test_approved_action_distribution_and_batch_distribution_are_preserved(): void
    {
        $plan = $this->planRows();
        $actions = array_count_values(array_column($plan, 'canonical_on_delete'));
        ksort($actions);
        self::assertSame(['CASCADE' => 25, 'RESTRICT' => 81, 'SET NULL' => 64], $actions);
        $batches = array_count_values(array_column($plan, 'batch_id'));
        ksort($batches);
        self::assertSame(['B01' => 13, 'B02' => 8, 'B03' => 6, 'B04' => 32, 'B05' => 8, 'B06' => 25, 'B07' => 61, 'B08' => 9, 'B09' => 8], $batches);
        self::assertSame(30, count(array_filter($plan, static fn (array $row): bool => $row['fresh_behavior'] === 'REPLACE WRONG-ACTION FK')));
    }

    public function test_generated_names_for_all_manifest_rows_match_the_approved_name_plan(): void
    {
        $nameRows = [];
        foreach ($this->readCsv($this->workspacePath('output/phase45/fk-constraint-name-plan.csv')) as $row) {
            $nameRows[$row['fk_id']] = $row;
        }
        self::assertCount(170, $nameRows);

        $helper = new CanonicalForeignKeyRepairSupport($this->app->make('db')->connection());
        foreach (self::migrationProvider() as $item) {
            foreach ($this->migrationManifest($item[1]) as $definition) {
                $row = $nameRows[$definition['fk_id']];
                self::assertSame($row['generated_constraint_name'], $helper->deterministicConstraintName($definition), $definition['fk_id']);
                self::assertLessThanOrEqual(64, (int) $row['name_length']);
            }
        }
    }

    /** @return list<array<string, string>> */
    private function planRows(): array
    {
        return $this->readCsv($this->workspacePath('output/phase45/fk-repair-plan.csv'));
    }

    /** @return list<array<string, mixed>> */
    private function migrationManifest(string $filename): array
    {
        $migration = require $this->workspacePath('laravel-core/database/migrations/'.$filename);
        $method = new \ReflectionMethod($migration, 'manifest');
        $method->setAccessible(true);

        /** @var list<array<string, mixed>> $manifest */
        $manifest = $method->invoke(null);

        return $manifest;
    }

    /** @return list<array<string, string>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        self::assertNotFalse($handle);
        $header = fgetcsv($handle);
        self::assertIsArray($header);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $row);
        }
        fclose($handle);

        return $rows;
    }

    private function workspacePath(string $relative): string
    {
        return dirname(base_path()).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
