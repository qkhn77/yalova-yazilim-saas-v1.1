<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('muhasebe_marka_ureticileri')) {
            return;
        }

        Schema::create('muhasebe_marka_ureticileri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->nullable()->constrained('firmalar')->cascadeOnDelete();
            $table->boolean('is_sabit')->default(false);
            $table->unsignedBigInteger('tanim_firma_kapsami')->default(0);
            $table->string('kod', 64);
            $table->string('ad', 191)->nullable();
            $table->boolean('aktif_mi')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tanim_firma_kapsami', 'kod']);
            $table->index(['firma_id', 'is_sabit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_marka_ureticileri');
    }
};
