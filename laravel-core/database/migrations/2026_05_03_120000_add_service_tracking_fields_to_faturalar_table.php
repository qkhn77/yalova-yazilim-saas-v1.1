<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturalar', function (Blueprint $table): void {
            if (! Schema::hasColumn('faturalar', 'islem_tipi')) {
                $table->string('islem_tipi', 64)->nullable()->after('kaynak_tipi');
                $table->index(['firma_id', 'islem_tipi'], 'faturalar_firma_islem_tipi_index');
            }

            if (! Schema::hasColumn('faturalar', 'islem_no')) {
                $table->unsignedBigInteger('islem_no')->nullable()->after('islem_tipi');
                $table->index(['firma_id', 'islem_no'], 'faturalar_firma_islem_no_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('faturalar', function (Blueprint $table): void {
            if (Schema::hasColumn('faturalar', 'islem_no')) {
                $table->dropIndex('faturalar_firma_islem_no_index');
                $table->dropColumn('islem_no');
            }

            if (Schema::hasColumn('faturalar', 'islem_tipi')) {
                $table->dropIndex('faturalar_firma_islem_tipi_index');
                $table->dropColumn('islem_tipi');
            }
        });
    }
};
