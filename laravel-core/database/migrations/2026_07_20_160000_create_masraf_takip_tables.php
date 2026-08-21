<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masraf_kategorileri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('kod', 64);
            $table->string('ad', 120);
            $table->unsignedSmallInteger('sira')->default(0);
            $table->boolean('aktif_mi')->default(true)->index();
            $table->timestamps();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'aktif_mi', 'sira']);
        });

        Schema::create('masraflar', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('masraf_kategorisi_id')->constrained('masraf_kategorileri')->restrictOnDelete();
            $table->date('tarih');
            $table->decimal('tutar', 18, 2);
            $table->char('para_birimi', 3)->default('TRY');
            $table->string('aciklama', 191);
            $table->text('notlar')->nullable();
            $table->string('durum', 24)->default('aktif')->index();
            $table->string('idempotency_key', 96);
            $table->foreignId('olusturan_kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('iptal_eden_kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('iptal_nedeni')->nullable();
            $table->timestamp('iptal_edildi_at')->nullable();
            $table->timestamps();

            $table->unique(['firma_id', 'idempotency_key']);
            $table->index(['firma_id', 'tarih', 'durum']);
            $table->index(['firma_id', 'masraf_kategorisi_id', 'tarih']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masraflar');
        Schema::dropIfExists('masraf_kategorileri');
    }
};
