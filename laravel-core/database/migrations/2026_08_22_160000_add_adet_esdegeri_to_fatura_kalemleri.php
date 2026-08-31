<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fatura_kalemleri') || Schema::hasColumn('fatura_kalemleri', 'adet_esdegeri')) {
            return;
        }

        Schema::table('fatura_kalemleri', function (Blueprint $table): void {
            $table->decimal('adet_esdegeri', 20, 8)->nullable()->after('ana_miktar');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('fatura_kalemleri') && Schema::hasColumn('fatura_kalemleri', 'adet_esdegeri')) {
            Schema::table('fatura_kalemleri', function (Blueprint $table): void {
                $table->dropColumn('adet_esdegeri');
            });
        }
    }
};
