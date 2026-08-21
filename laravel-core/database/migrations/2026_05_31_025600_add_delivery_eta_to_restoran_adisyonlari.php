<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restoran_adisyonlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('restoran_adisyonlari', 'tahmini_teslimat_at')) {
                $table->dateTime('tahmini_teslimat_at')->nullable()->after('teslimat_adresi');
                $table->text('teslimat_notu')->nullable()->after('tahmini_teslimat_at');
                $table->index(['firma_id', 'tahmini_teslimat_at'], 'restoran_adisyon_tahmini_teslimat_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restoran_adisyonlari', function (Blueprint $table): void {
            if (Schema::hasColumn('restoran_adisyonlari', 'tahmini_teslimat_at')) {
                $table->dropIndex('restoran_adisyon_tahmini_teslimat_idx');
                $table->dropColumn(['tahmini_teslimat_at', 'teslimat_notu']);
            }
        });
    }
};
