<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firmalar', function (Blueprint $table): void {
            $table->id();
            $table->string('ad');
            $table->string('kisa_ad')->nullable();
            $table->string('firma_kodu')->unique();
            $table->string('vergi_no')->nullable()->index();
            $table->string('telefon')->nullable();
            $table->string('eposta')->nullable()->index();
            $table->text('adres')->nullable();
            $table->string('durum', 40)->default('beklemede')->index();
            $table->boolean('onaylandi_mi')->default(false)->index();
            $table->foreignId('onaylayan_kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('onay_tarihi')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firmalar');
    }
};
