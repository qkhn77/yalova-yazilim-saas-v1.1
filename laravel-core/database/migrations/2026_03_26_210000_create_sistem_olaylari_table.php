<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sistem_olaylari', function (Blueprint $table): void {
            $table->id();
            $table->string('tip', 120);
            $table->string('seviye', 20);
            $table->string('mesaj', 255);
            $table->json('context')->nullable();
            $table->foreignId('firma_id')->nullable()->constrained('firmalar')->nullOnDelete();
            $table->timestamps();

            $table->index(['seviye', 'created_at']);
            $table->index(['tip', 'created_at']);
            $table->index(['firma_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sistem_olaylari');
    }
};
