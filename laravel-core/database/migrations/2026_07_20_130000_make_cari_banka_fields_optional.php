<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cari_banka_hesaplari', function (Blueprint $table): void {
            $table->string('hesap_adi', 191)->nullable()->change();
            $table->string('banka_adi', 191)->nullable()->change();
            $table->string('sube_adi', 191)->nullable()->change();
            $table->string('hesap_no', 128)->nullable()->change();
        });
    }

    public function down(): void
    {
        $bosAlanVar = DB::table('cari_banka_hesaplari')
            ->whereNull('hesap_adi')
            ->orWhereNull('banka_adi')
            ->orWhereNull('sube_adi')
            ->orWhereNull('hesap_no')
            ->exists();

        if ($bosAlanVar) {
            throw new RuntimeException('Banka hesaplarında boş alanlar bulunduğu için zorunlu alan migration geri alınamaz.');
        }

        Schema::table('cari_banka_hesaplari', function (Blueprint $table): void {
            $table->string('hesap_adi', 191)->nullable(false)->change();
            $table->string('banka_adi', 191)->nullable(false)->change();
            $table->string('sube_adi', 191)->nullable(false)->change();
            $table->string('hesap_no', 128)->nullable(false)->change();
        });
    }
};
