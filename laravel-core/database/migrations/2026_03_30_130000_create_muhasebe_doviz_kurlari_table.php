<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('muhasebe_doviz_kurlari')) {
            return;
        }

        Schema::create('muhasebe_doviz_kurlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->char('kaynak_para_birimi', 3);
            $table->char('hedef_para_birimi', 3);
            $table->date('tarih');
            $table->decimal('kur', 18, 8);
            $table->boolean('manuel_mi')->default(true);
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->unique(
                ['firma_id', 'kaynak_para_birimi', 'hedef_para_birimi', 'tarih'],
                'muhasebe_doviz_kurlari_unique'
            );
            $table->index(['firma_id', 'tarih'], 'muhasebe_doviz_kurlari_firma_tarih_index');
            $table->index(
                ['firma_id', 'kaynak_para_birimi', 'hedef_para_birimi'],
                'muhasebe_doviz_kurlari_pair_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_doviz_kurlari');
    }
};
