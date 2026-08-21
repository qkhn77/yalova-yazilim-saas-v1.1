<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('denetim_kayitlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->nullable()->constrained('firmalar')->nullOnDelete();
            $table->foreignId('kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('olay')->index();
            $table->string('konu_tipi')->nullable()->index();
            $table->unsignedBigInteger('konu_id')->nullable()->index();
            $table->json('eski_veri')->nullable();
            $table->json('yeni_veri')->nullable();
            $table->string('ip_adresi', 45)->nullable();
            $table->text('kullanici_ajan')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('denetim_kayitlari');
    }
};

