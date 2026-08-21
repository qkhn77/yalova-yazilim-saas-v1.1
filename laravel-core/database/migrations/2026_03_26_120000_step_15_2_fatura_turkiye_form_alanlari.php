<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('faturalar')) {
            return;
        }

        Schema::table('faturalar', function (Blueprint $table): void {
            if (! Schema::hasColumn('faturalar', 'irsaliye_no')) {
                $table->string('irsaliye_no', 64)->nullable()->after('belge_no');
            }
            if (! Schema::hasColumn('faturalar', 'tevkifat_orani')) {
                $table->decimal('tevkifat_orani', 5, 2)->nullable()->after('kdv_toplam');
            }
            if (! Schema::hasColumn('faturalar', 'e_belge_tipi')) {
                $table->string('e_belge_tipi', 32)->nullable()->after('kaynak_tipi');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('faturalar')) {
            return;
        }

        Schema::table('faturalar', function (Blueprint $table): void {
            foreach (['irsaliye_no', 'tevkifat_orani', 'e_belge_tipi'] as $kolon) {
                if (Schema::hasColumn('faturalar', $kolon)) {
                    $table->dropColumn($kolon);
                }
            }
        });
    }
};
