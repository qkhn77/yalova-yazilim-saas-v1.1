<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stok_kartlari')) {
            return;
        }

        Schema::table('stok_kartlari', function (Blueprint $table): void {
            if (Schema::hasColumn('stok_kartlari', 'gorsel')) {
                $table->dropColumn('gorsel');
            }

            if (Schema::hasColumn('stok_kartlari', 'galeri')) {
                $table->dropColumn('galeri');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stok_kartlari')) {
            return;
        }

        Schema::table('stok_kartlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('stok_kartlari', 'gorsel')) {
                $table->string('gorsel')->nullable()->after('goruntulenme_sayisi');
            }

            if (! Schema::hasColumn('stok_kartlari', 'galeri')) {
                $table->longText('galeri')->nullable()->after('gorsel');
            }
        });
    }
};
