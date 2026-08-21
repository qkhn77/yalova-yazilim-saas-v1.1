<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'admin_layout')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('admin_layout', 32)
                    ->nullable()
                    ->after('profil_fotografi');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'admin_layout')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('admin_layout');
            });
        }
    }
};
