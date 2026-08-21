<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Çoklu ölçü girişini ürün politikasından kaldırır. Ölçü ve hareket
     * kayıtları denetim izi olarak korunur; kartların seçim tipi sabit ölçüye
     * çekilir. Böylece geçmiş stok miktarları kaybolmaz.
     */
    public function up(): void
    {
        if (! Schema::hasTable('stok_kartlari')) {
            return;
        }

        DB::table('stok_kartlari')
            ->where('olcu_yapisi', 'coklu')
            ->update([
                'olcu_yapisi' => 'sabit',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Hangi kartların çoklu yapıdan dönüştürüldüğü ayrıca tutulmadığından
        // ters geçiş otomatik yapılamaz.
    }
};
