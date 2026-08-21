<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('masraf_butceleri')) {
            return;
        }

        Schema::create('masraf_butceleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('masraf_kategorisi_id')->constrained('masraf_kategorileri')->restrictOnDelete();
            $table->date('donem_baslangic');
            $table->date('donem_bitis');
            $table->decimal('butce_tutari', 18, 2);
            $table->char('para_birimi', 3)->default('TRY');
            $table->string('durum', 24)->default('aktif')->index();
            $table->text('notlar')->nullable();
            $table->timestamps();

            $table->unique(
                ['firma_id', 'masraf_kategorisi_id', 'donem_baslangic', 'donem_bitis', 'para_birimi'],
                'masraf_butce_donem_unique',
            );
            $table->index(
                ['firma_id', 'masraf_kategorisi_id', 'donem_baslangic', 'donem_bitis', 'durum'],
                'masraf_butce_rapor_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masraf_butceleri');
    }
};
