<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restoran_masalari', function (Blueprint $table): void {
            $table->string('qr_siparis_kodu', 64)->nullable()->after('kod');
            $table->unique(['firma_id', 'qr_siparis_kodu'], 'restoran_masa_qr_siparis_unique');
        });

        DB::table('restoran_masalari')
            ->whereNull('qr_siparis_kodu')
            ->orderBy('id')
            ->chunkById(100, function ($masalar): void {
                foreach ($masalar as $masa) {
                    DB::table('restoran_masalari')
                        ->where('id', $masa->id)
                        ->update(['qr_siparis_kodu' => Str::random(40)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('restoran_masalari', function (Blueprint $table): void {
            $table->dropUnique('restoran_masa_qr_siparis_unique');
            $table->dropColumn('qr_siparis_kodu');
        });
    }
};
