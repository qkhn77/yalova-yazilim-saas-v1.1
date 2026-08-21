<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('araclar', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('plaka', 20);
            $table->string('marka', 80);
            $table->string('model', 80);
            $table->unsignedSmallInteger('model_yili')->nullable();
            $table->string('yakit_tipi', 32)->nullable();
            $table->unsignedInteger('kilometre')->default(0);
            $table->date('sigorta_bitis')->nullable();
            $table->date('muayene_bitis')->nullable();
            $table->boolean('aktif_mi')->default(true);
            $table->text('notlar')->nullable();
            $table->timestamps();

            $table->unique(['firma_id', 'plaka']);
            $table->index(['firma_id', 'aktif_mi', 'plaka']);
        });

        Schema::create('duzenli_fatura_tanimlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('masraf_kategorisi_id')->constrained('masraf_kategorileri')->restrictOnDelete();
            $table->string('ad', 120);
            $table->string('abone_no', 120)->nullable();
            $table->string('tedarikci', 160)->nullable();
            $table->boolean('aktif_mi')->default(true);
            $table->text('notlar')->nullable();
            $table->timestamps();

            $table->unique(['firma_id', 'masraf_kategorisi_id', 'ad']);
            $table->index(['firma_id', 'aktif_mi', 'masraf_kategorisi_id'], 'duzenli_fatura_aktif_kategori_index');
        });

        Schema::create('masraf_arac_detaylari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('masraf_id')->constrained('masraflar')->cascadeOnDelete();
            $table->foreignId('arac_id')->constrained('araclar')->restrictOnDelete();
            $table->decimal('yakit_litre', 12, 3)->nullable();
            $table->decimal('litre_fiyati', 18, 4)->nullable();
            $table->unsignedInteger('kilometre')->nullable();
            $table->timestamps();

            $table->unique('masraf_id');
            $table->index(['firma_id', 'arac_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masraf_arac_detaylari');
        Schema::dropIfExists('duzenli_fatura_tanimlari');
        Schema::dropIfExists('araclar');
    }
};
