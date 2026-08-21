<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('siparis_kalemleri', 'depo_id')) {
            Schema::table('siparis_kalemleri', function (Blueprint $table): void {
                $table->foreignId('depo_id')->nullable()->after('stok_karti_id')->constrained('muhasebe_depolar')->nullOnDelete();
                $table->index(['depo_id', 'stok_karti_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('siparis_kalemleri', 'depo_id')) {
            Schema::table('siparis_kalemleri', function (Blueprint $table): void {
                $table->dropForeign(['depo_id']);
                $table->dropIndex(['depo_id', 'stok_karti_id']);
                $table->dropColumn('depo_id');
            });
        }
    }
};
