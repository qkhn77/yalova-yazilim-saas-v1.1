<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cariler', function (Blueprint $table): void {
            if (! Schema::hasColumn('cariler', 'posta_kodu')) {
                $table->string('posta_kodu', 20)->nullable()->after('ilce');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cariler', function (Blueprint $table): void {
            if (Schema::hasColumn('cariler', 'posta_kodu')) {
                $table->dropColumn('posta_kodu');
            }
        });
    }
};
