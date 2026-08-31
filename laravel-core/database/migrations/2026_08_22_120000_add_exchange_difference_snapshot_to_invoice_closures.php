<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fatura_finans_kapatmalari')) {
            return;
        }

        Schema::table('fatura_finans_kapatmalari', function (Blueprint $table): void {
            if (! Schema::hasColumn('fatura_finans_kapatmalari', 'baz_fatura_tutari')) {
                $table->decimal('baz_fatura_tutari', 18, 8)->nullable()->after('baz_uygulanan_tutar');
            }
            if (! Schema::hasColumn('fatura_finans_kapatmalari', 'kur_farki_tutari')) {
                $table->decimal('kur_farki_tutari', 18, 8)->nullable()->after('baz_fatura_tutari');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fatura_finans_kapatmalari')) {
            return;
        }

        Schema::table('fatura_finans_kapatmalari', function (Blueprint $table): void {
            if (Schema::hasColumn('fatura_finans_kapatmalari', 'kur_farki_tutari')) {
                $table->dropColumn('kur_farki_tutari');
            }
            if (Schema::hasColumn('fatura_finans_kapatmalari', 'baz_fatura_tutari')) {
                $table->dropColumn('baz_fatura_tutari');
            }
        });
    }
};
