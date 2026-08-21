<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teknik_servis_kayitli_cihazlar')) {
            Schema::table('teknik_servis_kayitli_cihazlar', function (Blueprint $table): void {
                if (! Schema::hasColumn('teknik_servis_kayitli_cihazlar', 'garanti_baslangic_tarihi')) {
                    $table->date('garanti_baslangic_tarihi')->nullable()->after('aktif_mi');
                    $table->date('garanti_bitis_tarihi')->nullable()->after('garanti_baslangic_tarihi');
                    $table->unsignedSmallInteger('bakim_periyot_ay')->nullable()->after('garanti_bitis_tarihi');
                    $table->date('son_bakim_tarihi')->nullable()->after('bakim_periyot_ay');
                    $table->index(['firma_id', 'garanti_bitis_tarihi'], 'ts_kayitli_cihazlar_garanti_idx');
                }
            });
        }

        if (! Schema::hasTable('teknik_servis_kayitli_cihaz_degisiklikleri')) {
            Schema::create('teknik_servis_kayitli_cihaz_degisiklikleri', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();
                $table->unsignedBigInteger('kayitli_cihaz_id');
                $table->unsignedBigInteger('kullanici_id')->nullable();
                $table->string('olay', 32)->default('guncelleme');
                $table->json('eski_degerler')->nullable();
                $table->json('yeni_degerler')->nullable();
                $table->ipAddress('ip_adresi')->nullable();
                $table->timestamps();
                $table->foreign('kayitli_cihaz_id', 'ts_kayitli_deg_cihaz_fk')
                    ->references('id')->on('teknik_servis_kayitli_cihazlar')->cascadeOnDelete();
                $table->foreign('kullanici_id', 'ts_kayitli_deg_kullanici_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->index(['firma_id', 'kayitli_cihaz_id'], 'ts_kayitli_cihaz_deg_firma_cihaz_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('teknik_servis_kayitli_cihaz_degisiklikleri');

        if (Schema::hasColumn('teknik_servis_kayitli_cihazlar', 'garanti_baslangic_tarihi')) {
            Schema::table('teknik_servis_kayitli_cihazlar', function (Blueprint $table): void {
                $table->dropIndex('ts_kayitli_cihazlar_garanti_idx');
                $table->dropColumn([
                    'garanti_baslangic_tarihi', 'garanti_bitis_tarihi',
                    'bakim_periyot_ay', 'son_bakim_tarihi',
                ]);
            });
        }
    }
};
