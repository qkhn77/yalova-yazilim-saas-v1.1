<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecommerce_pazaryeri_entegrasyonlari')) {
            $this->ekUniqueIndexYoksaEkle();
            return;
        }

        Schema::create('ecommerce_pazaryeri_entegrasyonlari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('firma_id');
            $table->string('pazaryeri_kodu', 64);
            $table->string('pazaryeri_adi', 120)->nullable();
            $table->boolean('aktif_mi')->default(true);
            $table->string('senkron_yonu', 32)->default('tek_yon');
            $table->unsignedInteger('siparis_cekme_periyodu')->default(30);
            $table->boolean('stok_senkron_aktif')->default(true);
            $table->boolean('fiyat_senkron_aktif')->default(true);
            $table->boolean('siparis_cekme_aktif')->default(true);
            $table->boolean('hata_uyari_aktif')->default(true);
            $table->unsignedTinyInteger('max_deneme')->default(3);
            $table->json('kimlik_bilgileri')->nullable();
            $table->json('ayarlar')->nullable();
            $table->timestamp('son_senkron_at')->nullable();
            $table->timestamps();

            $table->unique(['firma_id', 'pazaryeri_kodu'], 'ecom_pazaryeri_firma_kodu_uniq');
            $table->index(['firma_id', 'aktif_mi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_pazaryeri_entegrasyonlari');
    }

    private function ekUniqueIndexYoksaEkle(): void
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $indexes = DB::select('SHOW INDEX FROM ecommerce_pazaryeri_entegrasyonlari');
        $varMi = false;
        foreach ($indexes as $index) {
            if (isset($index->Key_name) && $index->Key_name === 'ecom_pazaryeri_firma_kodu_uniq') {
                $varMi = true;
                break;
            }
        }

        if (! $varMi) {
            DB::statement('ALTER TABLE ecommerce_pazaryeri_entegrasyonlari ADD UNIQUE ecom_pazaryeri_firma_kodu_uniq (firma_id, pazaryeri_kodu)');
        }
    }
};
