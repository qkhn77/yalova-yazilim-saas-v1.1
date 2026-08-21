<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cariler', 'website')) {
            Schema::table('cariler', function (Blueprint $table): void {
                $table->string('website', 255)->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cariler', 'website')) {
            Schema::table('cariler', function (Blueprint $table): void {
                $table->dropColumn('website');
            });
        }
    }
};
