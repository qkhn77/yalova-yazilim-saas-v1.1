<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cari_yetkili_kisiler', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('cari_id')->constrained('cariler')->cascadeOnDelete();
            $table->string('ad_soyad', 191);
            $table->string('gorevi', 128)->nullable();
            $table->string('telefon', 64)->nullable();
            $table->string('email', 191)->nullable();
            $table->unsignedInteger('sira')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'cari_id', 'sira']);
        });

        Schema::create('cari_adresleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('cari_id')->constrained('cariler')->cascadeOnDelete();
            $table->string('baslik', 128);
            $table->string('tur', 64);
            $table->text('adres');
            $table->string('ulke', 64)->nullable();
            $table->string('il', 64)->nullable();
            $table->string('ilce', 64)->nullable();
            $table->string('posta_kodu', 16)->nullable();
            $table->unsignedInteger('sira')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'cari_id', 'tur']);
        });

        Schema::create('cari_banka_hesaplari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('cari_id')->constrained('cariler')->cascadeOnDelete();
            $table->string('hesap_adi', 191);
            $table->string('banka_adi', 191);
            $table->string('sube_adi', 191);
            $table->string('sube_kodu', 64)->nullable();
            $table->string('hesap_no', 128);
            $table->char('para_birimi', 3)->nullable();
            $table->string('iban', 34)->nullable();
            $table->boolean('varsayilan_mi')->default(false);
            $table->unsignedInteger('sira')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'cari_id', 'varsayilan_mi']);
        });

        // Eski tekil yetkili alanı kaybolmasın; yeni yapıya yalnızca bir kez aktarılır.
        DB::table('cariler')
            ->whereNotNull('yetkili_kisi')
            ->where('yetkili_kisi', '<>', '')
            ->orderBy('id')
            ->eachById(function (object $cari): void {
                $zatenVar = DB::table('cari_yetkili_kisiler')
                    ->where('cari_id', $cari->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($zatenVar) {
                    return;
                }

                DB::table('cari_yetkili_kisiler')->insert([
                    'firma_id' => $cari->firma_id,
                    'cari_id' => $cari->id,
                    'ad_soyad' => $cari->yetkili_kisi,
                    'sira' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('cari_banka_hesaplari');
        Schema::dropIfExists('cari_adresleri');
        Schema::dropIfExists('cari_yetkili_kisiler');
    }
};
