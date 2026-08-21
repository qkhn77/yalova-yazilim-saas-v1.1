<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol_yetkileri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rol_id')->constrained('roller')->cascadeOnDelete();
            $table->foreignId('yetki_id')->constrained('yetkiler')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['rol_id', 'yetki_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_yetkileri');
    }
};

