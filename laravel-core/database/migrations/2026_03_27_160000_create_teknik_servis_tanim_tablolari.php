<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teknik_servis_tanim_cihazlar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();
            $table->string('ad', 191);
            $table->string('kod', 64)->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('siralama')->default(0);
            $table->boolean('varsayilan_mi')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'aktif']);
        });

        Schema::create('teknik_servis_tanim_markalar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();
            $table->string('ad', 191);
            $table->string('kod', 64)->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('siralama')->default(0);
            $table->boolean('varsayilan_mi')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'aktif']);
        });

        Schema::create('teknik_servis_tanim_aksesuarlar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();
            $table->string('ad', 191);
            $table->string('kod', 64)->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('siralama')->default(0);
            $table->boolean('varsayilan_mi')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'aktif']);
        });

        Schema::create('teknik_servis_tanim_servis_durumlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();
            $table->string('ad', 191);
            $table->string('kod', 64)->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('siralama')->default(0);
            $table->boolean('varsayilan_mi')->default(false);
            $table->boolean('is_fiyat_verildi')->default(false);
            $table->boolean('is_teslim_edildi')->default(false);
            $table->boolean('is_iptal')->default(false);
            $table->boolean('is_iade')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'aktif']);
        });

        Schema::create('teknik_servis_tanim_arizalar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();
            $table->foreignId('cihaz_id')->constrained('teknik_servis_tanim_cihazlar')->restrictOnDelete();
            $table->string('ad', 191);
            $table->string('kod', 64)->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('siralama')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'cihaz_id']);
            $table->index(['firma_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teknik_servis_tanim_arizalar');
        Schema::dropIfExists('teknik_servis_tanim_servis_durumlari');
        Schema::dropIfExists('teknik_servis_tanim_aksesuarlar');
        Schema::dropIfExists('teknik_servis_tanim_markalar');
        Schema::dropIfExists('teknik_servis_tanim_cihazlar');
    }
};
