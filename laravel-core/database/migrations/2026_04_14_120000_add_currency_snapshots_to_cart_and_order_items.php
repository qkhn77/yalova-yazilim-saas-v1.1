<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sepet_kalemleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('sepet_kalemleri', 'para_birimi')) {
                $table->char('para_birimi', 3)->default('TRY')->after('birim_fiyat');
            }
        });

        Schema::table('siparis_kalemleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('siparis_kalemleri', 'para_birimi')) {
                $table->char('para_birimi', 3)->default('TRY')->after('birim_fiyat');
            }
        });

        DB::table('sepet_kalemleri')->whereNull('para_birimi')->update(['para_birimi' => 'TRY']);
        DB::table('siparis_kalemleri')->whereNull('para_birimi')->update(['para_birimi' => 'TRY']);
    }

    public function down(): void
    {
        Schema::table('siparis_kalemleri', function (Blueprint $table): void {
            if (Schema::hasColumn('siparis_kalemleri', 'para_birimi')) {
                $table->dropColumn('para_birimi');
            }
        });

        Schema::table('sepet_kalemleri', function (Blueprint $table): void {
            if (Schema::hasColumn('sepet_kalemleri', 'para_birimi')) {
                $table->dropColumn('para_birimi');
            }
        });
    }
};
