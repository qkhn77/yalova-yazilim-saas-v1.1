<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personel_departmanlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('sube_id')->nullable()->constrained('subeler')->nullOnDelete();
            $table->string('ad');
            $table->string('kod')->nullable();
            $table->text('aciklama')->nullable();
            $table->boolean('aktif_mi')->default(true)->index();
            $table->unsignedInteger('siralama')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'sube_id', 'aktif_mi']);
        });

        Schema::create('personel_gorevleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('departman_id')->nullable()->constrained('personel_departmanlari')->nullOnDelete();
            $table->string('ad');
            $table->string('kod')->nullable();
            $table->text('aciklama')->nullable();
            $table->string('varsayilan_maas_tipi', 40)->nullable();
            $table->decimal('varsayilan_ucret', 15, 2)->nullable();
            $table->boolean('aktif_mi')->default(true)->index();
            $table->unsignedInteger('siralama')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'departman_id', 'aktif_mi']);
        });

        Schema::create('personel_ayarlar', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('anahtar');
            $table->json('deger')->nullable();
            $table->timestamps();

            $table->unique(['firma_id', 'anahtar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personel_ayarlar');
        Schema::dropIfExists('personel_gorevleri');
        Schema::dropIfExists('personel_departmanlari');
    }
};
