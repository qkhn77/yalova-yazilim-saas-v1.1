<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stok_hareketleri')) {
            DB::table('stok_hareketleri')->where('islem_turu', 'iade')->update(['islem_turu' => 'satis_iadesi']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stok_hareketleri')) {
            DB::table('stok_hareketleri')->where('islem_turu', 'satis_iadesi')->update(['islem_turu' => 'iade']);
        }
    }
};
