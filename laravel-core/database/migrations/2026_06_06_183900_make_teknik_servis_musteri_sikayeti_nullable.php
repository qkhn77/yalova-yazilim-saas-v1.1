<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            $table->text('musteri_sikayeti')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            $table->text('musteri_sikayeti')->nullable(false)->change();
        });
    }
};
