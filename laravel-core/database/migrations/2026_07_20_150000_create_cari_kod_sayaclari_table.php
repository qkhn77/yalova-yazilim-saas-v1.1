<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cari_kod_sayaclari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->unsignedInteger('son_numara')->default(999);
            $table->timestamps();

            $table->unique('firma_id');
        });

        DB::table('firmalar')
            ->select('id')
            ->orderBy('id')
            ->eachById(function (object $firma): void {
                $sonNumara = 999;

                DB::table('cariler')
                    ->where('firma_id', $firma->id)
                    ->where('kod', 'like', 'CR-%')
                    ->pluck('kod')
                    ->each(function (mixed $kod) use (&$sonNumara): void {
                        if (preg_match('/^CR-(\d+)$/', (string) $kod, $eslesme) === 1) {
                            $sonNumara = max($sonNumara, (int) $eslesme[1]);
                        }
                    });

                DB::table('cari_kod_sayaclari')->insert([
                    'firma_id' => $firma->id,
                    'son_numara' => $sonNumara,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('cari_kod_sayaclari');
    }
};
