<?php

declare(strict_types=1);

namespace Tests\Unit;

use Database\Migrations\Support\RequiredIndexRepairSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

require_once __DIR__.'/../../database/migrations/Support/RequiredIndexRepairSupport.php';

final class Phase45RequiredIndexRepairSupportTest extends TestCase
{
    private RequiredIndexRepairSupport $helper;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Required index tests require isolated MariaDB/MySQL.');
        }

        $this->helper = new RequiredIndexRepairSupport(DB::connection());
        $this->dropTable();
        Schema::create('phase45_required_index_test', function ($table): void {
            $table->unsignedBigInteger('firma_id');
            $table->unsignedBigInteger('cihaz_id');
            $table->unsignedBigInteger('marka_id');
            $table->string('model_no', 100);
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->helper)) {
            $this->dropTable();
        }

        parent::tearDown();
    }

    public function test_existing_exact_index_is_noop(): void
    {
        $this->addIndex(['firma_id', 'cihaz_id', 'marka_id', 'model_no'], 'fresh_exact');

        self::assertSame('NO-OP', $this->helper->ensureCanonicalIndex($this->definition()));
        self::assertCount(1, $this->equivalentIndexes());
    }

    public function test_absent_index_is_added(): void
    {
        self::assertSame('ADDED', $this->helper->ensureCanonicalIndex($this->definition()));
        self::assertCount(1, $this->equivalentIndexes());
    }

    public function test_same_functional_index_with_different_name_is_noop(): void
    {
        $this->addIndex(['firma_id', 'cihaz_id', 'marka_id', 'model_no'], 'different_name');

        self::assertSame('NO-OP', $this->helper->ensureCanonicalIndex($this->definition()));
        self::assertSame('different_name', $this->equivalentIndexes()[0]['name']);
    }

    public function test_partial_coverage_is_not_equivalent_and_full_index_is_added(): void
    {
        $this->addIndex(['firma_id', 'cihaz_id'], 'partial_coverage');

        self::assertSame('ADDED', $this->helper->ensureCanonicalIndex($this->definition()));
        self::assertCount(1, $this->equivalentIndexes());
    }

    public function test_rerun_is_noop(): void
    {
        self::assertSame('ADDED', $this->helper->ensureCanonicalIndex($this->definition()));
        self::assertSame('NO-OP', $this->helper->ensureCanonicalIndex($this->definition()));
        self::assertCount(1, $this->equivalentIndexes());
    }

    public function test_unique_mismatch_is_not_equivalent(): void
    {
        Schema::table('phase45_required_index_test', function ($table): void {
            $table->unique(['firma_id', 'cihaz_id', 'marka_id', 'model_no'], 'unique_shape');
        });

        self::assertSame('ADDED', $this->helper->ensureCanonicalIndex($this->definition()));
        self::assertCount(1, $this->equivalentIndexes());
    }

    public function test_column_order_mismatch_is_not_equivalent(): void
    {
        $this->addIndex(['cihaz_id', 'firma_id', 'marka_id', 'model_no'], 'wrong_order');

        self::assertSame('ADDED', $this->helper->ensureCanonicalIndex($this->definition()));
        self::assertCount(1, $this->equivalentIndexes());
    }

    public function test_multiple_equivalent_indexes_fail_fast(): void
    {
        $this->addIndex(['firma_id', 'cihaz_id', 'marka_id', 'model_no'], 'equivalent_a');
        $this->addIndex(['firma_id', 'cihaz_id', 'marka_id', 'model_no'], 'equivalent_b');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('multiple equivalent indexes');
        $this->helper->ensureCanonicalIndex($this->definition());
    }

    /** @return array{table:string,columns:list<string>,unique:bool,name:string} */
    private function definition(): array
    {
        return [
            'table' => 'phase45_required_index_test',
            'columns' => ['firma_id', 'cihaz_id', 'marka_id', 'model_no'],
            'unique' => false,
            'name' => 'ts_kayitli_cihazlar_kimlik_idx',
        ];
    }

    /** @param list<string> $columns */
    private function addIndex(array $columns, string $name): void
    {
        Schema::table('phase45_required_index_test', function ($table) use ($columns, $name): void {
            $table->index($columns, $name);
        });
    }

    /** @return list<array{name:string,columns:list<string>,unique:bool,prefixes:list<?int>}> */
    private function equivalentIndexes(): array
    {
        return $this->helper->findEquivalentIndexes($this->definition());
    }

    private function dropTable(): void
    {
        Schema::dropIfExists('phase45_required_index_test');
    }
}
