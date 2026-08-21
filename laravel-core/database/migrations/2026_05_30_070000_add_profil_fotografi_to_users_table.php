<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'profil_fotografi')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('profil_fotografi')->nullable()->after('telefon');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'profil_fotografi')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('profil_fotografi');
        });
    }
};
