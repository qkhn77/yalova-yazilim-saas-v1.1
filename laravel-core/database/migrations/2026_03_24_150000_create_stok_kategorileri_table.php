<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_kategorileri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('stok_kategorileri')->nullOnDelete();
            $table->string('kod', 64);
            $table->string('ad', 128);
            $table->text('aciklama')->nullable();
            $table->boolean('aktif_mi')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'kod']);
            $table->unique(['firma_id', 'ad']);
            $table->index(['firma_id', 'parent_id']);
            $table->index(['firma_id', 'aktif_mi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_kategorileri');
    }
};
