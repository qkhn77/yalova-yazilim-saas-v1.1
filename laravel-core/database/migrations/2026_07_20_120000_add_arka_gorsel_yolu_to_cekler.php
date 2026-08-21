<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekler', function (Blueprint $table): void {
            $table->string('arka_gorsel_yolu', 255)->nullable()->after('on_gorsel_yolu');
        });
    }

    public function down(): void
    {
        Schema::table('cekler', function (Blueprint $table): void {
            $table->dropColumn('arka_gorsel_yolu');
        });
    }
};
