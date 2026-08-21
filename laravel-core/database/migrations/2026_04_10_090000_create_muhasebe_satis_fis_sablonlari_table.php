<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('muhasebe_satis_fis_sablonlari')) {
            return;
        }

        Schema::create('muhasebe_satis_fis_sablonlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('ad', 191);
            $table->string('kod', 64);
            $table->string('sayfa_tipi', 16)->default('80mm');
            $table->longText('sablon_html');
            $table->longText('sablon_css')->nullable();
            $table->boolean('varsayilan_mi')->default(false);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['firma_id', 'kod'], 'muhasebe_satis_fis_sablonlari_firma_kod_uq');
            $table->index(['firma_id', 'varsayilan_mi'], 'muhasebe_satis_fis_sablonlari_firma_varsayilan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_satis_fis_sablonlari');
    }
};

