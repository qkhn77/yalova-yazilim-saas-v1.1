<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'teklifler';

    private const INDEX = 'teklifler_firma_deleted_created_durum_idx';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || $this->indexVarMi(self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(
                ['firma_id', 'deleted_at', 'created_at', 'durum'],
                self::INDEX,
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! $this->indexVarMi(self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }

    private function indexVarMi(string $indexAdi): bool
    {
        return collect(Schema::getIndexes(self::TABLE))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexAdi);
    }
};
