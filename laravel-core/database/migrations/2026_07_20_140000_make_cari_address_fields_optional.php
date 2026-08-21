<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cari_adresleri', function (Blueprint $table): void {
            $table->string('baslik', 128)->nullable()->change();
            $table->string('tur', 64)->nullable()->change();
            $table->text('adres')->nullable()->change();
        });
    }

    public function down(): void
    {
        $bosAlanVar = DB::table('cari_adresleri')
            ->whereNull('baslik')
            ->orWhereNull('tur')
            ->orWhereNull('adres')
            ->exists();

        if ($bosAlanVar) {
            throw new RuntimeException('Adres kayıtlarında boş alanlar bulunduğu için zorunlu alan migration geri alınamaz.');
        }

        Schema::table('cari_adresleri', function (Blueprint $table): void {
            $table->string('baslik', 128)->nullable(false)->change();
            $table->string('tur', 64)->nullable(false)->change();
            $table->text('adres')->nullable(false)->change();
        });
    }
};
