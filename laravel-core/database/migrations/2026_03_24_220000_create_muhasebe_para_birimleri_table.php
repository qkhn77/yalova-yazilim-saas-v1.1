<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('muhasebe_para_birimleri')) {
            return;
        }

        Schema::create('muhasebe_para_birimleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->char('kod', 3);
            $table->string('ad', 64)->nullable();
            $table->boolean('aktif_mi')->default(true);
            $table->timestamps();

            $table->unique(['firma_id', 'kod']);
            $table->index(['firma_id', 'aktif_mi']);
        });

        $simdi = now();
        foreach (DB::table('firmalar')->pluck('id') as $firmaId) {
            DB::table('muhasebe_para_birimleri')->insert([
                'firma_id' => $firmaId,
                'kod' => 'TRY',
                'ad' => 'Türk Lirası',
                'aktif_mi' => true,
                'created_at' => $simdi,
                'updated_at' => $simdi,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_para_birimleri');
    }
};
