<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'aktif_mi')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('aktif_mi')->default(true)->index()->after('super_admin_mi');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'aktif_mi')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('aktif_mi');
            });
        }
    }
};
