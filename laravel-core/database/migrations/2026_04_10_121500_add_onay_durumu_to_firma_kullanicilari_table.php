<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('firma_kullanicilari') && ! Schema::hasColumn('firma_kullanicilari', 'onay_durumu')) {
            Schema::table('firma_kullanicilari', function (Blueprint $table): void {
                $table->string('onay_durumu', 40)->nullable()->default('aktif')->after('durum');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('firma_kullanicilari') && Schema::hasColumn('firma_kullanicilari', 'onay_durumu')) {
            Schema::table('firma_kullanicilari', function (Blueprint $table): void {
                $table->dropColumn('onay_durumu');
            });
        }
    }
};

