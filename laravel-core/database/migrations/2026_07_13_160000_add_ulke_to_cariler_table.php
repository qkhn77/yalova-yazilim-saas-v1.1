<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cariler') || Schema::hasColumn('cariler', 'ulke')) {
            return;
        }

        Schema::table('cariler', function (Blueprint $table): void {
            $table->string('ulke', 64)->nullable()->after('adres');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cariler') || ! Schema::hasColumn('cariler', 'ulke')) {
            return;
        }

        Schema::table('cariler', function (Blueprint $table): void {
            $table->dropColumn('ulke');
        });
    }
};
