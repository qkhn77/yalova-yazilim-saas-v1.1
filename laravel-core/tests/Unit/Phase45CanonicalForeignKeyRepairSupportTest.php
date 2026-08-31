<?php

declare(strict_types=1);

namespace Tests\Unit;

use Database\Migrations\Support\CanonicalForeignKeyRepairSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

require_once __DIR__.'/../../database/migrations/Support/CanonicalForeignKeyRepairSupport.php';

final class Phase45CanonicalForeignKeyRepairSupportTest extends TestCase
{
    private CanonicalForeignKeyRepairSupport $helper;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Pilot helper tests require the isolated MariaDB/MySQL PHPUnit configuration.');
        }

        $this->helper = new CanonicalForeignKeyRepairSupport(DB::connection());
        $this->dropTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->helper)) {
            $this->dropTables();
        }

        parent::tearDown();
    }

    public function test_canonical_equivalent_is_noop(): void
    {
        $this->createBaseTables();
        $definition = $this->definition('CASCADE');
        $name = $this->helper->deterministicConstraintName($definition);
        DB::statement($this->addSql($definition, $name, 'CASCADE'));

        self::assertSame('NO-OP', $this->helper->ensureCanonicalForeignKey($definition));
    }

    public function test_absent_relationship_is_added(): void
    {
        $this->createBaseTables();
        $definition = $this->definition('RESTRICT');

        self::assertSame('ADDED', $this->helper->ensureCanonicalForeignKey($definition));
        self::assertTrue($this->helper->canonicalEquivalentExists($definition));
    }

    public function test_wrong_action_is_replaced(): void
    {
        $this->createBaseTables();
        $definition = $this->definition('RESTRICT');
        DB::statement($this->addSql($definition, 'legacy_wrong_action_fk', 'CASCADE'));

        self::assertSame('REPLACED', $this->helper->ensureCanonicalForeignKey($definition));
        self::assertTrue($this->helper->canonicalEquivalentExists($definition));
        self::assertSame('fk_phase45_fk_child_'.substr(hash('sha256', 'phase45_fk_child|parent_id|phase45_fk_parent|id'), 0, 16), $this->helper->deterministicConstraintName($definition));
    }

    public function test_name_difference_is_semantic_noop(): void
    {
        $this->createBaseTables();
        $definition = $this->definition('RESTRICT');
        DB::statement($this->addSql($definition, 'legacy_semantic_name', 'RESTRICT'));

        self::assertSame('NO-OP', $this->helper->ensureCanonicalForeignKey($definition));
    }

    public function test_orphan_fails_before_ddl(): void
    {
        $this->createBaseTables();
        DB::table('phase45_fk_child')->insert(['parent_id' => 999]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('orphan rows');
        $this->helper->ensureCanonicalForeignKey($this->definition('RESTRICT'));
    }

    public function test_multiple_relationships_fail_fast(): void
    {
        $this->createBaseTables();
        $definition = $this->definition('RESTRICT');
        DB::statement($this->addSql($definition, 'legacy_relationship_a', 'RESTRICT'));
        DB::statement($this->addSql($definition, 'legacy_relationship_b', 'RESTRICT'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Multiple FK constraints');
        $this->helper->ensureCanonicalForeignKey($definition);
    }

    public function test_set_null_nullable_is_supported(): void
    {
        $this->createBaseTables(true);
        $definition = $this->definition('SET NULL');

        self::assertSame('ADDED', $this->helper->ensureCanonicalForeignKey($definition));
    }

    public function test_set_null_non_nullable_fails_before_ddl(): void
    {
        $this->createBaseTables(false);
        $definition = $this->definition('SET NULL');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SET NULL requires nullable');
        $this->helper->ensureCanonicalForeignKey($definition);
    }

    public function test_mariadb_driver_metadata_path_is_used(): void
    {
        self::assertSame('mariadb', DB::connection()->getDriverName());
        self::assertSame('mariadb', $this->helper->driver());
    }

    public function test_generated_name_matches_approved_plan_for_pilot_rows(): void
    {
        $path = dirname(base_path()).DIRECTORY_SEPARATOR.'output'.DIRECTORY_SEPARATOR.'phase45'.DIRECTORY_SEPARATOR.'fk-constraint-name-plan.csv';
        self::assertFileExists($path);
        $handle = fopen($path, 'rb');
        self::assertNotFalse($handle);
        $header = fgetcsv($handle);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $item = array_combine($header, $row);
            if ($item['batch_id'] === 'B08') {
                $rows[$item['fk_id']] = $item;
            }
        }
        fclose($handle);

        self::assertCount(9, $rows);
        foreach ($rows as $row) {
            self::assertSame($row['generated_constraint_name'], $this->helper->deterministicConstraintName([
                'child_table' => $row['child_table'],
                'child_columns' => $row['child_columns'],
                'parent_table' => $row['parent_table'],
                'parent_columns' => $row['parent_columns'],
            ]));
            self::assertLessThanOrEqual(64, (int) $row['name_length']);
        }
    }

    public function test_pilot_manifest_has_exact_b08_contract(): void
    {
        $path = dirname(base_path()).DIRECTORY_SEPARATOR.'output'.DIRECTORY_SEPARATOR.'phase45'.DIRECTORY_SEPARATOR.'fk-repair-plan.csv';
        $handle = fopen($path, 'rb');
        self::assertNotFalse($handle);
        $header = fgetcsv($handle);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $item = array_combine($header, $row);
            if ($item['batch_id'] === 'B08') {
                $rows[] = $item;
            }
        }
        fclose($handle);

        self::assertCount(9, $rows);
        self::assertSame(9, count(array_filter($rows, static fn (array $row): bool => $row['production_behavior'] === 'ADD CANONICAL FK')));
        self::assertSame(9, count(array_filter($rows, static fn (array $row): bool => $row['fresh_behavior'] === 'NO-OP')));
        self::assertSame(0, count(array_filter($rows, static fn (array $row): bool => $row['fresh_behavior'] === 'REPLACE WRONG-ACTION FK')));
    }

    private function createBaseTables(bool $nullable = false): void
    {
        Schema::create('phase45_fk_parent', function ($table): void {
            $table->unsignedBigInteger('id')->primary();
        });
        Schema::create('phase45_fk_child', function ($table) use ($nullable): void {
            $column = $table->unsignedBigInteger('parent_id');
            if ($nullable) {
                $column->nullable();
            }
        });
    }

    private function dropTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::dropIfExists('phase45_fk_child');
        Schema::dropIfExists('phase45_fk_parent');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /** @return array<string, mixed> */
    private function definition(string $onDelete): array
    {
        return [
            'child_table' => 'phase45_fk_child',
            'child_columns' => ['parent_id'],
            'parent_table' => 'phase45_fk_parent',
            'parent_columns' => ['id'],
            'canonical_on_delete' => $onDelete,
            'canonical_on_update' => 'RESTRICT',
        ];
    }

    private function addSql(array $definition, string $name, string $onDelete): string
    {
        return sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s ON UPDATE RESTRICT',
            $definition['child_table'],
            $name,
            $definition['child_columns'][0],
            $definition['parent_table'],
            $definition['parent_columns'][0],
            $onDelete,
        );
    }
}
