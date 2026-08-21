<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teknik_servis_fis_numaralari')) {
            return;
        }

        Schema::create('teknik_servis_fis_numaralari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();
            $table->unsignedSmallInteger('yil');
            $table->string('prefix', 16);
            $table->unsignedInteger('son_sira')->default(0);
            $table->timestamps();

            $table->unique(['firma_id', 'yil', 'prefix'], 'teknik_servis_fis_numaralari_firma_yil_prefix_unique');
        });
    }

    public function down(): void
    {
        // Onarım migration'ı: rollback sırasında mevcut üretim sayacı tablosunu düşürmeyiz.
    }
};
