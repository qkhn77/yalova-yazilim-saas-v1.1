<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('faturalar') || Schema::hasColumn('faturalar', 'fatura_sinifi')) {
            return;
        }

        Schema::table('faturalar', function (Blueprint $table): void {
            // Eski gider kayıtları otomatik değiştirilmez. Uygulama bunları
            // legacy kayıt olarak okuyup kullanıcıya güncelleme uyarısı verir.
            $table->string('fatura_sinifi', 32)->nullable()->after('tur');
            $table->index(['firma_id', 'fatura_sinifi'], 'faturalar_firma_sinifi_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('faturalar') || ! Schema::hasColumn('faturalar', 'fatura_sinifi')) {
            return;
        }

        Schema::table('faturalar', function (Blueprint $table): void {
            $table->dropIndex('faturalar_firma_sinifi_idx');
            $table->dropColumn('fatura_sinifi');
        });
    }
};
