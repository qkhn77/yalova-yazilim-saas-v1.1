<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muhasebe_depolar')) {
            Schema::create('muhasebe_depolar', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
                $table->string('kod', 64);
                $table->string('ad', 191);
                $table->string('adres')->nullable();
                $table->boolean('varsayilan_mi')->default(false);
                $table->boolean('aktif_mi')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['firma_id', 'kod']);
                $table->index(['firma_id', 'aktif_mi']);
                $table->index(['firma_id', 'varsayilan_mi']);
            });
        }

        foreach (DB::table('firmalar')->pluck('id') as $firmaId) {
            $exists = DB::table('muhasebe_depolar')
                ->where('firma_id', $firmaId)
                ->where('kod', 'MERKEZ')
                ->exists();

            if (! $exists) {
                DB::table('muhasebe_depolar')->insert([
                    'firma_id' => $firmaId,
                    'kod' => 'MERKEZ',
                    'ad' => 'Merkez Depo',
                    'varsayilan_mi' => true,
                    'aktif_mi' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('muhasebe_depolar')
            ->where('kod', 'MERKEZ')
            ->update(['varsayilan_mi' => true, 'aktif_mi' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_depolar');
    }
};
