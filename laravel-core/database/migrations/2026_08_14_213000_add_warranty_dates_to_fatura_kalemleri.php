<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fatura_kalemleri')) {
            return;
        }

        Schema::table('fatura_kalemleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('fatura_kalemleri', 'garanti_baslangic_tarihi')) {
                $table->date('garanti_baslangic_tarihi')->nullable()->after('seri_nolari');
                $table->date('garanti_bitis_tarihi')->nullable()->after('garanti_baslangic_tarihi');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('fatura_kalemleri', 'garanti_baslangic_tarihi')) {
            Schema::table('fatura_kalemleri', function (Blueprint $table): void {
                $table->dropColumn(['garanti_baslangic_tarihi', 'garanti_bitis_tarihi']);
            });
        }
    }
};
