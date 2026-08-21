<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firma_ayarlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('anahtar');
            $table->longText('deger')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'anahtar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firma_ayarlari');
    }
};

