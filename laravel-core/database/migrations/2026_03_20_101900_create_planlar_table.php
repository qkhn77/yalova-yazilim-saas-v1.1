<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planlar', function (Blueprint $table): void {
            $table->id();
            $table->string('ad');
            $table->string('kod')->unique();
            $table->decimal('ucret', 12, 2)->default(0);
            $table->unsignedInteger('sure_gun')->default(30);
            $table->boolean('aktif_mi')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planlar');
    }
};

