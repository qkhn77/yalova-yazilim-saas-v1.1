<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personeller', function (Blueprint $table): void {
            if (! Schema::hasColumn('personeller', 'pin_kodu_hash')) {
                $table->string('pin_kodu_hash')->nullable()->after('pin_kodu');
            }
        });

        DB::table('personeller')
            ->whereNotNull('pin_kodu')
            ->where('pin_kodu', '!=', '')
            ->orderBy('id')
            ->select(['id', 'pin_kodu'])
            ->chunkById(100, function ($personeller): void {
                foreach ($personeller as $personel) {
                    DB::table('personeller')
                        ->where('id', $personel->id)
                        ->update([
                            'pin_kodu_hash' => Hash::make((string) $personel->pin_kodu),
                            'pin_kodu' => null,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('personeller', function (Blueprint $table): void {
            if (Schema::hasColumn('personeller', 'pin_kodu_hash')) {
                $table->dropColumn('pin_kodu_hash');
            }
        });
    }
};
