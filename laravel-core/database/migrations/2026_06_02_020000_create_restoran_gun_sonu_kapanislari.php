<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restoran_gun_sonu_kapanislari')) {
            return;
        }

        Schema::create('restoran_gun_sonu_kapanislari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->date('tarih');
            $table->decimal('toplam_tahsilat', 15, 2)->default(0);
            $table->decimal('toplam_muhasebe', 15, 2)->default(0);
            $table->decimal('toplam_fark', 15, 2)->default(0);
            $table->boolean('mutabik_mi')->default(false);
            $table->json('kanal_ozeti')->nullable();
            $table->text('fark_aciklamasi')->nullable();
            $table->text('notlar')->nullable();
            $table->foreignId('kapatan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('kapandi_at');
            $table->timestamps();

            $table->unique(['firma_id', 'tarih'], 'rest_gun_sonu_firma_tarih_unique');
            $table->index(['firma_id', 'kapandi_at'], 'rest_gun_sonu_firma_kapandi_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restoran_gun_sonu_kapanislari');
    }
};
