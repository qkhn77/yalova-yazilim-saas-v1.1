<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ecommerce_mesaj_konulari') || Schema::hasColumn('ecommerce_mesaj_konulari', 'siparis_id')) {
            return;
        }

        Schema::table('ecommerce_mesaj_konulari', function (Blueprint $table): void {
            $table->unsignedBigInteger('siparis_id')->nullable()->after('stok_karti_id');
            $table->index(['firma_id', 'siparis_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ecommerce_mesaj_konulari') || ! Schema::hasColumn('ecommerce_mesaj_konulari', 'siparis_id')) {
            return;
        }

        Schema::table('ecommerce_mesaj_konulari', function (Blueprint $table): void {
            $table->dropIndex(['firma_id', 'siparis_id']);
            $table->dropColumn('siparis_id');
        });
    }
};
