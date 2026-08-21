<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cariler', function (Blueprint $table) {
            if (! Schema::hasColumn('cariler', 'tc_no')) {
                $table->string('tc_no', 11)->nullable()->after('vergi_no');
            }
        });

        Schema::table('cariler', function (Blueprint $table) {
            $table->index(['firma_id', 'created_at'], 'cariler_firma_created_idx');
        });

        Schema::table('cari_hareketleri', function (Blueprint $table) {
            $table->index(['firma_id', 'created_at'], 'cari_hareketleri_firma_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cari_hareketleri', function (Blueprint $table) {
            $table->dropIndex('cari_hareketleri_firma_created_idx');
        });

        Schema::table('cariler', function (Blueprint $table) {
            $table->dropIndex('cariler_firma_created_idx');
        });

        Schema::table('cariler', function (Blueprint $table) {
            if (Schema::hasColumn('cariler', 'tc_no')) {
                $table->dropColumn('tc_no');
            }
        });
    }
};
