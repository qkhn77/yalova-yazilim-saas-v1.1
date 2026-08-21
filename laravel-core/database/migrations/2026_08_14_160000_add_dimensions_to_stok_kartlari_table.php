<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('stok_kartlari', 'en_cm')) {
                $table->decimal('en_cm', 10, 2)->nullable()->after('birim');
            }
            if (! Schema::hasColumn('stok_kartlari', 'boy_cm')) {
                $table->decimal('boy_cm', 10, 2)->nullable()->after('en_cm');
            }
            if (! Schema::hasColumn('stok_kartlari', 'kalinlik_cm')) {
                $table->decimal('kalinlik_cm', 10, 2)->nullable()->after('boy_cm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stok_kartlari', function (Blueprint $table): void {
            $columns = [];
            foreach (['en_cm', 'boy_cm', 'kalinlik_cm'] as $column) {
                if (Schema::hasColumn('stok_kartlari', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
