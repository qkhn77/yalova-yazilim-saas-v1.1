<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subeler', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->string('ad');
            $table->string('kod')->nullable();
            $table->string('telefon')->nullable();
            $table->text('adres')->nullable();
            $table->boolean('aktif_mi')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'aktif_mi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subeler');
    }
};
