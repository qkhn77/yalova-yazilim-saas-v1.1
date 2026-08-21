<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekler', function (Blueprint $table): void {
            $table->string('on_gorsel_yolu', 255)->nullable()->after('aciklama');
        });
    }

    public function down(): void
    {
        Schema::table('cekler', function (Blueprint $table): void {
            $table->dropColumn('on_gorsel_yolu');
        });
    }
};
