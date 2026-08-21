<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_modulleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('planlar')->cascadeOnDelete();
            $table->foreignId('modul_id')->constrained('moduller')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'modul_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_modulleri');
    }
};

