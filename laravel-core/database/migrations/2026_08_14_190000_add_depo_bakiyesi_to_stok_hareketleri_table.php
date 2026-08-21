<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stok_hareketleri', 'depo_id')) {
            Schema::table('stok_hareketleri', function (Blueprint $table): void {
                $table->foreignId('depo_id')->nullable()->after('stok_id')->constrained('muhasebe_depolar')->nullOnDelete();
                $table->index(['firma_id', 'depo_id', 'stok_id']);
            });
        }

        if (! Schema::hasTable('stok_depo_bakiyeleri')) {
            Schema::create('stok_depo_bakiyeleri', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
                $table->foreignId('depo_id')->constrained('muhasebe_depolar')->restrictOnDelete();
                $table->foreignId('stok_id')->constrained('stok_kartlari')->restrictOnDelete();
                $table->decimal('miktar', 18, 4)->default(0);
                $table->decimal('rezerve_miktar', 18, 4)->default(0);
                $table->timestamps();

                $table->unique(['firma_id', 'depo_id', 'stok_id']);
                $table->index(['firma_id', 'stok_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_depo_bakiyeleri');
        if (Schema::hasColumn('stok_hareketleri', 'depo_id')) {
            Schema::table('stok_hareketleri', function (Blueprint $table): void {
                $table->dropForeign(['depo_id']);
                $table->dropIndex(['firma_id', 'depo_id', 'stok_id']);
                $table->dropColumn('depo_id');
            });
        }
    }
};
