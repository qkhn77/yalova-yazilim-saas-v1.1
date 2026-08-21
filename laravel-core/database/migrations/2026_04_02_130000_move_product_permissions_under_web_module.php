<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('yetkiler') || ! Schema::hasColumn('yetkiler', 'modul_kodu')) {
            return;
        }

        DB::table('yetkiler')
            ->where(function ($query): void {
                $query->where('kod', 'like', 'urun.%')
                    ->orWhere('kod', 'like', 'urun_kategori.%');
            })
            ->update(['modul_kodu' => 'web']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('yetkiler') || ! Schema::hasColumn('yetkiler', 'modul_kodu')) {
            return;
        }

        DB::table('yetkiler')
            ->where('kod', 'like', 'urun.%')
            ->update(['modul_kodu' => 'urun']);

        DB::table('yetkiler')
            ->where('kod', 'like', 'urun_kategori.%')
            ->update(['modul_kodu' => 'urun_kategori']);
    }
};
