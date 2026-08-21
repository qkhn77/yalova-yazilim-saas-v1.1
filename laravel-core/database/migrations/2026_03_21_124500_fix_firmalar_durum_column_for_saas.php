<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('firmalar') || ! Schema::hasColumn('firmalar', 'durum')) {
            return;
        }

        $surucu = Schema::getConnection()->getDriverName();
        if (in_array($surucu, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `firmalar` MODIFY `durum` VARCHAR(40) NOT NULL DEFAULT 'beklemede'");
        }
        // SQLite: MODIFY desteklenmez; firmalar.durum zaten create migration ile uyumludur.
    }

    public function down(): void
    {
        if (! Schema::hasTable('firmalar') || ! Schema::hasColumn('firmalar', 'durum')) {
            return;
        }

        $surucu = Schema::getConnection()->getDriverName();
        if (in_array($surucu, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `firmalar` MODIFY `durum` VARCHAR(40) NOT NULL DEFAULT 'beklemede'");
        }
    }
};
