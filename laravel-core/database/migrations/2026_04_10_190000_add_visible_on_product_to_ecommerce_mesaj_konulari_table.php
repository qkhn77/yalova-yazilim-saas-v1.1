<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ecommerce_mesaj_konulari') || Schema::hasColumn('ecommerce_mesaj_konulari', 'visible_on_product')) {
            return;
        }

        Schema::table('ecommerce_mesaj_konulari', function (Blueprint $table): void {
            $table->boolean('visible_on_product')->default(false)->after('siparis_id');
            $table->index(['firma_id', 'stok_karti_id', 'visible_on_product'], 'ecom_mesaj_urun_gorunur_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ecommerce_mesaj_konulari') || ! Schema::hasColumn('ecommerce_mesaj_konulari', 'visible_on_product')) {
            return;
        }

        Schema::table('ecommerce_mesaj_konulari', function (Blueprint $table): void {
            $table->dropIndex('ecom_mesaj_urun_gorunur_idx');
            $table->dropColumn('visible_on_product');
        });
    }
};
