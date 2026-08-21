<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roller', function (Blueprint $table): void {
            $table->id();
            $table->string('ad');
            $table->string('kod')->unique();
            $table->text('aciklama')->nullable();
            $table->boolean('sistem_rolu_mu')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roller');
    }
};

