<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stok_kartlari', 'stok_takip_tipi')) {
            Schema::table('stok_kartlari', function (Blueprint $table): void {
                $table->string('stok_takip_tipi', 16)->default('basit')->after('stok_takip');
                $table->index(['firma_id', 'stok_takip_tipi']);
            });
        }

    }

    public function down(): void
    {
        if (Schema::hasColumn('stok_kartlari', 'stok_takip_tipi')) {
            Schema::table('stok_kartlari', function (Blueprint $table): void {
                $table->dropIndex(['firma_id', 'stok_takip_tipi']);
                $table->dropColumn('stok_takip_tipi');
            });
        }
    }
};
